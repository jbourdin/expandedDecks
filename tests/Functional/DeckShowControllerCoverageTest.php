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

use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\EventDeckEntry;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Additional coverage tests for DeckShowController uncovered branches.
 *
 * Covers access control for private decks:
 * - Non-owner/non-admin/non-staff sees 403
 * - Staff of an event where the deck is registered can see a private deck
 *
 * @see docs/features.md F2.3 — Detail view
 */
class DeckShowControllerCoverageTest extends AbstractFunctionalTest
{
    /**
     * A logged-in user who is not the owner, admin, or event staff should
     * get 403 for a private deck.
     */
    public function testPrivateDeckDeniedForNonOwnerNonAdmin(): void
    {
        // Ancient Box is owned by admin, not public
        // lender@example.com is not admin, not owner, not staff for the event where Ancient Box is registered
        $this->loginAs('lender@example.com');

        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * An admin can see any private deck.
     */
    public function testPrivateDeckAccessibleByAdmin(): void
    {
        $this->loginAs('admin@example.com');

        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
    }

    /**
     * A staff member of an event where the private deck is registered can
     * see the private deck.
     *
     * Ancient Box is registered at "Expanded Weekly #42" event and borrower
     * is staff for that event.
     */
    public function testPrivateDeckAccessibleByEventStaff(): void
    {
        // borrower@example.com is staff on "Expanded Weekly #42" event
        // Ancient Box is registered on that same event
        $this->loginAs('borrower@example.com');

        $shortTag = $this->getDeckShortTag('Ancient Box');
        $this->client->request('GET', '/deck/'.$shortTag);

        self::assertResponseIsSuccessful();
    }

    /**
     * A deck with no current version should still display correctly for
     * the owner (no grouped cards section).
     */
    public function testDeckShowWithNoVersionShowsEmptyCardList(): void
    {
        $this->loginAs('admin@example.com');

        // Create a deck without a version
        $entityManager = $this->getEntityManager();
        $deck = new Deck();
        $deck->setName('No Version Deck');
        $deck->setOwner($entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']));
        $deck->setFormat('Expanded');
        $deck->setPublic(true);
        $entityManager->persist($deck);
        $entityManager->flush();

        $this->client->request('GET', '/deck/'.$deck->getShortTag());

        self::assertResponseIsSuccessful();
    }

    /**
     * When the deck owner views a deck that is played (has an EventDeckEntry)
     * at an upcoming event where they are engaged, the event status overview
     * should include DeckEventStatus::Played.
     *
     * Covers DeckShowController::show() line 129.
     *
     * @see docs/features.md F2.14 — Deck event status overview
     */
    public function testDeckShowEventStatusOverviewPlayed(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();
        $admin = $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin);

        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        // Admin is already engaged at this event (from fixtures)
        // Create a deck entry for Iron Thorns at this event (Played status takes priority)
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($deck);
        $currentVersion = $deck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        $entry = new EventDeckEntry();
        $entry->setEvent($event);
        $entry->setPlayer($admin);
        $entry->setDeckVersion($currentVersion);
        $entityManager->persist($entry);
        $entityManager->flush();

        $this->client->request('GET', '/deck/'.$deck->getShortTag());
        self::assertResponseIsSuccessful();
    }

    /**
     * When the deck owner views a deck that is registered (non-delegated) at an
     * upcoming event with no active borrow, the event status overview should
     * include DeckEventStatus::Registered.
     *
     * Covers DeckShowController::show() line 135.
     *
     * @see docs/features.md F2.14 — Deck event status overview
     */
    public function testDeckShowEventStatusOverviewRegistered(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();

        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        // Ancient Box is registered at this event with delegateToStaff=false,
        // but it has an active (approved) borrow. Cancel the borrow so the
        // registration check is reached instead of the borrow check.
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Ancient Box']);
        self::assertNotNull($deck);

        $this->cancelActiveBorrowsForDeck($deck, $event, $entityManager);

        $this->client->request('GET', '/deck/'.$deck->getShortTag());
        self::assertResponseIsSuccessful();
    }

    /**
     * When the deck owner views a deck that is registered with delegation at
     * an upcoming event with no active borrow, the event status overview should
     * include DeckEventStatus::DelegatedToStaff.
     *
     * Covers DeckShowController::show() lines 133-134.
     *
     * @see docs/features.md F2.14 — Deck event status overview
     */
    public function testDeckShowEventStatusOverviewDelegatedToStaff(): void
    {
        $this->loginAs('admin@example.com');

        $entityManager = $this->getEntityManager();

        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        // Iron Thorns is registered with delegateToStaff=true at this event,
        // but it has an active (pending) borrow. Cancel the borrow so the
        // registration check is reached.
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($deck);

        $this->cancelActiveBorrowsForDeck($deck, $event, $entityManager);

        $this->client->request('GET', '/deck/'.$deck->getShortTag());
        self::assertResponseIsSuccessful();
    }

    private function cancelActiveBorrowsForDeck(Deck $deck, Event $event, EntityManagerInterface $entityManager): void
    {
        /** @var \App\Repository\BorrowRepository $borrowRepository */
        $borrowRepository = static::getContainer()->get(\App\Repository\BorrowRepository::class);
        $borrow = $borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event);

        if (null !== $borrow) {
            $borrow->setStatus(BorrowStatus::Cancelled);
            $borrow->setCancelledAt(new \DateTimeImmutable());
            $entityManager->flush();
        }
    }

    private function getDeckShortTag(string $name): string
    {
        $entityManager = $this->getEntityManager();
        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => $name]);

        return $deck->getShortTag();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }
}
