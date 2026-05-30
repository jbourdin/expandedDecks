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

namespace App\Entity;

use Doctrine\ORM\Event\PreUpdateEventArgs;

/**
 * Lets a `#[ORM\PreUpdate]` hook distinguish a "real" content update from a
 * purely structural one (display ordering). Reordering an entity should not be
 * advertised as a content change to users or search engines, so timestamp
 * stamping is skipped when `position` is the only field that moved.
 *
 * @see docs/features.md F18.11 — Archetype relevance ordering
 * @see docs/features.md F18.19 — Archetype variant ordering
 */
trait StructuralChangeTrait
{
    /**
     * Display-ordering fields — changing one of these alone is not a content update.
     *
     * @var list<string>
     */
    private const array STRUCTURAL_ONLY_FIELDS = ['position'];

    private function isStructuralOnlyChange(PreUpdateEventArgs $args): bool
    {
        $changedFields = array_keys($args->getEntityChangeSet());

        return [] !== $changedFields
            && [] === array_diff($changedFields, self::STRUCTURAL_ONLY_FIELDS);
    }
}
