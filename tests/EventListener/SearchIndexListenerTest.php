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

namespace App\Tests\EventListener;

use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\User;
use App\EventListener\SearchIndexListener;
use App\Repository\PageRepository;
use App\Service\Search\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
 */
class SearchIndexListenerTest extends TestCase
{
    public function testPostPersistIndexesArchetype(): void
    {
        $archetype = new Archetype();

        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::once())->method('indexArchetype')->with($archetype);

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postPersist($this->createPostPersistArgs($archetype));
    }

    public function testPostUpdateIndexesPage(): void
    {
        $page = new Page();

        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::once())->method('indexPage')->with($page);

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postUpdate($this->createPostUpdateArgs($page));
    }

    public function testPostPersistIndexesEvent(): void
    {
        $event = new Event();

        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::once())->method('indexEvent')->with($event);

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postPersist($this->createPostPersistArgs($event));
    }

    public function testPostPersistIndexesOwnedDeck(): void
    {
        $deck = new Deck();
        $deck->setOwner(new User());

        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::once())->method('indexDeck')->with($deck);

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postPersist($this->createPostPersistArgs($deck));
    }

    public function testPostPersistIndexesVariantDeck(): void
    {
        $deck = new Deck();
        // No owner = variant

        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::once())->method('indexVariant')->with($deck);

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postPersist($this->createPostPersistArgs($deck));
    }

    public function testPostPersistIndexesArchetypeTranslation(): void
    {
        $archetype = new Archetype();
        $translation = new ArchetypeTranslation();
        $translation->setArchetype($archetype);

        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::once())->method('indexArchetype')->with($archetype);

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postPersist($this->createPostPersistArgs($translation));
    }

    public function testPostPersistIndexesPageTranslation(): void
    {
        $page = new Page();
        $translation = new PageTranslation();
        $translation->setPage($page);

        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::once())->method('indexPage')->with($page);

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postPersist($this->createPostPersistArgs($translation));
    }

    public function testPostRemoveRemovesArchetype(): void
    {
        $archetype = new Archetype();

        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::once())->method('removeArchetype')->with($archetype);

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postRemove($this->createPostRemoveArgs($archetype));
    }

    public function testPostRemoveRemovesVariantDeck(): void
    {
        $deck = new Deck();

        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::once())->method('removeVariant')->with($deck);

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postRemove($this->createPostRemoveArgs($deck));
    }

    public function testPostRemoveRemovesOwnedDeck(): void
    {
        $deck = new Deck();
        $deck->setOwner(new User());

        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::once())->method('removeDeck')->with($deck);

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postRemove($this->createPostRemoveArgs($deck));
    }

    public function testIndexerFailureIsLoggedNotThrown(): void
    {
        $archetype = new Archetype();

        $indexer = $this->createStub(SearchIndexer::class);
        $indexer->method('indexArchetype')->willThrowException(new \RuntimeException('Connection refused'));

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());

        // Should not throw
        $listener->postPersist($this->createPostPersistArgs($archetype));
        self::assertTrue(true);
    }

    public function testUnrelatedEntityIsIgnored(): void
    {
        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->expects(self::never())->method(self::anything());

        $listener = new SearchIndexListener($indexer, $this->createStub(PageRepository::class), new NullLogger());
        $listener->postPersist($this->createPostPersistArgs(new \stdClass()));
    }

    private function createPostPersistArgs(object $entity): PostPersistEventArgs
    {
        return new PostPersistEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }

    private function createPostUpdateArgs(object $entity): PostUpdateEventArgs
    {
        return new PostUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }

    private function createPostRemoveArgs(object $entity): PostRemoveEventArgs
    {
        return new PostRemoveEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }
}
