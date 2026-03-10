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

namespace App\Tests\Entity;

use App\Entity\Event;
use App\Entity\EventEngagement;
use App\Entity\EventStaff;
use App\Entity\User;
use App\Enum\EngagementState;
use App\Enum\EventVisibility;
use App\Enum\TournamentStructure;
use PHPUnit\Framework\TestCase;

/**
 * Covers Event entity methods not yet tested by EventTest.
 *
 * @see docs/features.md F3.1 — Create a new event
 * @see docs/features.md F3.5 — Assign event staff team
 */
class EventCoverageTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $event = new Event();

        self::assertNull($event->getId());
        self::assertSame('', $event->getName());
        self::assertNull($event->getEventId());
        self::assertSame('Expanded', $event->getFormat());
        self::assertInstanceOf(\DateTimeImmutable::class, $event->getDate());
        self::assertNull($event->getEndDate());
        self::assertSame('UTC', $event->getTimezone());
        self::assertNull($event->getLocation());
        self::assertNull($event->getDescription());
        self::assertNull($event->getRegistrationLink());
        self::assertNull($event->getTournamentStructure());
        self::assertNull($event->getMinAttendees());
        self::assertNull($event->getMaxAttendees());
        self::assertNull($event->getRoundDuration());
        self::assertNull($event->getTopCutRoundDuration());
        self::assertNull($event->getEntryFeeAmount());
        self::assertNull($event->getEntryFeeCurrency());
        self::assertSame(EventVisibility::Public, $event->getVisibility());
        self::assertFalse($event->isDecklistMandatory());
        self::assertFalse($event->isInvitationOnly());
        self::assertInstanceOf(\DateTimeImmutable::class, $event->getCreatedAt());
        self::assertNull($event->getCancelledAt());
        self::assertNull($event->getFinishedAt());
    }

    public function testSetEventId(): void
    {
        $event = new Event();
        $result = $event->setEventId('EVT-12345');

        self::assertSame('EVT-12345', $event->getEventId());
        self::assertSame($event, $result);
    }

    public function testSetFormat(): void
    {
        $event = new Event();
        $result = $event->setFormat('Standard');

        self::assertSame('Standard', $event->getFormat());
        self::assertSame($event, $result);
    }

    public function testSetEndDate(): void
    {
        $event = new Event();
        $endDate = new \DateTimeImmutable('2026-06-15');
        $result = $event->setEndDate($endDate);

        self::assertSame($endDate, $event->getEndDate());
        self::assertSame($event, $result);
    }

    public function testSetTimezone(): void
    {
        $event = new Event();
        $result = $event->setTimezone('Europe/Paris');

        self::assertSame('Europe/Paris', $event->getTimezone());
        self::assertSame($event, $result);
    }

    public function testSetLocation(): void
    {
        $event = new Event();
        $result = $event->setLocation('Paris Convention Center');

        self::assertSame('Paris Convention Center', $event->getLocation());
        self::assertSame($event, $result);
    }

    public function testSetDescription(): void
    {
        $event = new Event();
        $result = $event->setDescription('A great event.');

        self::assertSame('A great event.', $event->getDescription());
        self::assertSame($event, $result);
    }

    public function testSetRegistrationLink(): void
    {
        $event = new Event();
        $result = $event->setRegistrationLink('https://example.com/register');

        self::assertSame('https://example.com/register', $event->getRegistrationLink());
        self::assertSame($event, $result);
    }

    public function testSetTournamentStructure(): void
    {
        $event = new Event();
        $result = $event->setTournamentStructure(TournamentStructure::Swiss);

        self::assertSame(TournamentStructure::Swiss, $event->getTournamentStructure());
        self::assertSame($event, $result);
    }

    public function testSetMinAttendees(): void
    {
        $event = new Event();
        $result = $event->setMinAttendees(8);

        self::assertSame(8, $event->getMinAttendees());
        self::assertSame($event, $result);
    }

    public function testSetMaxAttendees(): void
    {
        $event = new Event();
        $result = $event->setMaxAttendees(64);

        self::assertSame(64, $event->getMaxAttendees());
        self::assertSame($event, $result);
    }

    public function testSetRoundDuration(): void
    {
        $event = new Event();
        $result = $event->setRoundDuration(50);

        self::assertSame(50, $event->getRoundDuration());
        self::assertSame($event, $result);
    }

    public function testSetTopCutRoundDuration(): void
    {
        $event = new Event();
        $result = $event->setTopCutRoundDuration(60);

        self::assertSame(60, $event->getTopCutRoundDuration());
        self::assertSame($event, $result);
    }

    public function testSetEntryFeeAmount(): void
    {
        $event = new Event();
        $result = $event->setEntryFeeAmount(1500);

        self::assertSame(1500, $event->getEntryFeeAmount());
        self::assertSame($event, $result);
    }

    public function testSetEntryFeeCurrency(): void
    {
        $event = new Event();
        $result = $event->setEntryFeeCurrency('EUR');

        self::assertSame('EUR', $event->getEntryFeeCurrency());
        self::assertSame($event, $result);
    }

    public function testSetVisibility(): void
    {
        $event = new Event();
        $result = $event->setVisibility(EventVisibility::Draft);

        self::assertSame(EventVisibility::Draft, $event->getVisibility());
        self::assertSame($event, $result);
    }

    public function testSetIsDecklistMandatory(): void
    {
        $event = new Event();
        $result = $event->setIsDecklistMandatory(true);

        self::assertTrue($event->isDecklistMandatory());
        self::assertSame($event, $result);
    }

    public function testSetIsInvitationOnly(): void
    {
        $event = new Event();
        $result = $event->setIsInvitationOnly(true);

        self::assertTrue($event->isInvitationOnly());
        self::assertSame($event, $result);
    }

    public function testSetCancelledAt(): void
    {
        $event = new Event();
        $now = new \DateTimeImmutable();
        $result = $event->setCancelledAt($now);

        self::assertSame($now, $event->getCancelledAt());
        self::assertSame($event, $result);
    }

    public function testGetStaffReturnsEmptyCollectionByDefault(): void
    {
        $event = new Event();
        self::assertCount(0, $event->getStaff());
    }

    public function testGetStaffForReturnsNullWhenNoMatch(): void
    {
        $event = new Event();
        $user = $this->createUserWithId(42);

        self::assertNull($event->getStaffFor($user));
    }

    public function testGetStaffForReturnsMatchingStaffMember(): void
    {
        $event = new Event();
        $user = $this->createUserWithId(7);

        $staffMember = new EventStaff();
        $staffMember->setUser($user);

        $this->addStaffToEvent($event, $staffMember);

        self::assertSame($staffMember, $event->getStaffFor($user));
    }

    public function testIsOrganizerOrStaffReturnsTrueForOrganizer(): void
    {
        $organizer = $this->createUserWithId(1);
        $event = new Event();
        $event->setOrganizer($organizer);

        self::assertTrue($event->isOrganizerOrStaff($organizer));
    }

    public function testIsOrganizerOrStaffReturnsTrueForStaff(): void
    {
        $organizer = $this->createUserWithId(1);
        $staffUser = $this->createUserWithId(2);

        $event = new Event();
        $event->setOrganizer($organizer);

        $staffMember = new EventStaff();
        $staffMember->setUser($staffUser);
        $this->addStaffToEvent($event, $staffMember);

        self::assertTrue($event->isOrganizerOrStaff($staffUser));
    }

    public function testIsOrganizerOrStaffReturnsFalseForUnrelatedUser(): void
    {
        $organizer = $this->createUserWithId(1);
        $unrelatedUser = $this->createUserWithId(99);

        $event = new Event();
        $event->setOrganizer($organizer);

        self::assertFalse($event->isOrganizerOrStaff($unrelatedUser));
    }

    public function testGetBorrowsReturnsEmptyCollectionByDefault(): void
    {
        $event = new Event();
        self::assertCount(0, $event->getBorrows());
    }

    public function testGetDeckEntriesReturnsEmptyCollectionByDefault(): void
    {
        $event = new Event();
        self::assertCount(0, $event->getDeckEntries());
    }

    public function testGetDeckRegistrationsReturnsEmptyCollectionByDefault(): void
    {
        $event = new Event();
        self::assertCount(0, $event->getDeckRegistrations());
    }

    public function testOnPrePersistResetsCreatedAt(): void
    {
        $event = new Event();
        $initialCreatedAt = $event->getCreatedAt();

        usleep(1000);
        $event->onPrePersist();

        self::assertInstanceOf(\DateTimeImmutable::class, $event->getCreatedAt());
        self::assertGreaterThanOrEqual($initialCreatedAt, $event->getCreatedAt());
    }

    public function testGetEngagementForReturnsMatchingEngagement(): void
    {
        $event = new Event();
        $user = $this->createUserWithId(5);

        $engagement = new EventEngagement();
        $engagement->setUser($user);
        $engagement->setState(EngagementState::RegisteredPlaying);

        $this->addEngagementToEvent($event, $engagement);

        self::assertSame($engagement, $event->getEngagementFor($user));
    }

    public function testCountByStateCountsCorrectly(): void
    {
        $event = new Event();

        $engagement1 = new EventEngagement();
        $engagement1->setUser($this->createUserWithId(1));
        $engagement1->setState(EngagementState::RegisteredPlaying);

        $engagement2 = new EventEngagement();
        $engagement2->setUser($this->createUserWithId(2));
        $engagement2->setState(EngagementState::RegisteredPlaying);

        $engagement3 = new EventEngagement();
        $engagement3->setUser($this->createUserWithId(3));
        $engagement3->setState(EngagementState::Interested);

        $this->addEngagementToEvent($event, $engagement1);
        $this->addEngagementToEvent($event, $engagement2);
        $this->addEngagementToEvent($event, $engagement3);

        self::assertSame(2, $event->countByState(EngagementState::RegisteredPlaying));
        self::assertSame(1, $event->countByState(EngagementState::Interested));
        self::assertSame(0, $event->countByState(EngagementState::Invited));
    }

    private function createUserWithId(int $id): User
    {
        $user = new User();
        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }

    private function addStaffToEvent(Event $event, EventStaff $staffMember): void
    {
        $reflection = new \ReflectionProperty(Event::class, 'staff');
        /** @var \Doctrine\Common\Collections\Collection<int, EventStaff> $collection */
        $collection = $reflection->getValue($event);
        $collection->add($staffMember);
    }

    private function addEngagementToEvent(Event $event, EventEngagement $engagement): void
    {
        $reflection = new \ReflectionProperty(Event::class, 'engagements');
        /** @var \Doctrine\Common\Collections\Collection<int, EventEngagement> $collection */
        $collection = $reflection->getValue($event);
        $collection->add($engagement);
    }
}
