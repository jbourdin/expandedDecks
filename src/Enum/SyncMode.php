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
 * Controls how the incremental TCGdex sync treats existing data.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
 */
enum SyncMode: string
{
    /**
     * Gap-fill cascade (default). Walks the whole catalogue (series → sets → cards),
     * inserts anything missing, and fetches only the locales an existing card still
     * lacks. A card whose every configured locale is already populated is skipped
     * with no HTTP call.
     */
    case Sync = 'sync';

    /**
     * Targeted re-fetch of a single set. Re-fetches every card in the set across
     * every configured locale unconditionally, merging into the JSON columns, and
     * picks up any card the set gained since the last sync.
     */
    case ForceUpdate = 'force_update';
}
