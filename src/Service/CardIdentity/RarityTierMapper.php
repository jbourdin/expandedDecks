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

namespace App\Service\CardIdentity;

/**
 * Maps TCGdex rarity strings to integer tiers for cost-based sorting.
 *
 * Tier 1 = cheapest/most common, Tier 6 = most expensive/rare.
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class RarityTierMapper
{
    /** @var array<string, int> */
    private const array RARITY_TIERS = [
        'Common' => 1,
        'None' => 1,
        'Uncommon' => 2,
        'One Diamond' => 2,
        'Rare' => 3,
        'Rare Holo' => 3,
        'Holo Rare' => 3,
        'Two Diamond' => 3,
        'Holo Rare V' => 4,
        'Holo Rare VMAX' => 4,
        'Holo Rare VSTAR' => 4,
        'Three Diamond' => 4,
        'Four Diamond' => 4,
        'ACE SPEC Rare' => 4,
        'Ultra Rare' => 5,
        'Full Art Trainer' => 5,
        'Rare Holo LV.X' => 5,
        'Rare PRIME' => 5,
        'Secret Rare' => 6,
        'Hyper rare' => 6,
        'Special illustration rare' => 6,
        'Illustration rare' => 6,
        'Shiny rare' => 6,
        'Shiny rare V' => 6,
        'Shiny rare VMAX' => 6,
        'Shiny Ultra Rare' => 6,
        'Crown' => 6,
        'Classic Collection' => 6,
        'LEGEND' => 6,
        'Amazing Rare' => 5,
        'Radiant Rare' => 5,
        'Black White Rare' => 3,
        'Double rare' => 4,
        'Mega Hyper Rare' => 6,
        'One Shiny' => 5,
        'Two Shiny' => 6,
        'One Star' => 5,
        'Two Star' => 6,
        'Three Star' => 6,
    ];

    public function map(?string $rarity): int
    {
        if (null === $rarity || '' === $rarity) {
            return 6;
        }

        return self::RARITY_TIERS[$rarity] ?? 6;
    }
}
