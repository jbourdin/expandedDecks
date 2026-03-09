<?php

declare(strict_types=1);

/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\Entity\BannedCard;
use App\Repository\BannedCardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches the official Pokemon TCG banned card list and syncs it to the database.
 *
 * @see docs/features.md F6.5 — Banned card list management
 */
#[AsCommand(
    name: 'app:banned-cards:sync',
    description: 'Sync the Expanded banned card list from the official Pokemon website.',
)]
class BannedCardsSyncCommand extends Command
{
    private const string SOURCE_URL = 'https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly BannedCardRepository $bannedCardRepo,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Fetching banned card list from pokemon.com');

        $cardNames = $this->fetchBannedCardNames($io);

        if ([] === $cardNames) {
            $io->error('No banned cards found — the page structure may have changed.');

            return Command::FAILURE;
        }

        $io->text(\sprintf('Found %d banned cards on the page.', \count($cardNames)));

        $added = 0;
        $unchanged = 0;

        foreach ($cardNames as $name) {
            $existing = $this->bannedCardRepo->findOneByName($name);

            if (null !== $existing) {
                ++$unchanged;
                continue;
            }

            $card = new BannedCard();
            $card->setCardName($name);
            $card->setSourceUrl(self::SOURCE_URL);

            $this->entityManager->persist($card);
            ++$added;
        }

        // Remove cards no longer on the banned list
        $existingCards = $this->bannedCardRepo->findAll();
        $bannedSet = array_flip($cardNames);
        $removed = 0;

        foreach ($existingCards as $existing) {
            if (!isset($bannedSet[$existing->getCardName()])) {
                $this->entityManager->remove($existing);
                ++$removed;
            }
        }

        $this->entityManager->flush();

        $io->success(\sprintf(
            'Sync complete: %d added, %d removed, %d unchanged.',
            $added,
            $removed,
            $unchanged,
        ));

        return Command::SUCCESS;
    }

    /**
     * Parses the Expanded section of the banned card page.
     *
     * @return list<string>
     */
    private function fetchBannedCardNames(SymfonyStyle $io): array
    {
        $response = $this->httpClient->request('GET', self::SOURCE_URL);
        $html = $response->getContent();

        // Find the Expanded section — look for <h2> containing "Expanded"
        $expandedPos = stripos($html, '>Expanded<');
        if (false === $expandedPos) {
            // Try alternative patterns
            $expandedPos = stripos($html, '>Expanded Format<');
        }

        if (false === $expandedPos) {
            $io->warning('Could not locate the "Expanded" section in the page.');

            return [];
        }

        // Extract the first <ul> after the Expanded heading
        $afterExpanded = substr($html, $expandedPos);
        if (!preg_match('/<ul\b[^>]*>(.*?)<\/ul>/si', $afterExpanded, $ulMatch)) {
            $io->warning('Could not find a <ul> list in the Expanded section.');

            return [];
        }

        $ulHtml = $ulMatch[1];

        // Extract card names from <li> elements — name is typically in an <a> tag
        $names = [];
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/si', $ulHtml, $liMatches)) {
            foreach ($liMatches[1] as $liContent) {
                $name = $this->extractCardName($liContent);
                if (null !== $name) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * Extracts the card name from a <li> inner HTML.
     * Card names are typically in <a> tags, or plain text before parentheses.
     */
    private function extractCardName(string $liHtml): ?string
    {
        // Try <a> tag first
        if (preg_match('/<a[^>]*>(.*?)<\/a>/si', $liHtml, $match)) {
            $name = html_entity_decode(strip_tags($match[1]), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            $name = trim($name);

            return '' !== $name ? $name : null;
        }

        // Fallback: plain text before first parenthesis
        $text = strip_tags($liHtml);
        $parenPos = strpos($text, '(');
        if (false !== $parenPos) {
            $text = substr($text, 0, $parenPos);
        }

        $text = html_entity_decode(trim($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return '' !== $text ? $text : null;
    }
}
