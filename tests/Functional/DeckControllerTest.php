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
 * @see docs/features.md F2.3 — Detail view
 * @see docs/features.md F4.5 — Borrow history
 */
class DeckControllerTest extends AbstractFunctionalTest
{
    public function testDeckShowDisplaysBorrowActivityForOwner(): void
    {
        $this->loginAs('admin@example.com');

        // Iron Thorns is owned by admin and has borrows in fixtures
        $crawler = $this->client->request('GET', '/deck/1');

        self::assertResponseIsSuccessful();
        // Owner sees the borrow activity card with borrows table
        $borrowActivityHeaders = $crawler->filter('.card-header:contains("Borrow Activity")');
        self::assertGreaterThan(0, $borrowActivityHeaders->count(), 'Borrow Activity card should be present.');
    }

    public function testDeckShowDisplaysBorrowActivityForBorrower(): void
    {
        $this->loginAs('borrower@example.com');

        // Iron Thorns — borrower has a pending borrow for this deck
        $crawler = $this->client->request('GET', '/deck/1');

        self::assertResponseIsSuccessful();
        $borrowActivityHeaders = $crawler->filter('.card-header:contains("Borrow Activity")');
        self::assertGreaterThan(0, $borrowActivityHeaders->count(), 'Borrow Activity card should be present.');
    }

    public function testDeckShowDoesNotShowBorrowFormForOwner(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/deck/1');

        self::assertResponseIsSuccessful();
        // Owner should NOT see a borrow request form (can't borrow own deck)
        self::assertSelectorNotExists('#borrow_event');
    }

    public function testDeckShowShowsBorrowFormForEligibleUser(): void
    {
        $this->loginAs('borrower@example.com');

        // Lugia VSTAR is owned by lender, status available, borrower has engagement
        $crawler = $this->client->request('GET', '/deck/3');

        self::assertResponseIsSuccessful();
        // Borrower should see the event dropdown to request a borrow
        $eventSelect = $crawler->filter('#borrow_event');
        self::assertGreaterThan(0, $eventSelect->count(), 'Borrow event dropdown should be present for eligible user.');
    }
}
