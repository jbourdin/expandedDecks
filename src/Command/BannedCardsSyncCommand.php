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
 * Each banned card entry on pokemon.com lists specific printings (set name + card number).
 * The command parses these, maps set names to PTCG set codes, and stores one row per
 * banned printing in the banned_card table.
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

    /**
     * Maps official Pokemon set names (as they appear on pokemon.com) to PTCG set codes
     * used in deck list exports and by TCGdex.
     *
     * @var array<string, string>
     */
    private const array SET_NAME_TO_CODE = [
        // Black & White era
        'Black & White' => 'BLW',
        'Black & White—Noble Victories' => 'NVI',
        'Black & White—Next Destinies' => 'NXD',
        'Black & White—Dark Explorers' => 'DEX',
        'Black & White—Dragons Exalted' => 'DRX',
        'Black & White—Boundaries Crossed' => 'BCR',
        'Black & White—Plasma Storm' => 'PLS',
        'Black & White—Plasma Freeze' => 'PLF',
        'Black & White—Plasma Blast' => 'PLB',
        'Black & White—Legendary Treasures' => 'LTR',
        // XY era
        'XY' => 'XY',
        'XY—Flashfire' => 'FLF',
        'XY—Furious Fists' => 'FFI',
        'XY—Phantom Forces' => 'PHF',
        'XY—Primal Clash' => 'PRC',
        'XY—Roaring Skies' => 'ROS',
        'XY—Ancient Origins' => 'AOR',
        'XY—BREAKthrough' => 'BKT',
        'XY—BREAKpoint' => 'BKP',
        'XY—Fates Collide' => 'FCO',
        'XY—Steam Siege' => 'STS',
        'XY—Evolutions' => 'EVO',
        'Generations' => 'GEN',
        // Sun & Moon era
        'Sun & Moon' => 'SUM',
        'Sun & Moon—Guardians Rising' => 'GRI',
        'Sun & Moon—Burning Shadows' => 'BUS',
        'Sun & Moon—Crimson Invasion' => 'CIN',
        'Sun & Moon—Ultra Prism' => 'UPR',
        'Sun & Moon—Forbidden Light' => 'FLI',
        'Sun & Moon—Celestial Storm' => 'CES',
        'Sun & Moon—Lost Thunder' => 'LOT',
        'Sun & Moon—Team Up' => 'TEU',
        'Sun & Moon—Unbroken Bonds' => 'UNB',
        'Sun & Moon—Unified Minds' => 'UNM',
        'Sun & Moon—Cosmic Eclipse' => 'CEC',
        'Shining Legends' => 'SLG',
        'Hidden Fates' => 'HIF',
        // Sword & Shield era
        'Sword & Shield' => 'SSH',
        'Sword & Shield—Rebel Clash' => 'RCL',
        'Sword & Shield—Darkness Ablaze' => 'DAA',
        'Sword & Shield—Vivid Voltage' => 'VIV',
        'Sword & Shield—Battle Styles' => 'BST',
        'Sword & Shield—Chilling Reign' => 'CRE',
        'Sword & Shield—Evolving Skies' => 'EVS',
        'Sword & Shield—Fusion Strike' => 'FST',
        'Sword & Shield—Brilliant Stars' => 'BRS',
        'Sword & Shield—Astral Radiance' => 'ASR',
        'Sword & Shield—Lost Origin' => 'LOR',
        'Sword & Shield—Silver Tempest' => 'SIT',
        'Sword & Shield—Crown Zenith' => 'CRZ',
        'Sword & Shield—Shining Fates' => 'SHF',
        'Celebrations' => 'CEL',
        // Promos
        'Black Star Promo' => 'PR-SM',
        'Sword & Shield Promo' => 'PR-SW',
        'Sun & Moon Promo' => 'PR-SM',
        'XY Promo' => 'PR-XY',
        'BW Promo' => 'PR-BLW',
        // Scarlet & Violet era
        'Scarlet & Violet' => 'SVI',
        'Scarlet & Violet—Paldea Evolved' => 'PAL',
        'Scarlet & Violet—Obsidian Flames' => 'OBF',
        'Scarlet & Violet—151' => 'MEW',
        'Scarlet & Violet—Paradox Rift' => 'PAR',
        'Scarlet & Violet—Paldean Fates' => 'PAF',
        'Scarlet & Violet—Temporal Forces' => 'TEF',
        'Scarlet & Violet—Twilight Masquerade' => 'TWM',
        'Scarlet & Violet—Shrouded Fable' => 'SFA',
        'Scarlet & Violet—Stellar Crown' => 'SCR',
        'Scarlet & Violet—Surging Sparks' => 'SSP',
        'Scarlet & Violet—Prismatic Evolutions' => 'PRE',
    ];

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

        $entries = $this->fetchBannedCardEntries($io);

        if ([] === $entries) {
            $io->error('No banned cards found — the page structure may have changed.');

            return Command::FAILURE;
        }

        $io->text(\sprintf('Found %d banned card printing(s) on the page.', \count($entries)));

        $added = 0;
        $unchanged = 0;

        /** @var array<string, true> $syncedKeys tracks setCode|cardNumber pairs seen in this sync */
        $syncedKeys = [];

        foreach ($entries as $entry) {
            $key = $entry['setCode'].'|'.$entry['cardNumber'];
            $syncedKeys[$key] = true;

            $existing = $this->bannedCardRepo->findOneBySetCodeAndNumber($entry['setCode'], $entry['cardNumber']);

            if (null !== $existing) {
                ++$unchanged;
                continue;
            }

            $card = new BannedCard();
            $card->setCardName($entry['cardName']);
            $card->setSetCode($entry['setCode']);
            $card->setCardNumber($entry['cardNumber']);
            $card->setSourceUrl(self::SOURCE_URL);

            $this->entityManager->persist($card);
            ++$added;
        }

        // Remove cards no longer on the banned list
        $existingCards = $this->bannedCardRepo->findAll();
        $removed = 0;

        foreach ($existingCards as $existing) {
            $key = $existing->getSetCode().'|'.$existing->getCardNumber();
            if (!isset($syncedKeys[$key])) {
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
     * @return list<array{cardName: string, setCode: string, cardNumber: string}>
     */
    private function fetchBannedCardEntries(SymfonyStyle $io): array
    {
        $response = $this->httpClient->request('GET', self::SOURCE_URL);
        $html = $response->getContent();

        // Find the Expanded section — look for <h2> containing "Expanded"
        $expandedPos = stripos($html, '>Expanded<');
        if (false === $expandedPos) {
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

        // Extract entries from <li> elements
        $entries = [];
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/si', $ulHtml, $liMatches)) {
            foreach ($liMatches[1] as $liContent) {
                $parsed = $this->parseBannedEntry($liContent, $io);
                foreach ($parsed as $entry) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * Parses a single <li> element containing a banned card with its printings.
     *
     * Format: "CardName (SetName, 67/101; SetName, 110/108)"
     * or:     "CardName (SetName, 98/122, 98a/122, and 98b/122)"
     *
     * @return list<array{cardName: string, setCode: string, cardNumber: string}>
     */
    private function parseBannedEntry(string $liHtml, SymfonyStyle $io): array
    {
        // Extract card name from <a> tag or plain text
        $cardName = $this->extractCardName($liHtml);

        if (null === $cardName) {
            return [];
        }

        // Get the full text content (strip HTML tags)
        $text = html_entity_decode(strip_tags($liHtml), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        // Extract everything inside the parentheses
        if (!preg_match('/\((.+)\)\s*$/', $text, $parenMatch)) {
            $io->warning(\sprintf('No set info found for banned card "%s".', $cardName));

            return [];
        }

        $printingsText = $parenMatch[1];

        // Split by semicolons to get set groups (each semicolon separates a different set)
        $setGroups = preg_split('/\s*;\s*/', $printingsText);

        if (false === $setGroups) {
            return [];
        }

        $entries = [];

        foreach ($setGroups as $setGroup) {
            $parsed = $this->parseSetGroup(trim($setGroup), $cardName, $io);
            foreach ($parsed as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Parses a set group like "Black & White—Noble Victories, 67/101"
     * or "XY—BREAKpoint, 98/122, 98a/122, and 98b/122".
     *
     * @return list<array{cardName: string, setCode: string, cardNumber: string}>
     */
    private function parseSetGroup(string $group, string $cardName, SymfonyStyle $io): array
    {
        // Split on the first comma to separate set name from card numbers
        $commaPos = strpos($group, ',');

        if (false === $commaPos) {
            $io->warning(\sprintf('Could not parse set group "%s" for card "%s".', $group, $cardName));

            return [];
        }

        $setName = trim(substr($group, 0, $commaPos));
        $numbersText = substr($group, $commaPos + 1);

        // Resolve set code
        $setCode = $this->resolveSetCode($setName);

        if (null === $setCode) {
            $io->warning(\sprintf('Unknown set "%s" for card "%s". Add it to SET_NAME_TO_CODE.', $setName, $cardName));

            return [];
        }

        // Extract card numbers — they look like "67/101", "98a/122", "TG02/TG30", "SWSH022"
        // Split by comma and "and" to handle "98/122, 98a/122, and 98b/122"
        $numberParts = preg_split('/\s*(?:,|and)\s+/', $numbersText);

        if (false === $numberParts) {
            return [];
        }

        $entries = [];

        foreach ($numberParts as $part) {
            $part = trim($part);

            if ('' === $part) {
                continue;
            }

            // Card number is the part before the slash (e.g., "67" from "67/101")
            // Some have no slash (e.g., "SWSH022") or special formats (e.g., "SM85")
            $cardNumber = $part;
            if (str_contains($part, '/')) {
                $slashParts = explode('/', $part);
                $cardNumber = $slashParts[0];
            }

            $entries[] = [
                'cardName' => $cardName,
                'setCode' => $setCode,
                'cardNumber' => $cardNumber,
            ];
        }

        return $entries;
    }

    /**
     * Extracts the card name from a <li> inner HTML.
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

    /**
     * Resolves a full set name to a PTCG set code.
     * Tries exact match first, then normalized match (dash variants).
     */
    private function resolveSetCode(string $setName): ?string
    {
        if (isset(self::SET_NAME_TO_CODE[$setName])) {
            return self::SET_NAME_TO_CODE[$setName];
        }

        // Try replacing different dash variants (em-dash, en-dash, regular dash)
        $normalized = str_replace(['—', '–', '−'], '—', $setName);

        if (isset(self::SET_NAME_TO_CODE[$normalized])) {
            return self::SET_NAME_TO_CODE[$normalized];
        }

        return null;
    }
}
