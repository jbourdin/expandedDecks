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
 * change that should not be advertised as one to users or search engines:
 *
 * - display ordering (`position`) — drag-and-drop reorders (F18.11, F18.19);
 * - social-preview metadata (`ogImage`, `ogDescription`) — tuning how a page
 *   is shared must not alter its publication or freshness dates (F18.32).
 *
 * Timestamp stamping is skipped when only exempt fields changed.
 *
 * @see docs/features.md F18.11 — Archetype relevance ordering
 * @see docs/features.md F18.19 — Archetype variant ordering
 * @see docs/features.md F18.32 — Card-fan OG image builder
 */
trait TimestampExemptChangeTrait
{
    /**
     * Fields whose change alone is not a content update.
     *
     * @var list<string>
     */
    private const array TIMESTAMP_EXEMPT_FIELDS = ['position', 'ogImage', 'ogDescription'];

    private function isTimestampExemptChange(PreUpdateEventArgs $args): bool
    {
        $changedFields = array_keys($args->getEntityChangeSet());

        return [] !== $changedFields
            && [] === array_diff($changedFields, self::TIMESTAMP_EXEMPT_FIELDS);
    }
}
