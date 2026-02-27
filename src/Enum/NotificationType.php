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
 * @see docs/features.md F8.1 — Receive in-app notification
 */
enum NotificationType: string
{
    case BorrowRequested = 'borrow_requested';
    case BorrowApproved = 'borrow_approved';
    case BorrowDenied = 'borrow_denied';
    case BorrowHandedOff = 'borrow_handed_off';
    case BorrowReturned = 'borrow_returned';
    case BorrowOverdue = 'borrow_overdue';
    case BorrowCancelled = 'borrow_cancelled';
    case StaffAssigned = 'staff_assigned';
    case EventUpdated = 'event_updated';
    case EventCancelled = 'event_cancelled';
    case EventReminder = 'event_reminder';
}
