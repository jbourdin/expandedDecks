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

namespace App\Constants;

/**
 * Hotness scale for cards — editor-curated relevance rating.
 *
 * Currently used only by {@see \App\Entity\StapleCard::hotness}. The exact scale
 * (1-10, 0-100, named tiers) is deferred to issue #437; this class commits only
 * to the named threshold so call sites stay scale-agnostic.
 *
 * Future #437 work will likely promote `hotness` to a cross-card axis on
 * `CardIdentity` (or a join table for multi-channel ratings); the constant
 * moves with the scale, every call site stays correct.
 *
 * @see https://github.com/jbourdin/expandedDecks/issues/532 — F6.15 staple cards
 * @see https://github.com/jbourdin/expandedDecks/issues/437 — expanded card watchlist
 */
final class CardHotness
{
    /**
     * Default hotness for new staples and the default minimum on the public list filter.
     * Editors can promote (above) or demote (below) any individual staple from the admin form.
     */
    public const STAPLE_THRESHOLD = 5;
}
