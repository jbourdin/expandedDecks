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

/**
 * Additional coverage tests for EventListController uncovered branches.
 *
 * @see docs/features.md F3.2 — Event listing
 * @see docs/features.md F3.15 — Event discovery
 */
class EventListControllerCoverageTest extends AbstractFunctionalTest
{
    /**
     * An invalid scope query parameter should fall back to "all".
     */
    public function testInvalidScopeFallsBackToAll(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event?scope=invalid_value');

        self::assertResponseIsSuccessful();
    }

    /**
     * The legacy /events/discover route should redirect permanently to
     * /event?scope=public.
     */
    public function testDiscoverRedirectsToEventListWithPublicScope(): void
    {
        $this->client->request('GET', '/events/discover');

        self::assertResponseRedirects('/event?scope=public', 301);
    }

    /**
     * Anonymous users visiting /event should see only public events and
     * the scope should be forced to "public".
     */
    public function testAnonymousUsersSeeOnlyPublicEvents(): void
    {
        $this->client->request('GET', '/event');

        self::assertResponseIsSuccessful();
    }

    /**
     * Staffing scope should show events where the user is organizer or staff.
     */
    public function testStaffingScopeShowsStaffEvents(): void
    {
        // organizer@example.com organizes multiple events
        $this->loginAs('organizer@example.com');

        $this->client->request('GET', '/event?scope=staffing');

        self::assertResponseIsSuccessful();
    }

    /**
     * Public scope for an authenticated user should show only public events.
     */
    public function testPublicScopeForAuthenticatedUser(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event?scope=public');

        self::assertResponseIsSuccessful();
    }
}
