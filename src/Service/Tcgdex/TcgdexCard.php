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

namespace App\Service\Tcgdex;

/**
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 * @see docs/features.md F6.10 — Card identity and printing model
 */
readonly class TcgdexCard
{
    /**
     * @param list<string> $attacks
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $category,
        public ?string $trainerType,
        public ?string $imageUrl,
        public bool $isExpandedLegal,
        public ?int $hp = null,
        public array $attacks = [],
        public ?string $rarity = null,
        public ?string $setReleaseDate = null,
        public ?string $setCode = null,
        public ?string $cardNumber = null,
        public ?int $priceInCents = null,
        public ?int $cardmarketProductId = null,
        public ?int $tcgplayerProductId = null,
    ) {
    }
}
