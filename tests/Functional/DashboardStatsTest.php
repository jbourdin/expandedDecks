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
 * @see docs/features.md F7.1 — Dashboard
 */
class DashboardStatsTest extends AbstractFunctionalTest
{
    public function testDashboardRequiresAuthentication(): void
    {
        $this->client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }

    public function testOrganizerSeesPersonalStatsOverview(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.card .fs-2.fw-bold');
    }

    public function testStatsShowNumericValues(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/dashboard');

        $statCards = $crawler->filter('.card .fs-2.fw-bold');
        // 4 personal stats only (no global stats)
        self::assertGreaterThanOrEqual(4, $statCards->count());

        for ($i = 0; $i < 4; ++$i) {
            self::assertMatchesRegularExpression('/^\d+$/', trim($statCards->eq($i)->text()));
        }
    }

    public function testPersonalStatsLabelsAreTranslated(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/dashboard');

        $html = $crawler->html();
        self::assertStringContainsString('My events', $html);
        self::assertStringContainsString('Registered decks', $html);
    }

    public function testRegularUserDoesNotSeePersonalStats(): void
    {
        // Lender has no organizer role and is not staff at any event
        $this->loginAs('lender@example.com');

        $crawler = $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        $html = $crawler->html();
        self::assertStringNotContainsString('Registered decks', $html);
    }

    public function testStaffUserSeesPersonalStats(): void
    {
        // Borrower is staff at the today event in fixtures
        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        $html = $crawler->html();
        self::assertStringContainsString('Registered decks', $html);
    }
}
