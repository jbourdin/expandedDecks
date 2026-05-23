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

namespace App\Service;

/**
 * @see docs/features.md F6.1 — Parse PTCG text format
 * @see docs/features.md F2.28 — Preserve imported list order
 */
readonly class ParsedCard
{
    /**
     * @param int $sortOrder zero-based line index in the source rawList,
     *                       preserved so DeckCard can render cards in
     *                       import order (F2.28). Defaults to 0 for
     *                       backward compatibility with constructors
     *                       that don't supply a line index.
     */
    public function __construct(
        public int $quantity,
        public string $cardName,
        public string $setCode,
        public string $cardNumber,
        public string $cardType,
        public int $sortOrder = 0,
    ) {
    }
}
