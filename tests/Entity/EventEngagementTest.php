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
use App\Entity\User;
use App\Enum\EngagementState;
use App\Enum\ParticipationMode;
use PHPUnit\Framework\TestCase;

class EventEngagementTest extends TestCase
{
    public function testNewEngagementHasNullId(): void
    {
        $engagement = new EventEngagement();
        self::assertNull($engagement->getId());
    }

    public function testSetAndGetEvent(): void
    {
        $engagement = new EventEngagement();
        $event = new Event();

        $result = $engagement->setEvent($event);

        self::assertSame($event, $engagement->getEvent());
        self::assertSame($engagement, $result);
    }

    public function testSetAndGetUser(): void
    {
        $engagement = new EventEngagement();
        $user = new User();

        $result = $engagement->setUser($user);

        self::assertSame($user, $engagement->getUser());
        self::assertSame($engagement, $result);
    }

    public function testSetAndGetState(): void
    {
        $engagement = new EventEngagement();

        $result = $engagement->setState(EngagementState::RegisteredPlaying);

        self::assertSame(EngagementState::RegisteredPlaying, $engagement->getState());
        self::assertSame($engagement, $result);
    }

    public function testSetAndGetParticipationMode(): void
    {
        $engagement = new EventEngagement();

        $result = $engagement->setParticipationMode(ParticipationMode::Spectating);

        self::assertSame(ParticipationMode::Spectating, $engagement->getParticipationMode());
        self::assertSame($engagement, $result);
    }

    public function testParticipationModeDefaultsToNull(): void
    {
        $engagement = new EventEngagement();
        self::assertNull($engagement->getParticipationMode());
    }

    public function testSetAndGetInvitedBy(): void
    {
        $engagement = new EventEngagement();
        $inviter = new User();

        $result = $engagement->setInvitedBy($inviter);

        self::assertSame($inviter, $engagement->getInvitedBy());
        self::assertSame($engagement, $result);
    }

    public function testInvitedByDefaultsToNull(): void
    {
        $engagement = new EventEngagement();
        self::assertNull($engagement->getInvitedBy());
    }

    public function testTimestampsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $engagement = new EventEngagement();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $engagement->getCreatedAt());
        self::assertLessThanOrEqual($after, $engagement->getCreatedAt());
        self::assertGreaterThanOrEqual($before, $engagement->getUpdatedAt());
        self::assertLessThanOrEqual($after, $engagement->getUpdatedAt());
    }

    public function testOnPrePersistResetsTimestamps(): void
    {
        $engagement = new EventEngagement();
        $originalCreatedAt = $engagement->getCreatedAt();

        usleep(1000);
        $engagement->onPrePersist();

        self::assertGreaterThanOrEqual($originalCreatedAt, $engagement->getCreatedAt());
        self::assertGreaterThanOrEqual($originalCreatedAt, $engagement->getUpdatedAt());
    }

    public function testOnPreUpdateRefreshesUpdatedAt(): void
    {
        $engagement = new EventEngagement();
        $originalUpdatedAt = $engagement->getUpdatedAt();

        usleep(1000);
        $engagement->onPreUpdate();

        self::assertGreaterThanOrEqual($originalUpdatedAt, $engagement->getUpdatedAt());
    }
}
