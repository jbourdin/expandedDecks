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
    case EventInvited = 'event_invited';
    case EventReminder = 'event_reminder';
    case EventEndingPhase = 'event_ending_phase';
    case EventCustodyPickup = 'event_custody_pickup';
    case EventTransferRequested = 'event_transfer_requested';
    case EventTransferAccepted = 'event_transfer_accepted';
    case EventTransferDeclined = 'event_transfer_declined';
    case DeckFound = 'deck_found';

    public function isBorrowType(): bool
    {
        return match ($this) {
            self::BorrowRequested,
            self::BorrowApproved,
            self::BorrowDenied,
            self::BorrowHandedOff,
            self::BorrowReturned,
            self::BorrowOverdue,
            self::BorrowCancelled => true,
            default => false,
        };
    }
}
