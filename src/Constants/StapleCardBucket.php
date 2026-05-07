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
 * Display buckets for staple cards. Each {@see \App\Entity\StapleCard} lands in
 * exactly one bucket via priority assignment at enrichment time:
 *
 *   if cardIdentity.ruleboxType === RuleboxType::ACE_SPEC: ACE_SPEC
 *   elif cardIdentity.category === 'pokemon':              POKEMON
 *   elif cardIdentity.category === 'energy':               ENERGY
 *   elif cardIdentity.trainerType === 'Supporter':         SUPPORTER
 *   elif cardIdentity.trainerType === 'Item':              ITEM
 *   elif cardIdentity.trainerType === 'Tool':              TOOL
 *   elif cardIdentity.trainerType === 'Stadium':           STADIUM
 *
 * Ace Spec wins over the type-based buckets so editors curating Ace Specs see
 * them gathered, not scattered across four trainer-type buckets.
 *
 * Display order in the UI (admin tabs and public sections) is given by
 * {@see self::ORDER}: regular cards first, Ace Spec last as the specialty section.
 *
 * @see https://github.com/jbourdin/expandedDecks/issues/532 — F6.15 staple cards
 */
final class StapleCardBucket
{
    public const POKEMON = 'pokemon';

    public const SUPPORTER = 'supporter';

    public const ITEM = 'item';

    public const TOOL = 'tool';

    public const STADIUM = 'stadium';

    public const ENERGY = 'energy';

    public const ACE_SPEC = 'ace_spec';

    /**
     * Display order — Pokémon first, trainer subtypes in canonical rulebook order,
     * Energy next, Ace Spec last as the specialty section.
     *
     * @var list<string>
     */
    public const ORDER = [
        self::POKEMON,
        self::SUPPORTER,
        self::ITEM,
        self::TOOL,
        self::STADIUM,
        self::ENERGY,
        self::ACE_SPEC,
    ];

    public static function isValid(string $bucket): bool
    {
        return \in_array($bucket, self::ORDER, true);
    }
}
