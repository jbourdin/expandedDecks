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

use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\EventDeckRegistration;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F4.8 — Staff-delegated lending
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
class EventDeckRegistrationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $registration = new EventDeckRegistration();

        self::assertNull($registration->getId());
        self::assertFalse($registration->isDelegateToStaff());
        self::assertInstanceOf(\DateTimeImmutable::class, $registration->getRegisteredAt());
        self::assertNull($registration->getStaffReceivedAt());
        self::assertNull($registration->getStaffReceivedBy());
        self::assertNull($registration->getStaffReturnedAt());
        self::assertNull($registration->getStaffReturnedBy());
        self::assertFalse($registration->hasStaffReceived());
        self::assertFalse($registration->hasStaffReturned());
    }

    public function testSetAndGetEvent(): void
    {
        $registration = new EventDeckRegistration();
        $event = new Event();

        $result = $registration->setEvent($event);

        self::assertSame($event, $registration->getEvent());
        self::assertSame($registration, $result);
    }

    public function testSetAndGetDeck(): void
    {
        $registration = new EventDeckRegistration();
        $deck = new Deck();

        $result = $registration->setDeck($deck);

        self::assertSame($deck, $registration->getDeck());
        self::assertSame($registration, $result);
    }

    public function testSetDelegateToStaff(): void
    {
        $registration = new EventDeckRegistration();
        $result = $registration->setDelegateToStaff(true);

        self::assertTrue($registration->isDelegateToStaff());
        self::assertSame($registration, $result);
    }

    public function testSetStaffReceivedAtAndBy(): void
    {
        $registration = new EventDeckRegistration();
        $staffMember = new User();
        $now = new \DateTimeImmutable();

        $registration->setStaffReceivedAt($now);
        $registration->setStaffReceivedBy($staffMember);

        self::assertSame($now, $registration->getStaffReceivedAt());
        self::assertSame($staffMember, $registration->getStaffReceivedBy());
        self::assertTrue($registration->hasStaffReceived());
    }

    public function testSetStaffReturnedAtAndBy(): void
    {
        $registration = new EventDeckRegistration();
        $staffMember = new User();
        $now = new \DateTimeImmutable();

        $registration->setStaffReturnedAt($now);
        $registration->setStaffReturnedBy($staffMember);

        self::assertSame($now, $registration->getStaffReturnedAt());
        self::assertSame($staffMember, $registration->getStaffReturnedBy());
        self::assertTrue($registration->hasStaffReturned());
    }

    public function testOnPrePersistResetsRegisteredAt(): void
    {
        $registration = new EventDeckRegistration();
        $initialRegisteredAt = $registration->getRegisteredAt();

        usleep(1000);
        $registration->onPrePersist();

        self::assertInstanceOf(\DateTimeImmutable::class, $registration->getRegisteredAt());
        self::assertGreaterThanOrEqual($initialRegisteredAt, $registration->getRegisteredAt());
    }
}
