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

class AdminTechnicalControllerTest extends AbstractFunctionalTest
{
    public function testDashboardRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/technical');

        self::assertResponseRedirects('/login');
    }

    public function testDashboardRequiresTechnicalAdminRole(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/admin/technical');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDashboardAccessibleForAdmin(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/admin/technical');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardShowsSetMappingCount(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/admin/technical');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('html:contains("Set Mappings")')->count(), 'Dashboard should contain "Set Mappings" text.');
    }

    public function testSetMappingsRebuildRequiresCsrfToken(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('POST', '/admin/technical/set-mappings-rebuild', [
            '_token' => 'invalid-token',
        ]);

        // Invalid CSRF redirects back to dashboard with a flash
        self::assertResponseRedirects('/admin/technical');
    }

    public function testEnrichRetryRequiresAuthentication(): void
    {
        $this->client->request('POST', '/admin/technical/enrich-retry');

        self::assertResponseRedirects('/login');
    }

    public function testFlushReenrichRequiresAuthentication(): void
    {
        $this->client->request('POST', '/admin/technical/flush-reenrich');

        self::assertResponseRedirects('/login');
    }
}
