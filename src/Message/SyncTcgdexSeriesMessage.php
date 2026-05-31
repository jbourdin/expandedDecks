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
 * Triggers a full incremental sync of the TCGdex database (root entry point).
 *
 * Dispatched by the CLI command or admin dashboard. The handler fetches all series
 * from the API and dispatches SyncTcgdexSerieMessage for each.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
readonly class SyncTcgdexSeriesMessage
{
    public function __construct(
        public SyncMode $mode = SyncMode::Sync,
    ) {
    }
}
