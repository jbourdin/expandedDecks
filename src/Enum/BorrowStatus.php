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

namespace App\Enum;

/**
 * @see docs/features.md F4.1 — Request to borrow a deck
 */
enum BorrowStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Lent = 'lent';
    case Returned = 'returned';
    case ReturnedToOwner = 'returned_to_owner';
    case Cancelled = 'cancelled';
    case Overdue = 'overdue';
}
