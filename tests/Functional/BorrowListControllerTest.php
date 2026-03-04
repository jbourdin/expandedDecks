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
        // Admin has no borrows as a borrower (only as a deck owner/lender)
        $this->loginAs('admin@example.com');

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
        // Admin owns Iron Thorns and Ancient Box which have borrows — inbox mode
        self::assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
    }

    public function testLendListStatusFilter(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/lends?status=approved');

        self::assertResponseIsSuccessful();
        // Non-terminal status → inbox mode with event grouping
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
        self::assertSelectorTextContains('.text-muted', 'No active borrows');
    }

    // ---------------------------------------------------------------
    // /lends — Inbox mode (active borrows grouped by event)
    // ---------------------------------------------------------------

    public function testLendInboxShowsEventGroupedBorrows(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/lends');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Lends');
        // Two event groups: "Expanded Weekly #42" (today) and "Lyon Expanded Cup 2026" (future)
        $eventHeaders = $crawler->filter('.card-header-themed a');
        self::assertSame(2, $eventHeaders->count(), 'Should show 2 event groups.');
        // 3 borrows total: today (1 pending + 1 approved), future (1 pending)
        self::assertSame(3, $crawler->filter('table tbody tr')->count());
    }

    public function testLendInboxShowsInlineActionButtons(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/lends');

        self::assertResponseIsSuccessful();
        // Pending borrow should have Approve and Deny buttons
        self::assertSelectorExists('button.btn-success', 'Approve button should be visible.');
        self::assertSelectorExists('button.btn-outline-danger', 'Deny button should be visible.');
        // Approved borrow should have Hand off button
        self::assertSelectorExists('button.btn-primary', 'Hand off button should be visible.');
    }

    public function testLendInboxFilterByActiveStatus(): void
    {
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/lends?status=pending');

        self::assertResponseIsSuccessful();
        // Still in inbox mode (pending is non-terminal)
        self::assertSelectorExists('.card-header-themed');
        // 2 pending borrows across 2 events (1 at today + 1 at future)
        $rows = $crawler->filter('table tbody tr');
        self::assertSame(2, $rows->count(), 'Should show exactly 2 pending borrows.');
        foreach ($rows as $row) {
            self::assertStringContainsString('Pending', $row->textContent);
        }
    }

    public function testLendHistoryModeForTerminalStatus(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/lends?status=returned');

        self::assertResponseIsSuccessful();
        // History mode — should show the flat table or empty message, not inbox cards
        self::assertSelectorTextContains('.text-muted', 'No lend activity');
    }

    public function testApproveFromInboxRedirectsBackToLends(): void
    {
        $this->loginAs('admin@example.com');

        // First, get the inbox to find the pending borrow's approve form
        $crawler = $this->client->request('GET', '/lends');
        $approveForm = $crawler->selectButton('Approve')->form();
        $this->client->submit($approveForm);

        self::assertResponseRedirects('/lends');
    }

    public function testLendInboxEmptyForUserWithNoActiveDecks(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/lends');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.text-muted', 'No active borrows');
    }

    // ---------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------

    public function testBorrowListHandlesPageParameter(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('GET', '/borrows?page=1');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Borrows');
    }

    public function testLendListHandlesPageParameter(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/lends?page=1');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Lends');
    }

    public function testLendListPreservesStatusFilterInPagination(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/lends?status=approved&page=1');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Lends');
    }

    public function testBorrowListShowsResultCount(): void
    {
        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', '/borrows');

        self::assertResponseIsSuccessful();
        // Borrower has fixture borrows — should show "Showing X–Y of Z"
        $showingText = $crawler->filter('p.text-muted:contains("Showing")');
        self::assertGreaterThan(0, $showingText->count(), 'Result count should be displayed.');
    }
}
