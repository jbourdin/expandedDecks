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
readonly class ParsedCard
{
    public function __construct(
        public int $quantity,
        public string $cardName,
        public string $setCode,
        public string $cardNumber,
        public string $cardType,
    ) {
    }
}
