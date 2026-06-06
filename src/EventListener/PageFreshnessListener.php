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

use App\Entity\PageTranslation;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Bumps `Page.lastPublishedAt` whenever one of its translations is created or
 * modified, so the public "Updated on" date reflects real content edits and not
 * only direct edits to the page metadata.
 *
 * A content edit goes through {@see \App\Controller\AdminPageController::saveTranslation()},
 * which mutates only the `PageTranslation` child and flushes. Doctrine fires
 * `PreUpdate` per-entity, only when that entity's own fields are dirty — a child
 * change in a `OneToMany` never dirties the owning `Page`, so the page's lifecycle
 * callbacks (and thus the trait's `lastPublishedAt` refresh) would otherwise be
 * skipped.
 *
 * Modifications are buffered during flush and emitted as a single bulk SQL
 * `UPDATE` in `postFlush` — this keeps the write off the in-progress UnitOfWork
 * and avoids re-entering Page's lifecycle callbacks. The `is_published = 1` guard
 * reproduces {@see \App\Entity\PublishableTimestampsTrait::stampPublicationOnUpdate()}'s
 * "drafts never bump" rule; `firstPublishedAt` is intentionally left untouched.
 *
 * @see docs/features.md F11.4 — CMS publication dates
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
final class PageFreshnessListener
{
    /**
     * Fields whose change alone is not content activity: social-preview
     * metadata (`ogImage`/`ogDescription`).
     * Mirrors {@see \App\Entity\TimestampExemptChangeTrait}.
     *
     * @var list<string>
     */
    private const array TIMESTAMP_EXEMPT_FIELDS = ['ogImage', 'ogDescription'];

    /** @var array<int, true> */
    private array $pendingPageIds = [];

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->collect($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        // Social-preview tuning (`ogImage`/`ogDescription` on a translation,
        // F18.32) isn't content activity that should refresh the page's
        // "Updated on" date.
        $changedFields = array_keys($args->getEntityChangeSet());
        if ([] !== $changedFields && [] === array_diff($changedFields, self::TIMESTAMP_EXEMPT_FIELDS)) {
            return;
        }

        $this->collect($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pendingPageIds) {
            return;
        }

        $ids = array_keys($this->pendingPageIds);
        $this->pendingPageIds = [];

        $args->getObjectManager()->getConnection()->executeStatement(
            'UPDATE page SET last_published_at = :now WHERE id IN (:ids) AND is_published = 1',
            ['now' => new \DateTimeImmutable(), 'ids' => $ids],
            ['now' => 'datetime_immutable', 'ids' => ArrayParameterType::INTEGER],
        );
    }

    private function collect(object $entity): void
    {
        if (!$entity instanceof PageTranslation) {
            return;
        }

        $pageId = $entity->getPage()->getId();
        if (null === $pageId) {
            return;
        }

        $this->pendingPageIds[$pageId] = true;
    }
}
