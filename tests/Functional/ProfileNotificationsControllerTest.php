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

use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\UserRepository;

/**
 * @see docs/features.md F8.3 — Notification preferences
 */
class ProfileNotificationsControllerTest extends AbstractFunctionalTest
{
    public function testNotificationsPageLoadsForAuthenticatedUser(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/profile/notifications');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="preferences[borrow_requested][email]"]');
        self::assertSelectorExists('input[name="preferences[event_updated][inApp]"]');
    }

    public function testNotificationsPageRedirectsForAnonymousUser(): void
    {
        $this->client->request('GET', '/profile/notifications');
        self::assertResponseRedirects();
    }

    public function testSavePreferencesUpdatesUser(): void
    {
        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', '/profile/notifications');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="_token"]')->attr('value');

        // Submit with borrow_requested email unchecked (omitted) and inApp checked
        $this->client->request('POST', '/profile/notifications', [
            '_token' => $token,
            'preferences' => [
                'borrow_requested' => ['inApp' => '1'],
                'borrow_approved' => ['email' => '1', 'inApp' => '1'],
                'borrow_denied' => ['email' => '1', 'inApp' => '1'],
                'borrow_handed_off' => ['email' => '1', 'inApp' => '1'],
                'borrow_returned' => ['email' => '1', 'inApp' => '1'],
                'borrow_overdue' => ['email' => '1', 'inApp' => '1'],
                'borrow_cancelled' => ['email' => '1', 'inApp' => '1'],
                'staff_assigned' => ['email' => '1', 'inApp' => '1'],
                'event_updated' => ['email' => '1', 'inApp' => '1'],
                'event_cancelled' => ['email' => '1', 'inApp' => '1'],
                'event_invited' => ['email' => '1', 'inApp' => '1'],
                'event_reminder' => ['email' => '1', 'inApp' => '1'],
            ],
        ]);

        self::assertResponseRedirects('/profile/notifications');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'notification preferences have been saved');

        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $repo->findOneBy(['email' => 'borrower@example.com']);

        self::assertFalse($user->isNotificationEnabled(NotificationType::BorrowRequested, 'email'));
        self::assertTrue($user->isNotificationEnabled(NotificationType::BorrowRequested, 'inApp'));
        self::assertTrue($user->isNotificationEnabled(NotificationType::BorrowApproved, 'email'));
    }

    public function testSaveWithInvalidCsrfTokenRedirects(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('POST', '/profile/notifications', [
            '_token' => 'invalid_token',
            'preferences' => [],
        ]);

        self::assertResponseRedirects('/profile/notifications');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'security token');
    }

    public function testAllCheckboxesCheckedByDefault(): void
    {
        $this->loginAs('organizer@example.com');

        $crawler = $this->client->request('GET', '/profile/notifications');
        self::assertResponseIsSuccessful();

        $checkboxes = $crawler->filter('input[type="checkbox"]');
        $total = $checkboxes->count();
        $checked = $crawler->filter('input[type="checkbox"][checked]')->count();

        // 12 types × 2 channels = 24 checkboxes, all checked
        self::assertSame(24, $total);
        self::assertSame(24, $checked);
    }
}
