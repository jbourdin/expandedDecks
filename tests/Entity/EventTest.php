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
use App\Entity\User;
use App\Enum\EngagementState;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testGetEngagementsReturnsEmptyCollectionByDefault(): void
    {
        $event = new Event();
        self::assertCount(0, $event->getEngagements());
    }

    public function testGetEngagementForReturnsNullWhenNoMatch(): void
    {
        $event = new Event();
        $user = $this->createUserWithId(1);

        self::assertNull($event->getEngagementFor($user));
    }

    public function testCountByStateReturnsZeroWhenNoEngagements(): void
    {
        $event = new Event();
        self::assertSame(0, $event->countByState(EngagementState::RegisteredPlaying));
    }

    public function testUserGetEventEngagementsReturnsEmptyCollection(): void
    {
        $user = new User();
        self::assertCount(0, $user->getEventEngagements());
    }

    private function createUserWithId(int $id): User
    {
        $user = new User();
        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }
}
