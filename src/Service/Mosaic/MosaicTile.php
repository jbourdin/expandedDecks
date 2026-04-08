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

namespace App\Service\Mosaic;

use App\Entity\CardPrinting;

/**
 * Lightweight DTO representing a single tile in a mosaic grid.
 *
 * Used by MosaicGenerator when rendering from merged/overridden card data
 * instead of directly from DeckCard entities.
 *
 * @see docs/features.md F6.6b — Minified mosaic
 */
readonly class MosaicTile
{
    public function __construct(
        public string $cardName,
        public int $quantity,
        public ?string $imageUrl,
        public string $cardType,
        public ?string $trainerSubtype,
        public ?CardPrinting $printing = null,
    ) {
    }
}
