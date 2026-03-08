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

    public function testOrganizerSeesStatsOverview(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.card .fs-2.fw-bold.text-primary');
        self::assertSelectorExists('.card .fs-2.fw-bold.text-success');
        self::assertSelectorExists('.card .fs-2.fw-bold.text-info');
    }

    public function testRegularUserDoesNotSeeStats(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.card .fs-2.fw-bold.text-primary');
    }

    public function testStatsShowNumericValues(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/dashboard');

        $statCards = $crawler->filter('.card .fs-2.fw-bold');
        self::assertGreaterThanOrEqual(4, $statCards->count());

        // Each stat should contain a numeric value
        for ($i = 0; $i < 4; ++$i) {
            self::assertMatchesRegularExpression('/^\d+$/', trim($statCards->eq($i)->text()));
        }
    }

    public function testStatsLabelsAreTranslated(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/dashboard');

        $html = $crawler->html();
        self::assertStringContainsString('Total decks', $html);
        self::assertStringContainsString('Active borrows', $html);
        self::assertStringContainsString('Upcoming events', $html);
        self::assertStringContainsString('Overdue returns', $html);
    }
}
