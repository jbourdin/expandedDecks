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
 * Controls the depth of the incremental TCGdex sync.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
enum SyncMode: string
{
    /** Only create missing entities (series, sets, cards). Default. */
    case Insert = 'insert';

    /** Create missing + update existing series and sets metadata (logos, names, card counts). Cards are only inserted, not updated. */
    case Update = 'update';

    /** Create missing + update all entities including existing cards (re-fetch and overwrite). */
    case Full = 'full';
}
