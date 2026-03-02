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

use App\Entity\Borrow;
use App\Enum\BorrowStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F4.1 — Request to borrow a deck
 */
class BorrowTest extends TestCase
{
    #[DataProvider('activeStatusProvider')]
    public function testIsActiveForEachStatus(BorrowStatus $status, bool $expected): void
    {
        $borrow = new Borrow();
        $borrow->setStatus($status);

        self::assertSame($expected, $borrow->isActive());
    }

    /**
     * @return iterable<string, array{BorrowStatus, bool}>
     */
    public static function activeStatusProvider(): iterable
    {
        yield 'pending is active' => [BorrowStatus::Pending, true];
        yield 'approved is active' => [BorrowStatus::Approved, true];
        yield 'lent is active' => [BorrowStatus::Lent, true];
        yield 'overdue is active' => [BorrowStatus::Overdue, true];
        yield 'returned is not active' => [BorrowStatus::Returned, false];
        yield 'returned_to_owner is not active' => [BorrowStatus::ReturnedToOwner, false];
        yield 'cancelled is not active' => [BorrowStatus::Cancelled, false];
    }

    #[DataProvider('cancellableStatusProvider')]
    public function testIsCancellableForEachStatus(BorrowStatus $status, bool $expected): void
    {
        $borrow = new Borrow();
        $borrow->setStatus($status);

        self::assertSame($expected, $borrow->isCancellable());
    }

    /**
     * @return iterable<string, array{BorrowStatus, bool}>
     */
    public static function cancellableStatusProvider(): iterable
    {
        yield 'pending is cancellable' => [BorrowStatus::Pending, true];
        yield 'approved is cancellable' => [BorrowStatus::Approved, true];
        yield 'lent is not cancellable' => [BorrowStatus::Lent, false];
        yield 'overdue is not cancellable' => [BorrowStatus::Overdue, false];
        yield 'returned is not cancellable' => [BorrowStatus::Returned, false];
        yield 'returned_to_owner is not cancellable' => [BorrowStatus::ReturnedToOwner, false];
        yield 'cancelled is not cancellable' => [BorrowStatus::Cancelled, false];
    }
}
