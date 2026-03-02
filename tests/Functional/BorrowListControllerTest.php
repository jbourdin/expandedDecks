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
 * @see docs/features.md F4.5 — Borrow history
 * @see docs/features.md F4.10 — Owner borrow inbox
 */
class BorrowListControllerTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // /borrows — Borrow history
    // ---------------------------------------------------------------

    public function testBorrowListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/borrows');

        self::assertResponseRedirects('/login');
    }

    public function testBorrowListShowsBorrowerActivity(): void
    {
        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', '/borrows');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Borrows');
        // Borrower has fixture borrows (Iron Thorns pending, Ancient Box approved)
        self::assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
    }

    public function testBorrowListStatusFilter(): void
    {
        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', '/borrows?status=pending');

        self::assertResponseIsSuccessful();
        // Should show only pending borrows
        $rows = $crawler->filter('table tbody tr');
        foreach ($rows as $row) {
            self::assertStringContainsString('Pending', $row->textContent);
        }
    }

    public function testBorrowListEmptyForUserWithNoBorrows(): void
    {
        $this->loginAs('lender@example.com');

        $this->client->request('GET', '/borrows');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.text-muted', 'No borrow activity');
    }

    // ---------------------------------------------------------------
    // /lends — Lend history
    // ---------------------------------------------------------------

    public function testLendListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/lends');

        self::assertResponseRedirects('/login');
    }

    public function testLendListShowsOwnerActivity(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/lends');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Lends');
        // Admin owns Iron Thorns and Ancient Box which have borrows
        self::assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
    }

    public function testLendListStatusFilter(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/lends?status=approved');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('table tbody tr');
        foreach ($rows as $row) {
            self::assertStringContainsString('Approved', $row->textContent);
        }
    }

    public function testLendListEmptyForUserWithNoDecks(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/lends');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.text-muted', 'No lend activity');
    }
}
