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

namespace App\Service\Search;

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\Page;
use App\Enum\EventVisibility;
use App\Repository\ArchetypeRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\PageRepository;
use Meilisearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Manages MeiliSearch indexes: configures settings, indexes documents,
 * and keeps the search index in sync with the database.
 *
 * Each content type has its own index with locale-aware documents.
 * Translatable entities produce one document per locale.
 *
 * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
 */
class SearchIndexer
{
    public const string INDEX_ARCHETYPES = 'archetypes';
    public const string INDEX_VARIANTS = 'variants';
    public const string INDEX_PAGES = 'pages';
    public const string INDEX_EVENTS = 'events';
    public const string INDEX_DECKS = 'decks';

    private const array SUPPORTED_LOCALES = ['en', 'fr'];

    private readonly Client $client;

    public function __construct(
        #[Autowire(env: 'MEILI_URL')]
        string $meilisearchUrl,
        #[Autowire(env: 'MEILI_MASTER_KEY')]
        string $meiliMasterKey,
        private readonly ArchetypeRepository $archetypeRepository,
        private readonly PageRepository $pageRepository,
        private readonly EventRepository $eventRepository,
        private readonly DeckRepository $deckRepository,
        private readonly LoggerInterface $logger,
    ) {
        $this->client = new Client($meilisearchUrl, $meiliMasterKey);
    }

    /**
     * Delete all indexes and recreate them with correct settings and full data.
     *
     * @return array{archetypes: int, variants: int, pages: int, events: int, decks: int} Document counts per index
     */
    public function reindexAll(): array
    {
        $counts = [];

        $counts[self::INDEX_ARCHETYPES] = $this->reindexArchetypes();
        $counts[self::INDEX_VARIANTS] = $this->reindexVariants();
        $counts[self::INDEX_PAGES] = $this->reindexPages();
        $counts[self::INDEX_EVENTS] = $this->reindexEvents();
        $counts[self::INDEX_DECKS] = $this->reindexDecks();

        return $counts;
    }

    public function indexArchetype(Archetype $archetype): void
    {
        if (!$archetype->isPublished() || $archetype->getDeletedAt() instanceof \DateTimeImmutable) {
            $this->removeArchetype($archetype);

            return;
        }

        $documents = $this->mapArchetype($archetype);
        $this->client->index(self::INDEX_ARCHETYPES)->addDocuments($documents);
    }

    public function removeArchetype(Archetype $archetype): void
    {
        $ids = [];
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $ids[] = $archetype->getId().'_'.$locale;
        }
        $this->deleteDocumentsSafe(self::INDEX_ARCHETYPES, $ids);
    }

    /**
     * Index a single archetype variant (deck with no owner).
     * Includes all card names from the current version for card-based search.
     */
    public function indexVariant(Deck $variant): void
    {
        if (null !== $variant->getOwner() || $variant->getDeletedAt() instanceof \DateTimeImmutable) {
            $this->removeVariant($variant);

            return;
        }

        $archetype = $variant->getArchetype();
        if (null === $archetype || !$archetype->isPublished()) {
            $this->removeVariant($variant);

            return;
        }

        $document = $this->mapVariant($variant);
        $this->client->index(self::INDEX_VARIANTS)->addDocuments([$document]);
    }

    public function removeVariant(Deck $variant): void
    {
        $this->deleteDocumentsSafe(self::INDEX_VARIANTS, [$variant->getShortTag()]);
    }

    public function indexPage(Page $page): void
    {
        if (!$page->isPublished() || $page->isNoIndex() || $page->getDeletedAt() instanceof \DateTimeImmutable) {
            $this->removePage($page);

            return;
        }

        $documents = $this->mapPage($page);
        $this->client->index(self::INDEX_PAGES)->addDocuments($documents);
    }

    public function removePage(Page $page): void
    {
        $ids = [];
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $ids[] = $page->getId().'_'.$locale;
        }
        $this->deleteDocumentsSafe(self::INDEX_PAGES, $ids);
    }

    public function indexEvent(Event $event): void
    {
        if (EventVisibility::Public !== $event->getVisibility() || $event->getDeletedAt() instanceof \DateTimeImmutable) {
            $this->removeEvent($event);

            return;
        }

        $document = $this->mapEvent($event);
        $this->client->index(self::INDEX_EVENTS)->addDocuments([$document]);
    }

    public function removeEvent(Event $event): void
    {
        $this->deleteDocumentsSafe(self::INDEX_EVENTS, [(string) $event->getId()]);
    }

    public function indexDeck(Deck $deck): void
    {
        if (!$deck->isPublic() || null === $deck->getOwner() || $deck->getDeletedAt() instanceof \DateTimeImmutable) {
            $this->removeDeck($deck);

            return;
        }

        $document = $this->mapDeck($deck);
        $this->client->index(self::INDEX_DECKS)->addDocuments([$document]);
    }

    public function removeDeck(Deck $deck): void
    {
        $this->deleteDocumentsSafe(self::INDEX_DECKS, [$deck->getShortTag()]);
    }

    /**
     * Check if MeiliSearch is reachable and healthy.
     */
    public function isHealthy(): bool
    {
        try {
            $health = $this->client->health();

            return 'available' === ($health['status'] ?? null);
        } catch (\Throwable) {
            return false;
        }
    }

    private function reindexArchetypes(): int
    {
        $index = $this->client->index(self::INDEX_ARCHETYPES);

        try {
            $this->client->deleteIndex(self::INDEX_ARCHETYPES);
        } catch (\Throwable) {
            // Index may not exist yet
        }

        $this->client->createIndex(self::INDEX_ARCHETYPES, ['primaryKey' => 'id']);
        $index->updateSettings([
            'searchableAttributes' => ['name', 'description', 'metaDescription'],
            'filterableAttributes' => ['locale', 'entityId'],
            'displayedAttributes' => ['id', 'entityId', 'locale', 'name', 'slug', 'type'],
        ]);

        $archetypes = $this->archetypeRepository->findPublishedForSearch();
        $documents = [];

        foreach ($archetypes as $archetype) {
            foreach ($this->mapArchetype($archetype) as $document) {
                $documents[] = $document;
            }
        }

        if ([] !== $documents) {
            $index->addDocuments($documents);
        }

        $this->logger->info('Indexed {count} archetype documents.', ['count' => \count($documents)]);

        return \count($documents);
    }

    private function reindexVariants(): int
    {
        $index = $this->client->index(self::INDEX_VARIANTS);

        try {
            $this->client->deleteIndex(self::INDEX_VARIANTS);
        } catch (\Throwable) {
            // Index may not exist yet
        }

        $this->client->createIndex(self::INDEX_VARIANTS, ['primaryKey' => 'id']);
        $index->updateSettings([
            'searchableAttributes' => ['name', 'archetypeName', 'cardNames'],
            'filterableAttributes' => ['archetypeSlug'],
            'displayedAttributes' => ['id', 'name', 'shortTag', 'archetypeName', 'archetypeSlug', 'type'],
        ]);

        $variants = $this->deckRepository->findVariantsForSearch();
        $documents = [];

        foreach ($variants as $variant) {
            $documents[] = $this->mapVariant($variant);
        }

        if ([] !== $documents) {
            $index->addDocuments($documents);
        }

        $this->logger->info('Indexed {count} variant documents.', ['count' => \count($documents)]);

        return \count($documents);
    }

    private function reindexPages(): int
    {
        $index = $this->client->index(self::INDEX_PAGES);

        try {
            $this->client->deleteIndex(self::INDEX_PAGES);
        } catch (\Throwable) {
            // Index may not exist yet
        }

        $this->client->createIndex(self::INDEX_PAGES, ['primaryKey' => 'id']);
        $index->updateSettings([
            'searchableAttributes' => ['title', 'content'],
            'filterableAttributes' => ['locale', 'entityId', 'channelCode'],
            'displayedAttributes' => ['id', 'entityId', 'locale', 'title', 'slug', 'type', 'channelCode'],
        ]);

        $pages = $this->pageRepository->findPublishedForSearch();
        $documents = [];

        foreach ($pages as $page) {
            foreach ($this->mapPage($page) as $document) {
                $documents[] = $document;
            }
        }

        if ([] !== $documents) {
            $index->addDocuments($documents);
        }

        $this->logger->info('Indexed {count} page documents.', ['count' => \count($documents)]);

        return \count($documents);
    }

    private function reindexEvents(): int
    {
        $index = $this->client->index(self::INDEX_EVENTS);

        try {
            $this->client->deleteIndex(self::INDEX_EVENTS);
        } catch (\Throwable) {
            // Index may not exist yet
        }

        $this->client->createIndex(self::INDEX_EVENTS, ['primaryKey' => 'id']);
        $index->updateSettings([
            'searchableAttributes' => ['name', 'description', 'location'],
            'filterableAttributes' => ['format'],
            'sortableAttributes' => ['date'],
            'displayedAttributes' => ['id', 'name', 'date', 'location', 'type'],
        ]);

        $events = $this->eventRepository->findPublicForSearch();
        $documents = [];

        foreach ($events as $event) {
            $documents[] = $this->mapEvent($event);
        }

        if ([] !== $documents) {
            $index->addDocuments($documents);
        }

        $this->logger->info('Indexed {count} event documents.', ['count' => \count($documents)]);

        return \count($documents);
    }

    private function reindexDecks(): int
    {
        $index = $this->client->index(self::INDEX_DECKS);

        try {
            $this->client->deleteIndex(self::INDEX_DECKS);
        } catch (\Throwable) {
            // Index may not exist yet
        }

        $this->client->createIndex(self::INDEX_DECKS, ['primaryKey' => 'id']);
        $index->updateSettings([
            'searchableAttributes' => ['name', 'shortTag', 'archetypeName', 'ownerName'],
            'filterableAttributes' => ['format', 'archetypeName'],
            'displayedAttributes' => ['id', 'shortTag', 'name', 'archetypeName', 'ownerName', 'type'],
        ]);

        $decks = $this->deckRepository->findPublicForSearch();
        $documents = [];

        foreach ($decks as $deck) {
            $documents[] = $this->mapDeck($deck);
        }

        if ([] !== $documents) {
            $index->addDocuments($documents);
        }

        $this->logger->info('Indexed {count} deck documents.', ['count' => \count($documents)]);

        return \count($documents);
    }

    /**
     * Map an archetype to MeiliSearch documents (one per locale).
     *
     * @return list<array<string, mixed>>
     */
    private function mapArchetype(Archetype $archetype): array
    {
        $documents = [];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $documents[] = [
                'id' => $archetype->getId().'_'.$locale,
                'entityId' => $archetype->getId(),
                'locale' => $locale,
                'type' => 'archetype',
                'name' => $archetype->getLocalizedName($locale),
                'slug' => $archetype->getSlug(),
                'description' => $this->stripMarkdown($archetype->getLocalizedDescription($locale) ?? ''),
                'metaDescription' => $archetype->getLocalizedMetaDescription($locale) ?? '',
            ];
        }

        return $documents;
    }

    /**
     * Map a page to MeiliSearch documents (one per locale).
     *
     * @return list<array<string, mixed>>
     */
    private function mapPage(Page $page): array
    {
        $documents = [];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $translation = $page->getTranslation($locale);
            if (null === $translation) {
                continue;
            }

            $documents[] = [
                'id' => $page->getId().'_'.$locale,
                'entityId' => $page->getId(),
                'locale' => $locale,
                'type' => 'page',
                'title' => $translation->getTitle(),
                'slug' => $page->getSlug(),
                'content' => $this->stripMarkdown($translation->getContent()),
                'channelCode' => $page->getChannel()?->getCode(),
            ];
        }

        return $documents;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEvent(Event $event): array
    {
        return [
            'id' => (string) $event->getId(),
            'type' => 'event',
            'name' => $event->getName(),
            'description' => $this->stripMarkdown($event->getDescription() ?? ''),
            'location' => $event->getLocation() ?? '',
            'date' => $event->getDate()->format('Y-m-d'),
            'format' => $event->getFormat(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDeck(Deck $deck): array
    {
        return [
            'id' => $deck->getShortTag(),
            'type' => 'deck',
            'name' => $deck->getName(),
            'shortTag' => $deck->getShortTag(),
            'archetypeName' => $deck->getArchetype()?->getName() ?? '',
            'ownerName' => $deck->getOwner()?->getScreenName() ?? '',
            'format' => $deck->getFormat(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapVariant(Deck $variant): array
    {
        $cardNames = [];
        $currentVersion = $variant->getCurrentVersion();
        if (null !== $currentVersion) {
            foreach ($currentVersion->getCards() as $card) {
                $cardNames[] = $card->getCardName();
            }
        }

        return [
            'id' => $variant->getShortTag(),
            'type' => 'variant',
            'name' => $variant->getName(),
            'shortTag' => $variant->getShortTag(),
            'archetypeName' => $variant->getArchetype()?->getName() ?? '',
            'archetypeSlug' => $variant->getArchetype()?->getSlug() ?? '',
            'cardNames' => implode(' ', array_unique($cardNames)),
        ];
    }

    /**
     * Strip Markdown formatting for plain-text indexing.
     */
    private function stripMarkdown(string $markdown): string
    {
        // Remove archetype/deck/card custom tags: [[archetype:slug]], [[deck:TAG]], [[card:...]]
        $text = (string) preg_replace('/\[\[(archetype|deck|card):[^\]]+\]\]/', '', $markdown);

        // Remove images ![alt](url) — must run before link and bold/italic removal
        $text = (string) preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', $text);

        // Remove Markdown links [text](url)
        $text = (string) preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);

        // Remove headings (#, ##, etc.)
        $text = (string) preg_replace('/^#{1,6}\s+/m', '', $text);

        // Remove bold/italic markers
        $text = str_replace(['**', '__', '*', '_'], '', $text);

        // Collapse whitespace
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * @param list<string> $documentIds
     */
    private function deleteDocumentsSafe(string $indexName, array $documentIds): void
    {
        try {
            $this->client->index($indexName)->deleteDocuments($documentIds);
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to delete documents from {index}: {error}', [
                'index' => $indexName,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
