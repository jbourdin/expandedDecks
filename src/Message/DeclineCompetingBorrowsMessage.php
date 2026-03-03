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

namespace App\Message;

/**
 * @see docs/features.md F4.11 — Borrow conflict detection
 */
readonly class DeclineCompetingBorrowsMessage
{
    public function __construct(
        public int $borrowId,
    ) {
    }
}
