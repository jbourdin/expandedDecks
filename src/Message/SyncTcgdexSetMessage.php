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

use App\Enum\SyncMode;

/**
 * Syncs a single TCGdex set: detects missing cards and dispatches
 * SyncTcgdexCardMessage for each.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
readonly class SyncTcgdexSetMessage
{
    public function __construct(
        public string $setId,
        public SyncMode $mode = SyncMode::Insert,
    ) {
    }
}
