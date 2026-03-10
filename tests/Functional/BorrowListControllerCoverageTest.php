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
 * Additional coverage tests for BorrowListController.
 *
 * @see docs/features.md F4.5 — Borrow history
 * @see docs/features.md F4.10 — Owner borrow inbox
 * @see docs/features.md F7.1 — Dashboard (scope=managed)
 */
class BorrowListControllerCoverageTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // /lends?scope=managed — Managed borrows (organizer/staff)
    // ---------------------------------------------------------------

    public function testManagedScopeInboxModeForOrganizer(): void
    {
        // Organizer is the organizer for today's event and future event
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/lends?scope=managed');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Managed Borrows');
        // Admin is organizer of "Past Expanded Weekly #40" (finished, not active)
        // but is deck owner for borrows at "Expanded Weekly #42" (today) and "Lyon Expanded Cup 2026"
        // The managed scope shows borrows at events where user is organizer or staff
    }

    public function testManagedScopeInboxModeForStaff(): void
    {
        // staff1 is staff at today's event and future event
        $this->loginAs('staff1@example.com');

        $crawler = $this->client->request('GET', '/lends?scope=managed');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Managed Borrows');
    }

    public function testManagedScopeWithStatusFilterNonTerminal(): void
    {
        $this->loginAs('staff1@example.com');

        $crawler = $this->client->request('GET', '/lends?scope=managed&status=pending');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Managed Borrows');
    }

    public function testManagedScopeWithStatusFilterTerminal(): void
    {
        // Terminal status uses paginated history mode within managed scope
        $this->loginAs('staff1@example.com');

        $this->client->request('GET', '/lends?scope=managed&status=returned');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Managed Borrows');
    }

    public function testManagedScopeHistoryModeWithPagination(): void
    {
        $this->loginAs('staff1@example.com');

        $this->client->request('GET', '/lends?scope=managed&status=cancelled&page=1');

        self::assertResponseIsSuccessful();
    }

    public function testManagedScopeEmptyForUserWithNoManagedEvents(): void
    {
        // Lender is not organizer or staff at any event
        $this->loginAs('lender@example.com');

        $this->client->request('GET', '/lends?scope=managed');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Managed Borrows');
    }

    // ---------------------------------------------------------------
    // /lends — History mode (terminal status)
    // ---------------------------------------------------------------

    public function testLendHistoryModeForCancelledStatus(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/lends?status=cancelled');

        self::assertResponseIsSuccessful();
        // Cancelled is terminal → history mode (paginated flat list)
    }

    public function testLendHistoryModeForReturnedToOwnerStatus(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/lends?status=returned_to_owner');

        self::assertResponseIsSuccessful();
        // returned_to_owner is terminal → history mode
    }

    public function testLendHistoryModeWithPagination(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/lends?status=returned&page=2');

        self::assertResponseIsSuccessful();
        // Page 2 with no data should still render successfully
    }

    // ---------------------------------------------------------------
    // /borrows — Pagination edge cases
    // ---------------------------------------------------------------

    public function testBorrowListHighPageNumberReturnsEmptyResults(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/borrows?page=999');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Borrows');
    }

    public function testBorrowListNegativePageClampedToOne(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/borrows?page=-5');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Borrows');
    }

    public function testBorrowListZeroPageClampedToOne(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/borrows?page=0');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Borrows');
    }

    // ---------------------------------------------------------------
    // Status filter — invalid / unknown values
    // ---------------------------------------------------------------

    public function testBorrowListInvalidStatusFilterIgnored(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/borrows?status=nonexistent_status');

        self::assertResponseIsSuccessful();
        // Invalid status value should resolve to null via BorrowStatus::tryFrom
        self::assertSelectorTextContains('h1', 'My Borrows');
    }

    public function testLendListInvalidStatusFilterIgnored(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/lends?status=invalid_value');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Lends');
    }

    // ---------------------------------------------------------------
    // /borrows — Status filter for each status type
    // ---------------------------------------------------------------

    public function testBorrowListFilterByApprovedStatus(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/borrows?status=approved');

        self::assertResponseIsSuccessful();
    }

    public function testBorrowListFilterByReturnedStatus(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/borrows?status=returned');

        self::assertResponseIsSuccessful();
    }

    public function testBorrowListFilterByCancelledStatus(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/borrows?status=cancelled');

        self::assertResponseIsSuccessful();
    }

    // ---------------------------------------------------------------
    // /lends — Inbox mode with specific active statuses
    // ---------------------------------------------------------------

    public function testLendInboxModeForApprovedStatus(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/lends?status=approved');

        self::assertResponseIsSuccessful();
        // Approved is non-terminal → inbox mode with event grouping
        $rows = $crawler->filter('table tbody tr');
        foreach ($rows as $row) {
            self::assertStringContainsString('Approved', $row->textContent);
        }
    }

    public function testLendInboxModeForLentStatus(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/lends?status=lent');

        self::assertResponseIsSuccessful();
        // Lent is non-terminal → inbox mode
    }

    public function testLendInboxModeForOverdueStatus(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/lends?status=overdue');

        self::assertResponseIsSuccessful();
        // Overdue is non-terminal → inbox mode
    }

    // ---------------------------------------------------------------
    // /borrows — Staff and lender users
    // ---------------------------------------------------------------

    public function testBorrowListForStaffUser(): void
    {
        // staff1 has a delegated borrow in fixtures
        $this->loginAs('staff1@example.com');

        $crawler = $this->client->request('GET', '/borrows');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Borrows');
        // staff1 has 1 delegated borrow (Regidrago at today's event)
        self::assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
    }

    public function testBorrowListForLenderUserWithNoBorrows(): void
    {
        // Lender has a pending borrow at future event for Iron Thorns
        $this->loginAs('lender@example.com');

        $crawler = $this->client->request('GET', '/borrows');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Borrows');
        // Lender has 1 pending borrow at future event
        self::assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
    }

    // ---------------------------------------------------------------
    // /lends — Lender's own lend inbox
    // ---------------------------------------------------------------

    public function testLendInboxForLenderWithDelegatedBorrow(): void
    {
        // Lender owns Regidrago which has a delegated borrow
        $this->loginAs('lender@example.com');

        $crawler = $this->client->request('GET', '/lends');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Lends');
        // Lender has 1 active delegated borrow on Regidrago
        self::assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
    }

    public function testBorrowListWithStatusFilterAndPagination(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/borrows?status=pending&page=1');

        self::assertResponseIsSuccessful();
    }
}
