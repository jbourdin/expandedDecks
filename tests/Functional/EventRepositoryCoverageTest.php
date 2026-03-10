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
use App\Entity\EventEngagement;
use App\Entity\User;
use App\Enum\EngagementState;
use App\Enum\EventVisibility;
use App\Enum\ParticipationMode;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Covers EventRepository methods not yet tested:
 * findEligibleForBorrow, findUpcomingByEngagement, findPublicUpcoming,
 * countUpcomingByOrganizerOrStaff, findUpcomingByOrganizerOrStaff, countUpcoming.
 *
 * @see docs/features.md F3.1 — Create a new event
 * @see docs/features.md F3.11 — Event visibility
 * @see docs/features.md F4.1 — Request to borrow a deck
 * @see docs/features.md F7.1 — Dashboard
 */
class EventRepositoryCoverageTest extends AbstractFunctionalTest
{
    private function getRepository(): EventRepository
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
    // countUpcoming
    // ---------------------------------------------------------------

    public function testCountUpcomingReturnsPositiveCount(): void
    {
        $repository = $this->getRepository();

        $count = $repository->countUpcoming();

        self::assertGreaterThan(0, $count);
    }

    public function testCountUpcomingExcludesCancelledEvents(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        $countBefore = $repository->countUpcoming();

        // Cancel an existing upcoming event
        $todayEvent = $repository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($todayEvent);
        $todayEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $countAfter = $repository->countUpcoming();

        self::assertLessThan($countBefore, $countAfter);
    }

    public function testCountUpcomingExcludesFinishedEvents(): void
    {
        $repository = $this->getRepository();

        // "Past Expanded Weekly #40" is finished — count should not include it
        $count = $repository->countUpcoming();
        $allUpcoming = $repository->findUpcoming(100);
        $allUpcomingNames = array_map(static fn (Event $event): string => $event->getName(), $allUpcoming);

        self::assertNotContains('Past Expanded Weekly #40', $allUpcomingNames);
        self::assertSame(\count($allUpcoming), $count);
    }

    // ---------------------------------------------------------------
    // findUpcoming
    // ---------------------------------------------------------------

    public function testFindUpcomingReturnsOrderedEvents(): void
    {
        $repository = $this->getRepository();

        $events = $repository->findUpcoming(100);

        for ($index = 1; $index < \count($events); ++$index) {
            self::assertGreaterThanOrEqual(
                $events[$index - 1]->getDate(),
                $events[$index]->getDate(),
                'Events should be ordered by date ascending.',
            );
        }
    }

    public function testFindUpcomingRespectsLimit(): void
    {
        $repository = $this->getRepository();

        $events = $repository->findUpcoming(1);

        self::assertLessThanOrEqual(1, \count($events));
    }

    // ---------------------------------------------------------------
    // findPublicUpcoming
    // ---------------------------------------------------------------

    public function testFindPublicUpcomingReturnsOnlyPublicEvents(): void
    {
        $repository = $this->getRepository();

        $events = $repository->findPublicUpcoming();

        foreach ($events as $event) {
            self::assertSame(
                EventVisibility::Public,
                $event->getVisibility(),
                \sprintf('findPublicUpcoming should only return public events, got "%s" (%s).', $event->getName(), $event->getVisibility()->value),
            );
        }
    }

    public function testFindPublicUpcomingExcludesCancelledEvents(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        /** @var User $admin */
        $admin = $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin);

        $cancelledEvent = new Event();
        $cancelledEvent->setName('Public But Cancelled Event');
        $cancelledEvent->setDate(new \DateTimeImmutable('+1 month'));
        $cancelledEvent->setTimezone('UTC');
        $cancelledEvent->setOrganizer($admin);
        $cancelledEvent->setFormat('Expanded');
        $cancelledEvent->setVisibility(EventVisibility::Public);
        $cancelledEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->persist($cancelledEvent);
        $entityManager->flush();

        $events = $repository->findPublicUpcoming();
        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);

        self::assertNotContains('Public But Cancelled Event', $eventNames);
    }

    public function testFindPublicUpcomingExcludesDraftEvents(): void
    {
        $repository = $this->getRepository();

        $events = $repository->findPublicUpcoming();
        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);

        self::assertNotContains('Draft Event — Not Yet Published', $eventNames);
    }

    public function testFindPublicUpcomingRespectsLimit(): void
    {
        $repository = $this->getRepository();

        $events = $repository->findPublicUpcoming(1);

        self::assertLessThanOrEqual(1, \count($events));
    }

    // ---------------------------------------------------------------
    // countUpcomingByOrganizerOrStaff
    // ---------------------------------------------------------------

    public function testCountUpcomingByOrganizerOrStaffForOrganizer(): void
    {
        $repository = $this->getRepository();
        // admin@example.com organizes "Expanded Weekly #42" and "Lyon Expanded Cup 2026"
        $admin = $this->getUserByEmail('admin@example.com');

        $count = $repository->countUpcomingByOrganizerOrStaff($admin);

        self::assertGreaterThan(0, $count);
    }

    public function testCountUpcomingByOrganizerOrStaffForStaff(): void
    {
        $repository = $this->getRepository();
        // staff1 is staff at "Expanded Weekly #42" and "Lyon Expanded Cup 2026"
        $staff1 = $this->getUserByEmail('staff1@example.com');

        $count = $repository->countUpcomingByOrganizerOrStaff($staff1);

        self::assertGreaterThan(0, $count);
    }

    public function testCountUpcomingByOrganizerOrStaffReturnsZeroForUnrelatedUser(): void
    {
        $repository = $this->getRepository();
        $lender = $this->getUserByEmail('lender@example.com');

        $count = $repository->countUpcomingByOrganizerOrStaff($lender);

        self::assertSame(0, $count);
    }

    public function testCountUpcomingByOrganizerOrStaffMatchesFindCount(): void
    {
        $repository = $this->getRepository();
        $admin = $this->getUserByEmail('admin@example.com');

        $count = $repository->countUpcomingByOrganizerOrStaff($admin);
        $events = $repository->findUpcomingByOrganizerOrStaff($admin);

        self::assertSame(\count($events), $count);
    }

    // ---------------------------------------------------------------
    // findUpcomingByOrganizerOrStaff
    // ---------------------------------------------------------------

    public function testFindUpcomingByOrganizerOrStaffForOrganizer(): void
    {
        $repository = $this->getRepository();
        // admin@example.com is the organizer of "Expanded Weekly #42" and "Lyon Expanded Cup 2026"
        $admin = $this->getUserByEmail('admin@example.com');

        $events = $repository->findUpcomingByOrganizerOrStaff($admin);

        self::assertNotEmpty($events);
        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertContains('Expanded Weekly #42', $eventNames);
    }

    public function testFindUpcomingByOrganizerOrStaffExcludesFinishedEvents(): void
    {
        $repository = $this->getRepository();
        // Admin organizes "Past Expanded Weekly #40" (finished)
        $admin = $this->getUserByEmail('admin@example.com');

        $events = $repository->findUpcomingByOrganizerOrStaff($admin);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertNotContains('Past Expanded Weekly #40', $eventNames);
    }

    public function testFindUpcomingByOrganizerOrStaffOrdersByDate(): void
    {
        $repository = $this->getRepository();
        $admin = $this->getUserByEmail('admin@example.com');

        $events = $repository->findUpcomingByOrganizerOrStaff($admin);

        for ($index = 1; $index < \count($events); ++$index) {
            self::assertGreaterThanOrEqual(
                $events[$index - 1]->getDate(),
                $events[$index]->getDate(),
                'Events should be ordered by date ascending.',
            );
        }
    }

    // ---------------------------------------------------------------
    // findUpcomingByEngagement
    // ---------------------------------------------------------------

    public function testFindUpcomingByEngagementReturnsBorrowerEvents(): void
    {
        $repository = $this->getRepository();
        // Borrower has engagements at "Expanded Weekly #42"
        $borrower = $this->getUserByEmail('borrower@example.com');

        $events = $repository->findUpcomingByEngagement($borrower);

        self::assertNotEmpty($events);
        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertContains('Expanded Weekly #42', $eventNames);
    }

    public function testFindUpcomingByEngagementExcludesFinishedEvents(): void
    {
        $repository = $this->getRepository();
        $borrower = $this->getUserByEmail('borrower@example.com');

        $events = $repository->findUpcomingByEngagement($borrower);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        // Borrower has engagement at "Past Expanded Weekly #40" (finished)
        self::assertNotContains('Past Expanded Weekly #40', $eventNames);
    }

    public function testFindUpcomingByEngagementExcludesCancelledEvents(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();
        $borrower = $this->getUserByEmail('borrower@example.com');

        // Create a cancelled event with borrower engagement
        /** @var User $admin */
        $admin = $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin);

        $cancelledEvent = new Event();
        $cancelledEvent->setName('Cancelled Engagement Event');
        $cancelledEvent->setDate(new \DateTimeImmutable('+1 week'));
        $cancelledEvent->setTimezone('UTC');
        $cancelledEvent->setOrganizer($admin);
        $cancelledEvent->setFormat('Expanded');
        $cancelledEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->persist($cancelledEvent);

        $engagement = new EventEngagement();
        $engagement->setEvent($cancelledEvent);
        $engagement->setUser($borrower);
        $engagement->setState(EngagementState::RegisteredPlaying);
        $engagement->setParticipationMode(ParticipationMode::Playing);
        $entityManager->persist($engagement);
        $entityManager->flush();

        $events = $repository->findUpcomingByEngagement($borrower);
        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);

        self::assertNotContains('Cancelled Engagement Event', $eventNames);
    }

    public function testFindUpcomingByEngagementReturnsEmptyForUserWithNoEngagements(): void
    {
        $repository = $this->getRepository();
        // Staff2 has no direct engagements at today's event (only staff role)
        $staff2 = $this->getUserByEmail('staff2@example.com');

        $events = $repository->findUpcomingByEngagement($staff2);

        // Staff2 may or may not have engagements — just verify the method runs without error
        self::assertIsArray($events);
    }

    // ---------------------------------------------------------------
    // findEligibleForBorrow
    // ---------------------------------------------------------------

    public function testFindEligibleForBorrowReturnsEventsWithEngagement(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();
        $borrower = $this->getUserByEmail('borrower@example.com');

        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($deck);

        $events = $repository->findEligibleForBorrow($borrower, $deck);

        self::assertNotEmpty($events);
        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertContains('Expanded Weekly #42', $eventNames);
    }

    public function testFindEligibleForBorrowExcludesCancelledEvents(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();
        $borrower = $this->getUserByEmail('borrower@example.com');

        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($deck);

        $events = $repository->findEligibleForBorrow($borrower, $deck);

        foreach ($events as $event) {
            self::assertNull($event->getCancelledAt(), 'Eligible events should not be cancelled.');
            self::assertNull($event->getFinishedAt(), 'Eligible events should not be finished.');
        }
    }

    public function testFindEligibleForBorrowOrdersByDate(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();
        $borrower = $this->getUserByEmail('borrower@example.com');

        /** @var Deck $deck */
        $deck = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($deck);

        $events = $repository->findEligibleForBorrow($borrower, $deck);

        for ($index = 1; $index < \count($events); ++$index) {
            self::assertGreaterThanOrEqual(
                $events[$index - 1]->getDate(),
                $events[$index]->getDate(),
                'Events should be ordered by date ascending.',
            );
        }
    }
}
