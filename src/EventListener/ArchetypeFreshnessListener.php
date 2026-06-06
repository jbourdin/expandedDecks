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

use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Entity\Deck;
use App\Entity\DeckVersion;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Bumps `Archetype.lastPublishedAt` whenever one of its variants or translations
 * is created or modified, so the catalog freshness signal reflects real variant
 * and content activity rather than only direct edits to the archetype metadata.
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
     * Fields whose change alone is not content activity: display ordering
     * (`position`) and social-preview metadata (`ogImage`/`ogDescription`).
     * Mirrors {@see \App\Entity\TimestampExemptChangeTrait}.
     *
     * @var list<string>
     */
    private const array TIMESTAMP_EXEMPT_FIELDS = ['position', 'ogImage', 'ogDescription'];

    /** @var array<int, true> */
    private array $pendingArchetypeIds = [];

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->collect($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        // A pure reorder (`position`, F18.19) or social-preview tuning
        // (`ogImage`/`ogDescription`, F18.32) isn't content activity that
        // should refresh the archetype's freshness signal. The change-set is
        // read here (preUpdate) because that is where Doctrine reliably
        // exposes it.
        $changedFields = array_keys($args->getEntityChangeSet());
        if ([] !== $changedFields && [] === array_diff($changedFields, self::TIMESTAMP_EXEMPT_FIELDS)) {
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

            return;
        }

        // Editing a localized name/description is real content activity that
        // should refresh the catalog freshness signal — the change dirties only
        // the translation child, never the owning Archetype, so its lifecycle
        // callbacks would otherwise be skipped.
        if ($entity instanceof ArchetypeTranslation) {
            $this->collectArchetype($entity->getArchetype());
        }
    }

    private function collectArchetype(Archetype $archetype): void
    {
        $archetypeId = $archetype->getId();
        if (null === $archetypeId) {
            return;
        }

        $this->pendingArchetypeIds[$archetypeId] = true;
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

        $this->collectArchetype($archetype);
    }
}
