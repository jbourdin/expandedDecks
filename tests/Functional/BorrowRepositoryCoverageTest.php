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

use App\Entity\Borrow;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Coverage for BorrowRepository query methods.
 *
 * @see docs/features.md F1.8 — Account deletion & data export (GDPR)
 * @see docs/features.md F4.5 — Borrow history
 * @see docs/features.md F4.2 — Approve / deny a borrow request
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
class BorrowRepositoryCoverageTest extends AbstractFunctionalTest
{
    private function getBorrowRepository(): BorrowRepository
    {
        /** @var BorrowRepository $repository */
        $repository = static::getContainer()->get(BorrowRepository::class);

        return $repository;
    }

    private function getDeckRepository(): DeckRepository
    {
        /** @var DeckRepository $repository */
        $repository = static::getContainer()->get(DeckRepository::class);

        return $repository;
    }

    private function getEventRepository(): EventRepository
    {
        /** @var EventRepository $repository */
        $repository = static::getContainer()->get(EventRepository::class);

        return $repository;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function getUserByEmail(string $email): User
    {
        $entityManager = $this->getEntityManager();
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }

    // ---------------------------------------------------------------
    // hasUnsettledBorrows
    // ---------------------------------------------------------------

    public function testHasUnsettledBorrowsReturnsTrueForBorrowerWithActiveBorrows(): void
    {
        $repository = $this->getBorrowRepository();
        // Borrower has pending + approved borrows in fixtures
        $borrower = $this->getUserByEmail('borrower@example.com');

        $result = $repository->hasUnsettledBorrows($borrower);

        self::assertTrue($result, 'Borrower with active borrows should have unsettled borrows.');
    }

    public function testHasUnsettledBorrowsReturnsTrueForOwnerWithActiveBorrows(): void
    {
        $repository = $this->getBorrowRepository();
        // Admin owns Iron Thorns and Ancient Box, which have active borrows
        $admin = $this->getUserByEmail('admin@example.com');

        $result = $repository->hasUnsettledBorrows($admin);

        self::assertTrue($result, 'Deck owner with active borrows on their decks should have unsettled borrows.');
    }

    public function testHasUnsettledBorrowsReturnsFalseForUserWithNoBorrows(): void
    {
        $repository = $this->getBorrowRepository();
        // Staff2 has no borrows as borrower and owns no decks with active borrows
        $staff2 = $this->getUserByEmail('staff2@example.com');

        $result = $repository->hasUnsettledBorrows($staff2);

        self::assertFalse($result, 'User with no active borrows should not have unsettled borrows.');
    }

    // ---------------------------------------------------------------
    // findByDeckForUser
    // ---------------------------------------------------------------

    public function testFindByDeckForUserReturnsResultsForDeckOwner(): void
    {
        $repository = $this->getBorrowRepository();
        $deckRepository = $this->getDeckRepository();
        $admin = $this->getUserByEmail('admin@example.com');

        // Iron Thorns is owned by admin and has borrows
        $ironThorns = $deckRepository->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($ironThorns);

        $borrows = $repository->findByDeckForUser($ironThorns, $admin);

        self::assertNotEmpty($borrows, 'Deck owner should see borrows for their deck.');
    }

    public function testFindByDeckForUserReturnsResultsForBorrower(): void
    {
        $repository = $this->getBorrowRepository();
        $deckRepository = $this->getDeckRepository();
        $borrower = $this->getUserByEmail('borrower@example.com');

        // Borrower has a borrow on Iron Thorns
        $ironThorns = $deckRepository->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($ironThorns);

        $borrows = $repository->findByDeckForUser($ironThorns, $borrower);

        self::assertNotEmpty($borrows, 'Borrower should see their own borrows for the deck.');
    }

    public function testFindByDeckForUserReturnsEmptyForUnrelatedUser(): void
    {
        $repository = $this->getBorrowRepository();
        $deckRepository = $this->getDeckRepository();
        $staff2 = $this->getUserByEmail('staff2@example.com');

        // Staff2 has no relationship to Iron Thorns borrows
        $ironThorns = $deckRepository->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($ironThorns);

        $borrows = $repository->findByDeckForUser($ironThorns, $staff2);

        self::assertEmpty($borrows, 'Unrelated user should see no borrows for the deck.');
    }

    public function testFindByDeckForUserReturnsResultsForEventOrganizer(): void
    {
        $repository = $this->getBorrowRepository();
        $deckRepository = $this->getDeckRepository();
        // Lender owns Regidrago, which has a delegated borrow at todayEvent.
        // todayEvent organizer is admin — admin is not borrower or deck owner for Regidrago.
        $admin = $this->getUserByEmail('admin@example.com');

        $regidrago = $deckRepository->findOneBy(['name' => 'Regidrago']);
        self::assertNotNull($regidrago);

        $borrows = $repository->findByDeckForUser($regidrago, $admin);

        self::assertNotEmpty($borrows, 'Event organizer should see borrows for decks at their events.');
    }

    public function testFindByDeckForUserOrdersByRequestedAtDescending(): void
    {
        $repository = $this->getBorrowRepository();
        $deckRepository = $this->getDeckRepository();
        $admin = $this->getUserByEmail('admin@example.com');

        $ironThorns = $deckRepository->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($ironThorns);

        $borrows = $repository->findByDeckForUser($ironThorns, $admin);

        if (\count($borrows) >= 2) {
            for ($index = 1; $index < \count($borrows); ++$index) {
                self::assertGreaterThanOrEqual(
                    $borrows[$index]->getRequestedAt(),
                    $borrows[$index - 1]->getRequestedAt(),
                    'Borrows should be ordered by requestedAt descending.',
                );
            }
        }
    }

    // ---------------------------------------------------------------
    // findActiveBorrowsForDeckAtEvent
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    public function testFindActiveBorrowsForDeckAtEventReturnsActiveBorrows(): void
    {
        $repository = $this->getBorrowRepository();
        $deckRepository = $this->getDeckRepository();
        $eventRepository = $this->getEventRepository();

        $ironThorns = $deckRepository->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($ironThorns);

        $todayEvent = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($todayEvent);

        // Iron Thorns has a pending borrow at todayEvent (pending is active)
        $borrows = $repository->findActiveBorrowsForDeckAtEvent($ironThorns, $todayEvent);

        self::assertNotEmpty($borrows, 'Should find active borrows for Iron Thorns at today event.');
        foreach ($borrows as $borrow) {
            self::assertContains(
                $borrow->getStatus()->value,
                BorrowRepository::activeStatusValues(),
                'All returned borrows should have active statuses.',
            );
        }
    }

    /**
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    public function testFindActiveBorrowsForDeckAtEventReturnsEmptyWhenNoneExist(): void
    {
        $repository = $this->getBorrowRepository();
        $deckRepository = $this->getDeckRepository();
        $eventRepository = $this->getEventRepository();

        // Ancient Box has an approved borrow at todayEvent but not at futureEvent
        $ancientBox = $deckRepository->findOneBy(['name' => 'Ancient Box']);
        self::assertNotNull($ancientBox);

        $futureEvent = $eventRepository->findOneBy(['name' => 'Lyon Expanded Cup 2026']);
        self::assertNotNull($futureEvent);

        $borrows = $repository->findActiveBorrowsForDeckAtEvent($ancientBox, $futureEvent);

        self::assertEmpty($borrows, 'Should return empty when no active borrows exist for the deck at this event.');
    }

    /**
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    public function testFindActiveBorrowsForDeckAtEventReturnsMultipleBorrows(): void
    {
        $repository = $this->getBorrowRepository();
        $deckRepository = $this->getDeckRepository();
        $eventRepository = $this->getEventRepository();
        $entityManager = $this->getEntityManager();

        $ironThorns = $deckRepository->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($ironThorns);
        $ironThornsVersion = $ironThorns->getCurrentVersion();
        self::assertNotNull($ironThornsVersion);

        $todayEvent = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($todayEvent);

        // Add a second active borrow for Iron Thorns at todayEvent
        $lender = $this->getUserByEmail('lender@example.com');
        $extraBorrow = new Borrow();
        $extraBorrow->setDeck($ironThorns);
        $extraBorrow->setDeckVersion($ironThornsVersion);
        $extraBorrow->setBorrower($lender);
        $extraBorrow->setEvent($todayEvent);
        $entityManager->persist($extraBorrow);
        $entityManager->flush();

        $borrows = $repository->findActiveBorrowsForDeckAtEvent($ironThorns, $todayEvent);

        self::assertGreaterThanOrEqual(2, \count($borrows), 'Should return multiple active borrows when they exist.');
    }

    // ---------------------------------------------------------------
    // findPendingRequestsForOwner
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F4.2 — Approve / deny a borrow request
     */
    public function testFindPendingRequestsForOwnerReturnsPendingBorrowsOnOwnedDecks(): void
    {
        $repository = $this->getBorrowRepository();
        // Admin owns Iron Thorns and Ancient Box.
        // Iron Thorns has pending borrows (one at todayEvent from borrower, one at futureEvent from lender).
        $admin = $this->getUserByEmail('admin@example.com');

        $borrows = $repository->findPendingRequestsForOwner($admin);

        self::assertNotEmpty($borrows, 'Should find pending borrow requests for decks owned by admin.');
        foreach ($borrows as $borrow) {
            self::assertSame(
                BorrowStatus::Pending,
                $borrow->getStatus(),
                'All returned borrows should have pending status.',
            );
            self::assertSame(
                $admin->getId(),
                $borrow->getDeck()->getOwner()->getId(),
                'All returned borrows should be for decks owned by the given owner.',
            );
        }
    }

    /**
     * @see docs/features.md F4.2 — Approve / deny a borrow request
     */
    public function testFindPendingRequestsForOwnerReturnsEmptyWhenNoPendingRequests(): void
    {
        $repository = $this->getBorrowRepository();
        // Staff2 owns no decks, so should have no pending requests
        $staff2 = $this->getUserByEmail('staff2@example.com');

        $borrows = $repository->findPendingRequestsForOwner($staff2);

        self::assertEmpty($borrows, 'Should return empty when user owns no decks with pending borrows.');
    }

    /**
     * @see docs/features.md F4.2 — Approve / deny a borrow request
     */
    public function testFindPendingRequestsForOwnerOrdersByRequestedAtAscending(): void
    {
        $repository = $this->getBorrowRepository();
        $admin = $this->getUserByEmail('admin@example.com');

        $borrows = $repository->findPendingRequestsForOwner($admin);

        if (\count($borrows) >= 2) {
            for ($index = 1; $index < \count($borrows); ++$index) {
                self::assertLessThanOrEqual(
                    $borrows[$index]->getRequestedAt(),
                    $borrows[$index - 1]->getRequestedAt(),
                    'Pending requests should be ordered by requestedAt ascending (oldest first).',
                );
            }
        }
    }

    /**
     * @see docs/features.md F4.2 — Approve / deny a borrow request
     */
    public function testFindPendingRequestsForOwnerExcludesNonPendingBorrows(): void
    {
        $repository = $this->getBorrowRepository();
        // Admin owns Ancient Box which has an approved borrow at todayEvent.
        // That approved borrow should NOT appear in pending requests.
        $admin = $this->getUserByEmail('admin@example.com');

        $borrows = $repository->findPendingRequestsForOwner($admin);

        foreach ($borrows as $borrow) {
            self::assertSame(
                BorrowStatus::Pending,
                $borrow->getStatus(),
                'Only pending borrows should be returned, not approved or other statuses.',
            );
        }
    }

    /**
     * @see docs/features.md F4.2 — Approve / deny a borrow request
     */
    public function testFindPendingRequestsForOwnerEagerLoadsDeckEventAndBorrower(): void
    {
        $repository = $this->getBorrowRepository();
        $admin = $this->getUserByEmail('admin@example.com');

        $borrows = $repository->findPendingRequestsForOwner($admin);

        self::assertNotEmpty($borrows);
        $borrow = $borrows[0];

        // Verify eager-loaded associations are accessible (no lazy-loading proxy issues)
        self::assertNotNull($borrow->getDeck()->getName());
        self::assertNotNull($borrow->getEvent()->getName());
        self::assertNotNull($borrow->getBorrower()->getScreenName());
    }
}
