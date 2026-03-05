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
 * @see docs/features.md F2.14 — Deck event status overview
 */
enum DeckEventStatus: string
{
    case Played = 'played';
    case ActivelyBorrowed = 'actively_borrowed';
    case DelegatedToStaff = 'delegated_to_staff';
    case Registered = 'registered';
    case NotRegistered = 'not_registered';
}
