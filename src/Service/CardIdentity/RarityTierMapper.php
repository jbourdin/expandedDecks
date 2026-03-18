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
 * Tier 1 = cheapest/most common, Tier 6 = most expensive/rare, Tier 7 = unknown.
 * Sets with unreliable rarity data are blacklisted and always return UNKNOWN_TIER.
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class RarityTierMapper
{
    /**
     * TCGdex set IDs with unreliable rarity data.
     *
     * Shiny Vault / Yellow A sets mark every card as "Common" despite containing
     * full-art and shiny rares. Promo sets have inconsistent or meaningless rarity.
     * Trainer kits, McDonald's promos, and other special sets are not standard product.
     */
    private const array UNRELIABLE_RARITY_SETS = [
        // Shiny Vault / Yellow A Alternate — all cards marked Common
        'sma', 'xya',
        // Promo sets — rarity is inconsistent/meaningless
        'svp', 'swshp', 'smp', 'xyp', 'bwp', 'dpp', 'hgssp', 'np', 'basep', 'wp', 'mep', 'P-A',
        // Trainer kits — not standard booster product
        'tk-bw-e', 'tk-bw-z', 'tk-dp-l', 'tk-dp-m', 'tk-ex-latia', 'tk-ex-latio',
        'tk-ex-m', 'tk-ex-p', 'tk-hs-g', 'tk-hs-r', 'tk-sm-l', 'tk-sm-r',
        'tk-xy-b', 'tk-xy-latia', 'tk-xy-latio', 'tk-xy-n', 'tk-xy-p', 'tk-xy-su', 'tk-xy-sy', 'tk-xy-w',
        // McDonald's promos
        '2011bw', '2012bw', '2014xy', '2015xy', '2016xy', '2017sm', '2018sm', '2019sm', '2021swsh',
        // Other special sets
        'cel25', 'det1', 'fut2020', 'rc', 'exu', 'lc',
        // POP series
        'pop1', 'pop2', 'pop3', 'pop4', 'pop5', 'pop6', 'pop7', 'pop8', 'pop9',
    ];
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

    /** Tier for unknown or unmapped rarities — treated as rarest to avoid false budget picks. */
    public const int UNKNOWN_TIER = 7;

    /**
     * @param string|null $rarity the rarity string from TCGdex
     * @param string|null $setId  the TCGdex set ID (e.g. 'sma', 'swshp') — used to check the blacklist
     */
    public function map(?string $rarity, ?string $setId = null): int
    {
        if (null !== $setId && \in_array($setId, self::UNRELIABLE_RARITY_SETS, true)) {
            return self::UNKNOWN_TIER;
        }

        if (null === $rarity || '' === $rarity) {
            return self::UNKNOWN_TIER;
        }

        return self::RARITY_TIERS[$rarity] ?? self::UNKNOWN_TIER;
    }
}
