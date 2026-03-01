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
use App\Entity\EventStaff;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class EventStaffTest extends TestCase
{
    public function testNewStaffHasNullId(): void
    {
        $staff = new EventStaff();
        self::assertNull($staff->getId());
    }

    public function testSetAndGetEvent(): void
    {
        $staff = new EventStaff();
        $event = new Event();

        $result = $staff->setEvent($event);

        self::assertSame($event, $staff->getEvent());
        self::assertSame($staff, $result);
    }

    public function testSetAndGetUser(): void
    {
        $staff = new EventStaff();
        $user = new User();

        $result = $staff->setUser($user);

        self::assertSame($user, $staff->getUser());
        self::assertSame($staff, $result);
    }

    public function testSetAndGetAssignedBy(): void
    {
        $staff = new EventStaff();
        $assigner = new User();

        $result = $staff->setAssignedBy($assigner);

        self::assertSame($assigner, $staff->getAssignedBy());
        self::assertSame($staff, $result);
    }

    public function testAssignedAtSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $staff = new EventStaff();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $staff->getAssignedAt());
        self::assertLessThanOrEqual($after, $staff->getAssignedAt());
    }

    public function testOnPrePersistResetsAssignedAt(): void
    {
        $staff = new EventStaff();
        $originalAssignedAt = $staff->getAssignedAt();

        usleep(1000);
        $staff->onPrePersist();

        self::assertGreaterThanOrEqual($originalAssignedAt, $staff->getAssignedAt());
    }
}
