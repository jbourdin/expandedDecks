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
use Doctrine\ORM\Mapping as ORM;

/**
 * Adds strict publish-aware timestamps to an entity that already exposes an
 * `isPublished()` getter. `firstPublishedAt` is stamped once when the entity
 * first becomes published; `lastPublishedAt` refreshes on every persist while
 * `isPublished` is true. Drafts never bump either field.
 *
 * @see docs/features.md F11.4 — CMS publication dates
 * @see docs/features.md F2.27 — Archetype publication dates
 */
trait PublishableTimestampsTrait
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $firstPublishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastPublishedAt = null;

    public function getFirstPublishedAt(): ?\DateTimeImmutable
    {
        return $this->firstPublishedAt;
    }

    public function getLastPublishedAt(): ?\DateTimeImmutable
    {
        return $this->lastPublishedAt;
    }

    /**
     * Initial-persist hook: if the entity is created already published, stamp
     * both timestamps to "now". Drafts created via `new` get nothing.
     */
    private function stampPublicationOnPersist(): void
    {
        if (!$this->isPublished()) {
            return;
        }

        $now = new \DateTimeImmutable();
        $this->firstPublishedAt ??= $now;
        $this->lastPublishedAt = $now;
    }

    /**
     * Update hook: when `isPublished` is true at save time, refresh
     * `lastPublishedAt`. Also stamp `firstPublishedAt` the first time the
     * `isPublished` field transitions from false to true. A no-op save on a
     * draft never bumps anything.
     *
     * Doctrine's `PreUpdateEventArgs` exposes the changeset so we can detect
     * a draft → published transition reliably (an entity saved-again-while-
     * published has no `isPublished` entry in the changeset and falls through
     * to the "refresh lastPublishedAt" branch).
     */
    private function stampPublicationOnUpdate(PreUpdateEventArgs $args): void
    {
        if (!$this->isPublished()) {
            return;
        }

        $now = new \DateTimeImmutable();

        if ($args->hasChangedField('isPublished') && true === $args->getNewValue('isPublished')) {
            $this->firstPublishedAt ??= $now;
        }

        $this->lastPublishedAt = $now;
    }
}
