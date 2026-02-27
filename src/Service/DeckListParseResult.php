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
 */
readonly class DeckListParseResult
{
    /**
     * @param list<ParsedCard>   $cards
     * @param list<string>       $errors
     * @param array<string, int> $sectionTotals
     */
    public function __construct(
        public array $cards,
        public array $errors,
        public array $sectionTotals,
    ) {
    }

    public function isValid(): bool
    {
        return [] === $this->errors && [] !== $this->cards;
    }

    public function totalCards(): int
    {
        $total = 0;

        foreach ($this->cards as $card) {
            $total += $card->quantity;
        }

        return $total;
    }
}
