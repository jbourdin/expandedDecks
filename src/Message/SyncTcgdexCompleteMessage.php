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
 * Dispatched after the sync cascade finishes. Triggers post-sync actions:
 * rebuild set mappings (BuildSetMappingsMessage) and re-enrich failed deck
 * versions (EnrichDeckVersionMessage).
 *
 * Routed to the deck_enrichment transport (not tcgdex_sync).
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
readonly class SyncTcgdexCompleteMessage
{
}
