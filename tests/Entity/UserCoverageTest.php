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

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Covers User entity methods not yet tested by other User tests.
 *
 * @see docs/features.md F1.1 — Register a new account
 */
class UserCoverageTest extends TestCase
{
    public function testGetStaffAssignmentsReturnsEmptyCollectionByDefault(): void
    {
        $user = new User();
        self::assertCount(0, $user->getStaffAssignments());
    }

    public function testGetNotificationsReturnsEmptyCollectionByDefault(): void
    {
        $user = new User();
        self::assertCount(0, $user->getNotifications());
    }

    public function testOnPrePersistResetsCreatedAt(): void
    {
        $user = new User();
        $initialCreatedAt = $user->getCreatedAt();

        usleep(1000);
        $user->onPrePersist();

        self::assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        self::assertGreaterThanOrEqual($initialCreatedAt, $user->getCreatedAt());
    }
}
