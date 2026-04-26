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

namespace App\Tests\Service\Search;

use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\User;
use App\Enum\EventVisibility;
use App\Repository\ArchetypeRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\PageRepository;
use App\Service\Search\SearchIndexer;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests SearchIndexer orchestration with a mocked MeiliSearch client.
 *
 * Verifies that the indexer creates indexes with correct settings,
 * maps entities to documents, and calls addDocuments/deleteDocuments
 * at the right times.
 *
 * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
 */
class SearchIndexerIntegrationTest extends TestCase
{
    // ── reindexAll ──────────────────────────────────────────────────────

    public function testReindexAllCreatesAllFiveIndexes(): void
    {
        $createdIndexes = [];

        $client = $this->createMock(Client::class);
        $client->method('deleteIndex'); // swallow
        $client->expects(self::exactly(5))
            ->method('createIndex')
            ->willReturnCallback(static function (string $uid) use (&$createdIndexes): array {
                $createdIndexes[] = $uid;

                return ['taskUid' => 1];
            });
        $client->method('index')->willReturn($this->createStubIndex());

        $indexer = $this->createIndexer($client);
        $counts = $indexer->reindexAll();

        self::assertSame(['archetypes', 'variants', 'pages', 'events', 'decks'], $createdIndexes);
        self::assertArrayHasKey('archetypes', $counts);
        self::assertArrayHasKey('variants', $counts);
        self::assertArrayHasKey('pages', $counts);
        self::assertArrayHasKey('events', $counts);
        self::assertArrayHasKey('decks', $counts);
    }

    public function testReindexAllIndexesArchetypeDocuments(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Regidrago');
        $this->setProperty($archetype, 'slug', 'regidrago');
        $this->setProperty($archetype, 'isPublished', true);

        $translation = new ArchetypeTranslation();
        $translation->setLocale('en');
        $translation->setName('Regidrago');
        $translation->setArchetype($archetype);
        $archetype->addTranslation($translation);

        $addedDocuments = [];
        $index = $this->createStub(Indexes::class);
        $index->method('updateSettings')->willReturn(['taskUid' => 1]);
        $index->method('addDocuments')
            ->willReturnCallback(static function (array $documents) use (&$addedDocuments): array {
                $addedDocuments = array_merge($addedDocuments, $documents);

                return ['taskUid' => 1];
            });
        $index->method('deleteDocuments')->willReturn(['taskUid' => 1]);

        $client = $this->createStub(Client::class);
        $client->method('deleteIndex')->willReturn(['taskUid' => 1]);
        $client->method('createIndex')->willReturn(['taskUid' => 1]);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client, archetypes: [$archetype]);
        $indexer->reindexAll();

        // At least one archetype document should have been added
        $archetypeDocs = array_filter($addedDocuments, static fn (array $document): bool => 'archetype' === ($document['type'] ?? null));
        self::assertNotEmpty($archetypeDocs);
    }

    // ── indexArchetype ──────────────────────────────────────────────────

    public function testIndexArchetypeAddsDocumentsWhenPublished(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Regidrago');
        $this->setProperty($archetype, 'id', 42);
        $this->setProperty($archetype, 'slug', 'regidrago');
        $this->setProperty($archetype, 'isPublished', true);

        $translation = new ArchetypeTranslation();
        $translation->setLocale('en');
        $translation->setName('Regidrago');
        $translation->setArchetype($archetype);
        $archetype->addTranslation($translation);

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexArchetype($archetype);
    }

    public function testIndexArchetypeRemovesDocumentsWhenUnpublished(): void
    {
        $archetype = new Archetype();
        $this->setProperty($archetype, 'id', 42);
        $this->setProperty($archetype, 'isPublished', false);

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('deleteDocuments');
        $index->expects(self::never())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexArchetype($archetype);
    }

    public function testIndexArchetypeRemovesDocumentsWhenDeleted(): void
    {
        $archetype = new Archetype();
        $this->setProperty($archetype, 'id', 42);
        $this->setProperty($archetype, 'isPublished', true);
        $archetype->setDeletedAt(new \DateTimeImmutable());

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('deleteDocuments');
        $index->expects(self::never())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexArchetype($archetype);
    }

    // ── indexPage ────────────────────────────────────────────────────────

    public function testIndexPageAddsDocumentsWhenPublished(): void
    {
        $page = new Page();
        $page->setSlug('welcome');
        $this->setProperty($page, 'id', 10);
        $this->setProperty($page, 'isPublished', true);

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Welcome');
        $translation->setContent('Content');
        $translation->setPage($page);
        $page->addTranslation($translation);

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexPage($page);
    }

    public function testIndexPageRemovesWhenNoIndex(): void
    {
        $page = new Page();
        $this->setProperty($page, 'id', 10);
        $this->setProperty($page, 'isPublished', true);
        $page->setNoIndex(true);

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('deleteDocuments');
        $index->expects(self::never())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexPage($page);
    }

    // ── indexEvent ───────────────────────────────────────────────────────

    public function testIndexEventAddsDocumentWhenPublic(): void
    {
        $event = new Event();
        $event->setName('League');
        $event->setDate(new \DateTimeImmutable());
        $event->setOrganizer(new User());
        $this->setProperty($event, 'id', 5);

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexEvent($event);
    }

    public function testIndexEventRemovesWhenPrivate(): void
    {
        $event = new Event();
        $event->setVisibility(EventVisibility::Private);
        $this->setProperty($event, 'id', 5);

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('deleteDocuments');
        $index->expects(self::never())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexEvent($event);
    }

    // ── indexDeck ────────────────────────────────────────────────────────

    public function testIndexDeckAddsDocumentWhenPublicAndOwned(): void
    {
        $deck = new Deck();
        $deck->setName('My Deck');
        $deck->setOwner(new User());
        $this->setProperty($deck, 'public', true);

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexDeck($deck);
    }

    public function testIndexDeckRemovesWhenPrivate(): void
    {
        $deck = new Deck();
        $deck->setOwner(new User());
        $this->setProperty($deck, 'public', false);

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('deleteDocuments');
        $index->expects(self::never())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexDeck($deck);
    }

    // ── indexVariant ────────────────────────────────────────────────────

    public function testIndexVariantAddsDocumentWhenArchetypePublished(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Regidrago');
        $this->setProperty($archetype, 'slug', 'regidrago');
        $this->setProperty($archetype, 'isPublished', true);

        $variant = new Deck();
        $variant->setName('Turbo Regidrago');
        $variant->setArchetype($archetype);
        // No owner = variant

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexVariant($variant);
    }

    public function testIndexVariantRemovesWhenArchetypeUnpublished(): void
    {
        $archetype = new Archetype();
        $this->setProperty($archetype, 'isPublished', false);

        $variant = new Deck();
        $variant->setArchetype($archetype);

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('deleteDocuments');
        $index->expects(self::never())->method('addDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexVariant($variant);
    }

    public function testIndexVariantRemovesWhenHasOwner(): void
    {
        $variant = new Deck();
        $variant->setOwner(new User());

        $index = $this->createMock(Indexes::class);
        $index->expects(self::once())->method('deleteDocuments');

        $client = $this->createStub(Client::class);
        $client->method('index')->willReturn($index);

        $indexer = $this->createIndexer($client);
        $indexer->indexVariant($variant);
    }

    // ── isHealthy ───────────────────────────────────────────────────────

    public function testIsHealthyReturnsTrueWhenAvailable(): void
    {
        $client = $this->createStub(Client::class);
        $client->method('health')->willReturn(['status' => 'available']);

        $indexer = $this->createIndexer($client);

        self::assertTrue($indexer->isHealthy());
    }

    public function testIsHealthyReturnsFalseWhenUnreachable(): void
    {
        $client = $this->createStub(Client::class);
        $client->method('health')->willThrowException(new \RuntimeException('Connection refused'));

        $indexer = $this->createIndexer($client);

        self::assertFalse($indexer->isHealthy());
    }

    public function testIsHealthyReturnsFalseWhenStatusNotAvailable(): void
    {
        $client = $this->createStub(Client::class);
        $client->method('health')->willReturn(['status' => 'maintenance']);

        $indexer = $this->createIndexer($client);

        self::assertFalse($indexer->isHealthy());
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * @param list<Archetype> $archetypes
     * @param list<Page>      $pages
     * @param list<Event>     $events
     * @param list<Deck>      $decks
     * @param list<Deck>      $variants
     */
    private function createIndexer(
        Client $client,
        array $archetypes = [],
        array $pages = [],
        array $events = [],
        array $decks = [],
        array $variants = [],
    ): SearchIndexer {
        $archetypeRepository = $this->createStub(ArchetypeRepository::class);
        $archetypeRepository->method('findPublishedForSearch')->willReturn($archetypes);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findPublishedForSearch')->willReturn($pages);

        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('findPublicForSearch')->willReturn($events);

        $deckRepository = $this->createStub(DeckRepository::class);
        $deckRepository->method('findPublicForSearch')->willReturn($decks);
        $deckRepository->method('findVariantsForSearch')->willReturn($variants);

        $indexer = (new \ReflectionClass(SearchIndexer::class))->newInstanceWithoutConstructor();

        $this->setProperty($indexer, 'client', $client);
        $this->setProperty($indexer, 'archetypeRepository', $archetypeRepository);
        $this->setProperty($indexer, 'pageRepository', $pageRepository);
        $this->setProperty($indexer, 'eventRepository', $eventRepository);
        $this->setProperty($indexer, 'deckRepository', $deckRepository);
        $this->setProperty($indexer, 'logger', new NullLogger());

        return $indexer;
    }

    private function createStubIndex(): Indexes
    {
        $index = $this->createStub(Indexes::class);
        $index->method('updateSettings')->willReturn(['taskUid' => 1]);
        $index->method('addDocuments')->willReturn(['taskUid' => 1]);
        $index->method('deleteDocuments')->willReturn(['taskUid' => 1]);

        return $index;
    }

    private function setProperty(object $entity, string $property, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty($entity, $property);
        $reflectionProperty->setValue($entity, $value);
    }
}
