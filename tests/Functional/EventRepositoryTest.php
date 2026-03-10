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

use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F3.11 — Event visibility
 * @see docs/features.md F3.15 — Event discovery
 * @see docs/features.md F7.1 — Dashboard
 */
class EventRepositoryTest extends AbstractFunctionalTest
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
    // findVisibleUpcoming() — Anonymous user (null)
    // ---------------------------------------------------------------

    public function testFindVisibleUpcomingForAnonymousReturnsOnlyPublicEvents(): void
    {
        $repository = $this->getRepository();

        $events = $repository->findVisibleUpcoming(null);

        foreach ($events as $event) {
            self::assertSame(
                'public',
                $event->getVisibility()->value,
                \sprintf('Anonymous user should only see public events, but saw "%s" (%s).', $event->getName(), $event->getVisibility()->value),
            );
        }
    }

    public function testFindVisibleUpcomingForAnonymousExcludesDraftEvents(): void
    {
        $repository = $this->getRepository();

        $events = $repository->findVisibleUpcoming(null);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertNotContains(
            'Draft Event — Not Yet Published',
            $eventNames,
            'Draft event should not be visible to anonymous users.',
        );
    }

    public function testFindVisibleUpcomingForAnonymousExcludesCancelledEvents(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        // Cancel an existing upcoming event to ensure it's excluded
        $todayEvent = $repository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($todayEvent);
        $todayEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $events = $repository->findVisibleUpcoming(null);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertNotContains(
            'Expanded Weekly #42',
            $eventNames,
            'Cancelled event should not appear in upcoming events.',
        );
    }

    public function testFindVisibleUpcomingForAnonymousExcludesFinishedEvents(): void
    {
        $repository = $this->getRepository();

        $events = $repository->findVisibleUpcoming(null);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertNotContains(
            'Past Expanded Weekly #40',
            $eventNames,
            'Finished event should not appear in upcoming events.',
        );
    }

    // ---------------------------------------------------------------
    // findVisibleUpcoming() — Authenticated user
    // ---------------------------------------------------------------

    public function testFindVisibleUpcomingForAuthenticatedUserIncludesPublicEvents(): void
    {
        $repository = $this->getRepository();
        $borrower = $this->getUserByEmail('borrower@example.com');

        $events = $repository->findVisibleUpcoming($borrower);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        // Borrower should see public events like "Expanded Weekly #42" (has engagement)
        self::assertContains('Expanded Weekly #42', $eventNames, 'Authenticated user should see public events they are engaged in.');
    }

    public function testFindVisibleUpcomingForAuthenticatedUserIncludesEngagedPrivateEvents(): void
    {
        $repository = $this->getRepository();
        // Admin has an engagement (invited) to the Draft event
        $admin = $this->getUserByEmail('admin@example.com');

        $events = $repository->findVisibleUpcoming($admin);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        // Admin is invited to the Draft event, so should see it
        self::assertContains(
            'Draft Event — Not Yet Published',
            $eventNames,
            'Authenticated user should see draft events they have engagement with.',
        );
    }

    public function testFindVisibleUpcomingForUserWithNoEngagementsOnlySeesPublic(): void
    {
        $repository = $this->getRepository();
        $lender = $this->getUserByEmail('lender@example.com');

        $events = $repository->findVisibleUpcoming($lender);

        // Lender has engagement at "Lyon Expanded Cup 2026" (public) but not at draft/invitational
        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertNotContains(
            'Draft Event — Not Yet Published',
            $eventNames,
            'User without engagement should not see draft events.',
        );
    }

    public function testFindVisibleUpcomingRespectsLimit(): void
    {
        $repository = $this->getRepository();

        $events = $repository->findVisibleUpcoming(null, 1);

        self::assertLessThanOrEqual(1, \count($events), 'Should respect the limit parameter.');
    }

    // ---------------------------------------------------------------
    // searchByName()
    // ---------------------------------------------------------------

    public function testSearchByNameFindsPartialMatch(): void
    {
        $repository = $this->getRepository();

        $events = $repository->searchByName('Expanded');

        self::assertNotEmpty($events, 'Should find events matching "Expanded".');
        foreach ($events as $event) {
            self::assertStringContainsString('Expanded', $event->getName());
        }
    }

    public function testSearchByNameFindsExactMatch(): void
    {
        $repository = $this->getRepository();

        $events = $repository->searchByName('Past Expanded Weekly #40');

        self::assertCount(1, $events);
        self::assertSame('Past Expanded Weekly #40', $events[0]->getName());
    }

    public function testSearchByNameReturnsEmptyForNoMatch(): void
    {
        $repository = $this->getRepository();

        $events = $repository->searchByName('NonExistentEventName12345');

        self::assertEmpty($events, 'Should return empty for a query that matches nothing.');
    }

    public function testSearchByNameExcludesCancelledEvents(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        // Create and cancel an event with a unique name
        /** @var User $admin */
        $admin = $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);

        $cancelledEvent = new Event();
        $cancelledEvent->setName('Cancelled Searchable Event');
        $cancelledEvent->setDate(new \DateTimeImmutable('+1 month'));
        $cancelledEvent->setTimezone('Europe/Paris');
        $cancelledEvent->setLocation('Paris');
        $cancelledEvent->setOrganizer($admin);
        $cancelledEvent->setFormat('Expanded');
        $cancelledEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->persist($cancelledEvent);
        $entityManager->flush();

        $events = $repository->searchByName('Cancelled Searchable Event');

        self::assertEmpty($events, 'Cancelled events should not appear in search results.');
    }

    public function testSearchByNameRespectsLimit(): void
    {
        $repository = $this->getRepository();

        $events = $repository->searchByName('Expanded', 2);

        self::assertLessThanOrEqual(2, \count($events), 'Should respect the limit parameter.');
    }

    public function testSearchByNameIsCaseInsensitiveForLikeQuery(): void
    {
        $repository = $this->getRepository();

        // MySQL LIKE is case-insensitive by default with utf8 collation
        $events = $repository->searchByName('expanded');

        self::assertNotEmpty($events, 'Search should be case-insensitive.');
    }

    // ---------------------------------------------------------------
    // findRecentByOrganizerOrStaff()
    // ---------------------------------------------------------------

    public function testFindRecentByOrganizerOrStaffIncludesRecentEvents(): void
    {
        $repository = $this->getRepository();
        // Admin is organizer of "Past Expanded Weekly #40" (-2 weeks)
        // and organizer of "Expanded Weekly #42" (today)
        $admin = $this->getUserByEmail('admin@example.com');

        $events = $repository->findRecentByOrganizerOrStaff($admin);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        // Today's event should be included (within 7 days)
        self::assertContains('Expanded Weekly #42', $eventNames, 'Should include today\'s event.');
    }

    public function testFindRecentByOrganizerOrStaffExcludesOlderEvents(): void
    {
        $repository = $this->getRepository();
        $admin = $this->getUserByEmail('admin@example.com');

        $events = $repository->findRecentByOrganizerOrStaff($admin);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        // "Past Expanded Weekly #40" is -2 weeks, which is beyond the 7-day cutoff
        self::assertNotContains(
            'Past Expanded Weekly #40',
            $eventNames,
            'Events older than 7 days should not be included.',
        );
    }

    public function testFindRecentByOrganizerOrStaffIncludesStaffEvents(): void
    {
        $repository = $this->getRepository();
        // staff1 is staff at both "Expanded Weekly #42" and "Lyon Expanded Cup 2026"
        $staff1 = $this->getUserByEmail('staff1@example.com');

        $events = $repository->findRecentByOrganizerOrStaff($staff1);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertContains('Expanded Weekly #42', $eventNames, 'Staff member should see their staffed events.');
    }

    public function testFindRecentByOrganizerOrStaffExcludesCancelledEvents(): void
    {
        $repository = $this->getRepository();
        $entityManager = $this->getEntityManager();

        /** @var User $admin */
        $admin = $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);

        // Create a recent cancelled event organized by admin
        $cancelledEvent = new Event();
        $cancelledEvent->setName('Recent Cancelled Event');
        $cancelledEvent->setDate(new \DateTimeImmutable('-2 days'));
        $cancelledEvent->setTimezone('Europe/Paris');
        $cancelledEvent->setLocation('Lyon');
        $cancelledEvent->setOrganizer($admin);
        $cancelledEvent->setFormat('Expanded');
        $cancelledEvent->setCancelledAt(new \DateTimeImmutable('-1 day'));
        $entityManager->persist($cancelledEvent);
        $entityManager->flush();

        $events = $repository->findRecentByOrganizerOrStaff($admin);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertNotContains(
            'Recent Cancelled Event',
            $eventNames,
            'Cancelled events should be excluded from recent events.',
        );
    }

    public function testFindRecentByOrganizerOrStaffReturnsEmptyForUnrelatedUser(): void
    {
        $repository = $this->getRepository();
        // Lender is not organizer or staff at any event
        $lender = $this->getUserByEmail('lender@example.com');

        $events = $repository->findRecentByOrganizerOrStaff($lender);

        self::assertEmpty($events, 'User with no organizer/staff roles should see no recent events.');
    }

    public function testFindRecentByOrganizerOrStaffOrdersByDateAscending(): void
    {
        $repository = $this->getRepository();
        $admin = $this->getUserByEmail('admin@example.com');

        $events = $repository->findRecentByOrganizerOrStaff($admin);

        if (\count($events) >= 2) {
            for ($index = 1; $index < \count($events); ++$index) {
                self::assertGreaterThanOrEqual(
                    $events[$index - 1]->getDate(),
                    $events[$index]->getDate(),
                    'Events should be ordered by date ascending.',
                );
            }
        }
    }

    // ---------------------------------------------------------------
    // findVisibleUpcoming() — Staff access to non-public events
    // ---------------------------------------------------------------

    public function testFindVisibleUpcomingForStaffIncludesStaffedEvents(): void
    {
        $repository = $this->getRepository();
        // staff2 is staff at "Invitation-Only Expanded Meetup" and "Lyon Expanded Cup 2026"
        $staff2 = $this->getUserByEmail('staff2@example.com');

        $events = $repository->findVisibleUpcoming($staff2);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        // staff2 is staff at the invitational event
        self::assertContains(
            'Invitation-Only Expanded Meetup',
            $eventNames,
            'Staff member should see invitational events they are staffed at.',
        );
    }

    public function testFindVisibleUpcomingForOrganizerIncludesOrganizedEvents(): void
    {
        $repository = $this->getRepository();
        // Organizer organizes "Expanded Weekly #42", "Lyon Expanded Cup 2026",
        // "Invitation-Only Expanded Meetup", and "Draft Event"
        $organizer = $this->getUserByEmail('organizer@example.com');

        $events = $repository->findVisibleUpcoming($organizer);

        $eventNames = array_map(static fn (Event $event): string => $event->getName(), $events);
        self::assertContains(
            'Draft Event — Not Yet Published',
            $eventNames,
            'Organizer should see their own draft events.',
        );
    }
}
