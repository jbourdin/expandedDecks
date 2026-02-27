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
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
readonly class EnrichDeckVersionMessage
{
    public function __construct(
        public int $deckVersionId,
    ) {
    }
}
