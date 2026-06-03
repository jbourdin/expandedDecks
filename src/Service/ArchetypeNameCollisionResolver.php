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

use App\Entity\Archetype;
use App\Repository\ArchetypeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Frees up an archetype name/slug occupied by a soft-deleted row so a new
 * archetype can take the canonical values.
 *
 * `Archetype` has DB-level unique constraints on both `name` and `slug`, and
 * soft-deleted rows (`deletedAt` set) keep occupying those slots. Recreating an
 * archetype with a previously-deleted name therefore hits a constraint
 * violation. This resolver renames the conflicting soft-deleted row(s) by
 * appending a uniqueness suffix to both columns.
 *
 * The rename is issued as a direct DQL `UPDATE` rather than via entity setters:
 * (1) it bypasses {@see Archetype} lifecycle callbacks that would re-derive the
 * slug from the name, so the literal suffix is preserved on the slug too, and
 * (2) the statement executes immediately, guaranteeing it lands before the new
 * row's INSERT — Doctrine's unit of work flushes all INSERTs before all UPDATEs,
 * so renaming through a shared flush would not free the slot in time.
 *
 * @see docs/features.md F2.31 — Rename soft-deleted name/slug conflicts on creation
 */
final readonly class ArchetypeNameCollisionResolver
{
    /**
     * Column length of `Archetype::$name` / `Archetype::$slug`.
     */
    private const int COLUMN_LENGTH = 100;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ArchetypeRepository $archetypeRepository,
    ) {
    }

    /**
     * Rename any soft-deleted archetype occupying $name or its derived slug.
     *
     * Call this before persisting/flushing the new (or edited) archetype.
     * On edit, pass the edited archetype's id as $excludeId so it can never
     * rename itself.
     *
     * @see docs/features.md F2.31 — Rename soft-deleted name/slug conflicts on creation
     */
    public function resolve(string $name, ?int $excludeId = null): void
    {
        $slug = Archetype::slugify($name);

        foreach ($this->archetypeRepository->findSoftDeletedByNameOrSlug($name, $slug, $excludeId) as $conflict) {
            $suffix = '__deleted_'.bin2hex(random_bytes(3));

            $this->entityManager->createQueryBuilder()
                ->update(Archetype::class, 'a')
                ->set('a.name', ':name')
                ->set('a.slug', ':slug')
                ->where('a.id = :id')
                ->setParameter('name', $this->appendSuffix($conflict->getName(), $suffix))
                ->setParameter('slug', $this->appendSuffix($conflict->getSlug(), $suffix))
                ->setParameter('id', $conflict->getId())
                ->getQuery()
                ->execute();
        }
    }

    /**
     * Append the suffix, truncating the base so the result fits the column,
     * while preserving as much of the original value as possible for audit.
     */
    private function appendSuffix(string $base, string $suffix): string
    {
        $maxBaseLength = self::COLUMN_LENGTH - \strlen($suffix);

        if (mb_strlen($base) > $maxBaseLength) {
            $base = mb_substr($base, 0, $maxBaseLength);
        }

        return $base.$suffix;
    }
}
