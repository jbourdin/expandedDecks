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
use App\Enum\NotificationType;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F8.3 — Notification preferences
 */
class UserNotificationPreferencesTest extends TestCase
{
    public function testIsNotificationEnabledDefaultsToTrueWhenNoPreferencesSet(): void
    {
        $user = new User();

        self::assertTrue($user->isNotificationEnabled(NotificationType::BorrowRequested, 'email'));
        self::assertTrue($user->isNotificationEnabled(NotificationType::BorrowRequested, 'inApp'));
        self::assertTrue($user->isNotificationEnabled(NotificationType::EventUpdated, 'email'));
    }

    public function testIsNotificationEnabledReturnsFalseWhenDisabled(): void
    {
        $user = new User();
        $user->setNotificationPreference(NotificationType::BorrowApproved, 'email', false);

        self::assertFalse($user->isNotificationEnabled(NotificationType::BorrowApproved, 'email'));
    }

    public function testIsNotificationEnabledReturnsTrueForUnsetTypeWhenOtherTypesSet(): void
    {
        $user = new User();
        $user->setNotificationPreference(NotificationType::BorrowApproved, 'email', false);

        // Other types remain enabled by default
        self::assertTrue($user->isNotificationEnabled(NotificationType::BorrowRequested, 'email'));
        self::assertTrue($user->isNotificationEnabled(NotificationType::BorrowRequested, 'inApp'));
    }

    public function testIsNotificationEnabledReturnsTrueForUnsetChannelWhenOtherChannelSet(): void
    {
        $user = new User();
        $user->setNotificationPreference(NotificationType::BorrowApproved, 'email', false);

        // In-app for the same type remains enabled
        self::assertTrue($user->isNotificationEnabled(NotificationType::BorrowApproved, 'inApp'));
    }

    public function testSetNotificationPreferenceCanReEnable(): void
    {
        $user = new User();
        $user->setNotificationPreference(NotificationType::EventCancelled, 'email', false);
        self::assertFalse($user->isNotificationEnabled(NotificationType::EventCancelled, 'email'));

        $user->setNotificationPreference(NotificationType::EventCancelled, 'email', true);
        self::assertTrue($user->isNotificationEnabled(NotificationType::EventCancelled, 'email'));
    }

    public function testGetNotificationPreferencesReturnsAllTypesWithDefaults(): void
    {
        $user = new User();
        $preferences = $user->getNotificationPreferences();

        self::assertCount(\count(NotificationType::cases()), $preferences);

        foreach (NotificationType::cases() as $type) {
            self::assertArrayHasKey($type->value, $preferences);
            self::assertTrue($preferences[$type->value]['email']);
            self::assertTrue($preferences[$type->value]['inApp']);
        }
    }

    public function testGetNotificationPreferencesReflectsOverrides(): void
    {
        $user = new User();
        $user->setNotificationPreference(NotificationType::BorrowOverdue, 'email', false);
        $user->setNotificationPreference(NotificationType::StaffAssigned, 'inApp', false);

        $preferences = $user->getNotificationPreferences();

        self::assertFalse($preferences[NotificationType::BorrowOverdue->value]['email']);
        self::assertTrue($preferences[NotificationType::BorrowOverdue->value]['inApp']);
        self::assertTrue($preferences[NotificationType::StaffAssigned->value]['email']);
        self::assertFalse($preferences[NotificationType::StaffAssigned->value]['inApp']);
    }

    public function testSetNotificationPreferencesOverwritesAll(): void
    {
        $user = new User();
        $user->setNotificationPreferences([
            NotificationType::BorrowRequested->value => ['email' => false, 'inApp' => true],
        ]);

        self::assertFalse($user->isNotificationEnabled(NotificationType::BorrowRequested, 'email'));
        self::assertTrue($user->isNotificationEnabled(NotificationType::BorrowRequested, 'inApp'));
        // Types not in the array still default to true
        self::assertTrue($user->isNotificationEnabled(NotificationType::EventUpdated, 'email'));
    }
}
