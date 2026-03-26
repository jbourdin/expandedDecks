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

namespace App\Tests\Functional;

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\Page;
use App\Repository\ArchetypeRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests for soft deletion of archetypes, pages, events, and decks.
 *
 * @see docs/features.md F7.6 — Soft-delete archetypes (admin)
 * @see docs/features.md F7.7 — Soft-delete CMS pages (admin)
 * @see docs/features.md F7.8 — Soft-delete events (admin)
 * @see docs/features.md F2.23 — Soft-delete decks (owner)
 */
class SoftDeleteTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    /**
     * Extract CSRF token from a rendered page form.
     */
    private function postDeleteWithCsrf(string $pageUrl, string $deleteUrl, string $csrfSelector): void
    {
        $crawler = $this->client->request('GET', $pageUrl);
        $tokenInput = $crawler->filter($csrfSelector);
        $token = $tokenInput->count() > 0 ? $tokenInput->attr('value') : 'missing';

        $this->client->request('POST', $deleteUrl, ['_token' => $token]);
    }

    // --- Archetype ---

    public function testAdminCanSoftDeleteArchetype(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Archetype $archetype */
        $archetype = $entityManager->getRepository(Archetype::class)->findOneBy(['name' => 'Shadow Rider Calyrex']);
        self::assertNotNull($archetype);
        $archetypeId = $archetype->getId();

        $this->postDeleteWithCsrf(
            '/admin/archetypes/'.$archetypeId,
            '/admin/archetypes/'.$archetypeId.'/delete',
            'form[action*="delete"] input[name="_token"]'
        );

        self::assertResponseRedirects();

        $entityManager->clear();
        /** @var Archetype $deleted */
        $deleted = $entityManager->getRepository(Archetype::class)->find($archetypeId);
        self::assertNotNull($deleted->getDeletedAt(), 'Archetype should have deletedAt set');
    }

    public function testDeletedArchetypeFilteredFromPublicQueries(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Archetype $archetype */
        $archetype = $entityManager->getRepository(Archetype::class)->findOneBy(['name' => 'Shadow Rider Calyrex']);
        self::assertNotNull($archetype);

        $archetype->setDeletedAt(new \DateTimeImmutable());
        $entityManager->flush();

        /** @var ArchetypeRepository $repository */
        $repository = static::getContainer()->get(ArchetypeRepository::class);
        $published = $repository->findPublishedBySlug($archetype->getSlug());
        self::assertNull($published, 'Deleted archetype should not appear in public queries');
    }

    public function testArchetypeDeleteWithInvalidCsrfRedirectsBack(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Archetype $archetype */
        $archetype = $entityManager->getRepository(Archetype::class)->findOneBy(['name' => 'Shadow Rider Calyrex']);
        self::assertNotNull($archetype);

        $this->client->request('POST', '/admin/archetypes/'.$archetype->getId().'/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects();

        $entityManager->clear();
        /** @var Archetype $notDeleted */
        $notDeleted = $entityManager->getRepository(Archetype::class)->find($archetype->getId());
        self::assertNull($notDeleted->getDeletedAt(), 'Archetype should not be deleted with invalid CSRF');
    }

    // --- Page ---

    public function testAdminCanSoftDeletePage(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Page $page */
        $page = $entityManager->getRepository(Page::class)->findOneBy([]);
        self::assertNotNull($page);
        $pageId = $page->getId();

        $this->postDeleteWithCsrf(
            '/admin/pages/'.$pageId,
            '/admin/pages/'.$pageId.'/delete',
            'form[action*="delete"] input[name="_token"]'
        );

        self::assertResponseRedirects();

        $entityManager->clear();
        /** @var Page $deleted */
        $deleted = $entityManager->getRepository(Page::class)->find($pageId);
        self::assertNotNull($deleted->getDeletedAt(), 'Page should have deletedAt set');
    }

    public function testDeletedPageFilteredFromPublicQueries(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Page $page */
        $page = $entityManager->getRepository(Page::class)->findOneBy(['isPublished' => true]);
        self::assertNotNull($page);

        $slug = $page->getSlug();
        $page->setDeletedAt(new \DateTimeImmutable());
        $entityManager->flush();

        /** @var PageRepository $repository */
        $repository = static::getContainer()->get(PageRepository::class);
        $found = $repository->findBySlug($slug, 'en');
        self::assertNull($found, 'Deleted page should not appear in public queries');
    }

    // --- Event ---

    public function testAdminCanSoftDeleteEvent(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Event $event */
        $event = $entityManager->getRepository(Event::class)->findOneBy(['name' => 'Lyon Expanded Cup 2026']);
        self::assertNotNull($event);
        $eventId = $event->getId();

        $this->postDeleteWithCsrf(
            '/event/'.$eventId,
            '/event/'.$eventId.'/delete',
            'form[action*="delete"] input[name="_token"]'
        );

        self::assertResponseRedirects();

        $entityManager->clear();
        /** @var Event $deleted */
        $deleted = $entityManager->getRepository(Event::class)->find($eventId);
        self::assertNotNull($deleted->getDeletedAt(), 'Event should have deletedAt set');
    }

    public function testDeletedEventFilteredFromUpcomingQueries(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Event $event */
        $event = $entityManager->getRepository(Event::class)->findOneBy(['name' => 'Lyon Expanded Cup 2026']);
        self::assertNotNull($event);

        $event->setDeletedAt(new \DateTimeImmutable());
        $entityManager->flush();

        /** @var EventRepository $repository */
        $repository = static::getContainer()->get(EventRepository::class);
        $upcoming = $repository->findUpcoming(100);
        foreach ($upcoming as $upcomingEvent) {
            self::assertNotSame($event->getId(), $upcomingEvent->getId(), 'Deleted event should not appear in upcoming queries');
        }
    }

    public function testNonAdminCannotDeleteEvent(): void
    {
        $this->loginAs('organizer@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Event $event */
        $event = $entityManager->getRepository(Event::class)->findOneBy(['name' => 'Lyon Expanded Cup 2026']);
        self::assertNotNull($event);

        $this->client->request('POST', '/event/'.$event->getId().'/delete', [
            '_token' => 'dummy',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testEventDeleteWithInvalidCsrfRedirectsBack(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Event $event */
        $event = $entityManager->getRepository(Event::class)->findOneBy(['name' => 'Lyon Expanded Cup 2026']);
        self::assertNotNull($event);

        $this->client->request('POST', '/event/'.$event->getId().'/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects();

        $entityManager->clear();
        /** @var Event $notDeleted */
        $notDeleted = $entityManager->getRepository(Event::class)->find($event->getId());
        self::assertNull($notDeleted->getDeletedAt(), 'Event should not be deleted with invalid CSRF');
    }

    // --- Deck ---

    public function testOwnerCanSoftDeleteDeck(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Shadow Rider Calyrex']);
        self::assertNotNull($deck);
        $deckId = $deck->getId();

        $this->postDeleteWithCsrf(
            '/deck/'.$deck->getShortTag(),
            '/deck/'.$deckId.'/delete',
            'form[action*="delete"] input[name="_token"]'
        );

        self::assertResponseRedirects();

        $entityManager->clear();
        /** @var Deck $deleted */
        $deleted = $entityManager->getRepository(Deck::class)->find($deckId);
        self::assertNotNull($deleted->getDeletedAt(), 'Deck should have deletedAt set');
    }

    public function testDeletedDeckFilteredFromCatalog(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Shadow Rider Calyrex']);
        self::assertNotNull($deck);
        $deck->setPublic(true);
        $entityManager->flush();

        /** @var DeckRepository $repository */
        $repository = static::getContainer()->get(DeckRepository::class);
        $countBefore = $repository->countPublicDecks();

        $deck->setDeletedAt(new \DateTimeImmutable());
        $entityManager->flush();
        $entityManager->clear();

        $countAfter = $repository->countPublicDecks();
        self::assertLessThan($countBefore, $countAfter, 'Deleting a public deck should reduce public count');
    }

    public function testNonOwnerCannotDeleteDeck(): void
    {
        $this->loginAs('borrower@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Shadow Rider Calyrex']);
        self::assertNotNull($deck);

        $this->client->request('POST', '/deck/'.$deck->getId().'/delete', [
            '_token' => 'dummy',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeckDeleteWithInvalidCsrfRedirectsBack(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Shadow Rider Calyrex']);
        self::assertNotNull($deck);

        $this->client->request('POST', '/deck/'.$deck->getId().'/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects();

        $entityManager->clear();
        /** @var Deck $notDeleted */
        $notDeleted = $entityManager->getRepository(Deck::class)->find($deck->getId());
        self::assertNull($notDeleted->getDeletedAt(), 'Deck should not be deleted with invalid CSRF');
    }

    public function testDeckDeleteBlockedByActiveBorrows(): void
    {
        $this->loginAs('organizer@example.com');

        $entityManager = $this->getEntityManager();
        /** @var \App\Repository\BorrowRepository $borrowRepository */
        $borrowRepository = static::getContainer()->get(\App\Repository\BorrowRepository::class);

        /** @var Deck[] $decks */
        $decks = $entityManager->getRepository(Deck::class)->findBy([]);

        $deckWithBorrows = null;
        foreach ($decks as $candidate) {
            if ($borrowRepository->countActiveBorrowsForDeck($candidate) > 0
                && 'organizer@example.com' === $candidate->getOwner()->getEmail()) {
                $deckWithBorrows = $candidate;
                break;
            }
        }

        if (null === $deckWithBorrows) {
            self::markTestSkipped('No deck with active borrows owned by organizer found in fixtures');
        }

        $this->postDeleteWithCsrf(
            '/deck/'.$deckWithBorrows->getShortTag(),
            '/deck/'.$deckWithBorrows->getId().'/delete',
            'form[action*="delete"] input[name="_token"]'
        );

        self::assertResponseRedirects();

        $entityManager->clear();
        /** @var Deck $stillExists */
        $stillExists = $entityManager->getRepository(Deck::class)->find($deckWithBorrows->getId());
        self::assertNull($stillExists->getDeletedAt(), 'Deck with active borrows should not be deleted');
    }
}
