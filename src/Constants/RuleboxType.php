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
 * Rulebox types — Pokémon TCG card mechanics that print a "When this is Knocked Out…" rule box
 * (or are otherwise marked as a special rulebox card).
 *
 * Stored on `CardIdentity.ruleboxType` as a nullable string. Null means a regular card with no rulebox.
 *
 * Detection is per-type and not uniform: most are name-pattern based (suffix or prefix), Ace Spec
 * is rarity-based. As of PR-1 (issue #532), only ACE_SPEC is auto-detected by `CardIdentityResolver`;
 * the others are listed here as the canonical taxonomy and will be populated by follow-up PRs.
 *
 * Naming-pattern reference (case-sensitive — modern `ex` and classic `EX` collide if folded;
 * patterns with ambiguity must be checked in priority order, most-specific first):
 *
 *   ACE_SPEC              rarity = "ACE SPEC Rare" on any printing of the identity
 *   POKEMON_MEGA          name prefix "Mega "   — modern Mega Pokémon ex (e.g. "Mega Charizard X ex").
 *                                                 Coexists with the " ex" suffix; check this BEFORE POKEMON_EX
 *                                                 or modern Megas get misclassified as plain ex.
 *   POKEMON_MEGA_CLASSIC  stage = "MEGA" (most reliable signal) OR name prefix "M " (single capital M + space,
 *                         e.g. "M Charizard EX"). XY-era Mega Evolution. Coexists with " EX" / "-EX" suffix;
 *                         check BEFORE POKEMON_EX_CLASSIC.
 *   POKEMON_EX            name suffix " ex"            (lowercase, modern Scarlet & Violet era plain ex)
 *   POKEMON_V             name suffix " V"
 *   POKEMON_VMAX          name suffix " VMAX"
 *   POKEMON_VSTAR         name suffix " VSTAR"
 *   POKEMON_GX            name suffix " GX"
 *   POKEMON_EX_CLASSIC    name contains "-EX" or " EX"  (uppercase, Ruby & Sapphire era plain EX)
 *   POKEMON_G             name suffix " G"             (Team Galactic Pokémon, Platinum era)
 *   POKEMON_BREAK         name suffix " BREAK"
 *   POKEMON_RADIANT       name prefix "Radiant "       (e.g. "Radiant Charizard") — NOT a suffix
 *   PRISM_STAR            name suffix " ◇"             (diamond/prism-star symbol; verify TCGdex encoding)
 *
 * Constants are stored as snake_case strings rather than a PHP enum so the database column stays
 * a plain VARCHAR — adding a new rulebox type later is a single new constant + a detector branch,
 * with no schema change.
 */
final class RuleboxType
{
    public const ACE_SPEC = 'ace_spec';

    public const POKEMON_EX = 'pokemon_ex';

    public const POKEMON_V = 'pokemon_v';

    public const POKEMON_VMAX = 'pokemon_vmax';

    public const POKEMON_VSTAR = 'pokemon_vstar';

    public const POKEMON_GX = 'pokemon_gx';

    public const POKEMON_EX_CLASSIC = 'pokemon_ex_classic';

    public const POKEMON_G = 'pokemon_g';

    public const POKEMON_BREAK = 'pokemon_break';

    public const POKEMON_MEGA = 'pokemon_mega';

    public const POKEMON_MEGA_CLASSIC = 'pokemon_mega_classic';

    public const POKEMON_RADIANT = 'pokemon_radiant';

    public const PRISM_STAR = 'prism_star';
}
