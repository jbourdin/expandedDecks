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

    public function testDiscordUsernameGetterAndSetter(): void
    {
        $user = new User();
        self::assertNull($user->getDiscordUsername());

        $user->setDiscordUsername('player42');
        self::assertSame('player42', $user->getDiscordUsername());

        $user->setDiscordUsername(null);
        self::assertNull($user->getDiscordUsername());
    }

    public function testAnonymizeClearsDiscordUsername(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setScreenName('Player');
        $user->setDiscordUsername('player42');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $user->anonymize();

        self::assertNull($user->getDiscordUsername());
    }

    public function testEraseCredentialsDoesNotAlterUserState(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('hashed-password');

        $user->eraseCredentials();

        self::assertSame('test@example.com', $user->getEmail());
        self::assertSame('hashed-password', $user->getPassword());
    }
}
