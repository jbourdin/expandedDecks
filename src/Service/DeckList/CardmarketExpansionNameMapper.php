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

namespace App\Service\DeckList;

/**
 * Maps PTCG set codes to Cardmarket expansion names.
 *
 * Cardmarket's "Add Decklist to Wants" textarea expects the full English
 * expansion name (e.g. "Brilliant Stars"), not the abbreviated PTCG code
 * (e.g. "BRS"). This service provides the mapping for all Expanded-legal sets.
 *
 * @see docs/features.md F6.11 — Export deck list for Cardmarket wishlist
 */
class CardmarketExpansionNameMapper
{
    /**
     * PTCG set code → Cardmarket expansion name.
     *
     * Covers all sets from Black & White (BLW, 2011) onward,
     * including promo and special sets encountered in the card database.
     *
     * @var array<string, string>
     */
    private const array PTCG_CODE_TO_EXPANSION_NAME = [
        // Black & White era
        'BLW' => 'Black & White',
        'EPO' => 'Emerging Powers',
        'NVI' => 'Noble Victories',
        'NXD' => 'Next Destinies',
        'DEX' => 'Dark Explorers',
        'DRV' => 'Dragon Vault',
        'BCR' => 'Boundaries Crossed',
        'PLS' => 'Plasma Storm',
        'PLF' => 'Plasma Freeze',
        'PLB' => 'Plasma Blast',
        'LTR' => 'Legendary Treasures',

        // XY era
        'XY' => 'XY',
        'FLF' => 'Flashfire',
        'FFI' => 'Furious Fists',
        'PHF' => 'Phantom Forces',
        'PRC' => 'Primal Clash',
        'ROS' => 'Roaring Skies',
        'AOR' => 'Ancient Origins',
        'BKT' => 'BREAKthrough',
        'BKP' => 'BREAKpoint',
        'FCO' => 'Fates Collide',
        'STS' => 'Steam Siege',
        'EVO' => 'Evolutions',
        'GEN' => 'Generations',

        // Sun & Moon era
        'SUM' => 'Sun & Moon',
        'GRI' => 'Guardians Rising',
        'BUS' => 'Burning Shadows',
        'SLG' => 'Shining Legends',
        'CIN' => 'Crimson Invasion',
        'UPR' => 'Ultra Prism',
        'FLI' => 'Forbidden Light',
        'CES' => 'Celestial Storm',
        'LOT' => 'Lost Thunder',
        'TEU' => 'Team Up',
        'UNB' => 'Unbroken Bonds',
        'UNM' => 'Unified Minds',
        'HIF' => 'Hidden Fates',
        'CEC' => 'Cosmic Eclipse',
        'PR-SM' => 'SM Black Star Promos',

        // Sword & Shield era
        'SSH' => 'Sword & Shield',
        'RCL' => 'Rebel Clash',
        'DAA' => 'Darkness Ablaze',
        'CPA' => "Champion's Path",
        'VIV' => 'Vivid Voltage',
        'SHF' => 'Shining Fates',
        'BST' => 'Battle Styles',
        'CRE' => 'Chilling Reign',
        'EVS' => 'Evolving Skies',
        'CEL' => 'Celebrations',
        'FST' => 'Fusion Strike',
        'BRS' => 'Brilliant Stars',
        'ASR' => 'Astral Radiance',
        'PGO' => 'Pokémon GO',
        'LOR' => 'Lost Origin',
        'SIT' => 'Silver Tempest',
        'CRZ' => 'Crown Zenith',
        'PR-SW' => 'SWSH Black Star Promos',

        // Scarlet & Violet era
        'SVI' => 'Scarlet & Violet',
        'PAL' => 'Paldea Evolved',
        'MEG' => '151',
        'OBF' => 'Obsidian Flames',
        'PAR' => 'Paradox Rift',
        'PAF' => 'Paldean Fates',
        'TEF' => 'Temporal Forces',
        'TWM' => 'Twilight Masquerade',
        'SFA' => 'Shrouded Fable',
        'SCR' => 'Stellar Crown',
        'SSP' => 'Surging Sparks',
        'PRE' => 'Prismatic Evolutions',
        'JTG' => 'Journey Together',
        'PR-SV' => 'SV Black Star Promos',

        // Special / compilation sets
        'KSS' => 'Kangaskhan-GX Box',
        'ASC' => 'Astral Radiance',
        'BLK' => 'Black & White',

        // Energy sets (kept for completeness, though basic energies are excluded)
        'MEE' => 'Match Energy Set',
        'SVE' => 'Scarlet & Violet Energies',
        'SME' => 'Sun & Moon Energies',
        'XYE' => 'XY Energies',
        'BWE' => 'Black & White Energies',

        // Older sets that may appear in cross-printing lookups
        'N1' => 'Neo Genesis',
        'EM' => 'EX Emerald',
        'HP' => 'EX Holon Phantoms',
        'LM' => 'EX Legend Maker',
        'GE' => 'Great Encounters',
        'MD' => 'Majestic Dawn',
        'MT' => 'Mysterious Treasures',
        'UL' => 'HS—Unleashed',
        'SS' => 'EX Sandstorm',
        'P5' => 'Pop Series 5',
        'P8' => 'Pop Series 8',
        'TK3L' => 'Trainer Kit: Lycanroc & Alolan Raichu',
    ];

    /**
     * Get the Cardmarket expansion name for a PTCG set code.
     *
     * @return string|null The expansion name, or null if the set code is unknown
     */
    public function getExpansionName(string $setCode): ?string
    {
        return self::PTCG_CODE_TO_EXPANSION_NAME[$setCode] ?? null;
    }
}
