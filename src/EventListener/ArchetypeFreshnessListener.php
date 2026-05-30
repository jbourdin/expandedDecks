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

namespace App\EventListener;

use App\Entity\Deck;
use App\Entity\DeckVersion;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Bumps `Archetype.lastPublishedAt` whenever one of its variants is created or
 * modified, so the catalog freshness signal reflects real variant activity
 * rather than only direct edits to the archetype metadata.
 *
 * Modifications are buffered during flush and emitted as a single bulk SQL
 * `UPDATE` in `postFlush` — this avoids re-entering Archetype's lifecycle
 * callbacks (which would needlessly run slug regeneration and the trait's
 * change-set inspection) and keeps the write off the in-progress UnitOfWork.
 *
 * @see docs/features.md F2.27 — Archetype publication dates
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
final class ArchetypeFreshnessListener
{
    /**
     * Display-ordering fields whose change alone is not variant activity.
     * Mirrors {@see \App\Entity\StructuralChangeTrait}.
     *
     * @var list<string>
     */
    private const array STRUCTURAL_ONLY_FIELDS = ['position'];

    /** @var array<int, true> */
    private array $pendingArchetypeIds = [];

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->collect($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        // A pure reorder only moves `position`; it isn't variant activity that
        // should refresh the archetype's freshness signal (F18.19). The
        // change-set is read here (preUpdate) because that is where Doctrine
        // reliably exposes it.
        $changedFields = array_keys($args->getEntityChangeSet());
        if ([] !== $changedFields && [] === array_diff($changedFields, self::STRUCTURAL_ONLY_FIELDS)) {
            return;
        }

        $this->collect($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pendingArchetypeIds) {
            return;
        }

        $ids = array_keys($this->pendingArchetypeIds);
        $this->pendingArchetypeIds = [];

        $args->getObjectManager()->getConnection()->executeStatement(
            'UPDATE archetype SET last_published_at = :now WHERE id IN (:ids) AND is_published = 1',
            ['now' => new \DateTimeImmutable(), 'ids' => $ids],
            ['now' => 'datetime_immutable', 'ids' => ArrayParameterType::INTEGER],
        );
    }

    private function collect(object $entity): void
    {
        if ($entity instanceof Deck) {
            $this->collectFromDeck($entity);

            return;
        }

        if ($entity instanceof DeckVersion) {
            $this->collectFromDeck($entity->getDeck());
        }
    }

    private function collectFromDeck(Deck $deck): void
    {
        // Variants are archetype-owned decks (`owner IS NULL`, `archetype IS NOT NULL`).
        // Player-owned decks shouldn't bump the archetype's freshness.
        if (null !== $deck->getOwner()) {
            return;
        }

        $archetype = $deck->getArchetype();
        if (null === $archetype) {
            return;
        }

        $archetypeId = $archetype->getId();
        if (null === $archetypeId) {
            return;
        }

        $this->pendingArchetypeIds[$archetypeId] = true;
    }
}
