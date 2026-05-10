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

use App\Constants\ListingIntroPage;
use App\Entity\Channel;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Repository\ChannelRepository;
use App\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Idempotently seeds the editable intro Page entries that back the banned-cards
 * and staple-cards listing pages. Run on cold-start (docker-entrypoint.sh) so a
 * fresh database always renders something under the H1, and re-runnable any
 * time without producing duplicates.
 *
 * Banned cards intros are seeded only on channels with `enableBannedCards`,
 * staple intros only on channels with `enableStaples`.
 */
#[AsCommand(
    name: 'app:listings:seed-intros',
    description: 'Create the banned-cards and staple-cards intro pages on each channel where missing.',
)]
class CreateListingIntroPagesCommand extends Command
{
    /**
     * Default copy used as the initial Markdown body for each intro page.
     * Mirrors the previous static subtitles, so existing channels see no
     * UX regression after the seed.
     *
     * @var array<string, array<string, array{title: string, content: string}>>
     */
    private const array DEFAULT_TRANSLATIONS = [
        ListingIntroPage::BANNED_CARDS_SLUG => [
            'en' => [
                'title' => 'Banned cards',
                'content' => 'Cards currently banned in the Expanded format. Click any card for details and the official announcement.',
            ],
            'fr' => [
                'title' => 'Cartes bannies',
                'content' => 'Cartes actuellement bannies du format Étendu. Cliquez sur une carte pour voir le détail et l\'annonce officielle.',
            ],
        ],
        ListingIntroPage::STAPLE_CARDS_SLUG => [
            'en' => [
                'title' => 'Staple cards',
                'content' => 'Editor-curated cards that anchor most Expanded decks, grouped by card type.',
            ],
            'fr' => [
                'title' => 'Staple cards',
                'content' => 'Cartes incontournables choisies par les éditeurs, triées par type.',
            ],
        ],
    ];

    public function __construct(
        private readonly ChannelRepository $channelRepository,
        private readonly PageRepository $pageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = 0;
        $skipped = 0;

        foreach ($this->channelRepository->findAll() as $channel) {
            if ($channel->getEnableBannedCards()) {
                $this->ensureIntroPage($channel, ListingIntroPage::BANNED_CARDS_SLUG, $created, $skipped);
            }
            if ($channel->getEnableStaples()) {
                $this->ensureIntroPage($channel, ListingIntroPage::STAPLE_CARDS_SLUG, $created, $skipped);
            }
        }

        $this->entityManager->flush();

        $io->success(\sprintf('Listing intro pages: %d created, %d already present.', $created, $skipped));

        return Command::SUCCESS;
    }

    private function ensureIntroPage(Channel $channel, string $slug, int &$created, int &$skipped): void
    {
        if (null !== $this->pageRepository->findBySlug($slug, $channel)) {
            ++$skipped;

            return;
        }

        $page = new Page();
        $page->setSlug($slug);
        $page->setChannel($channel);
        $page->setIsPublished(true);
        $page->setNoIndex(false);

        $defaults = self::DEFAULT_TRANSLATIONS[$slug];
        foreach ($channel->getLocales() as $locale) {
            $entry = $defaults[$locale] ?? $defaults['en'];

            $translation = new PageTranslation();
            $translation->setLocale($locale);
            $translation->setTitle($entry['title']);
            $translation->setContent($entry['content']);
            $page->addTranslation($translation);
        }

        $this->entityManager->persist($page);
        ++$created;
    }
}
