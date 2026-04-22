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
 * Fetches and persists a single card from the TCGdex API.
 *
 * The setId is provided explicitly so the handler can resolve the parent
 * TcgdexSet entity without parsing the card ID.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
readonly class SyncTcgdexCardMessage
{
    public function __construct(
        public string $cardId,
        public string $setId,
        public SyncMode $mode = SyncMode::Insert,
    ) {
    }
}
