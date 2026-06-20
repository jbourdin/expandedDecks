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
use App\Entity\Deck;
use App\Entity\Page;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Stamps editorial content with its creator at creation time (F19.8).
 *
 * Runs on `prePersist` only, so a later editor — including an admin — never
 * acquires authorship merely by saving an edit. Skips when there is no
 * authenticated user (CLI, fixtures, anonymous requests), leaving the author
 * null, and never overwrites an author that is already set.
 *
 * Archetype variants (owner-less editorial decklists) are stamped; user-owned
 * decks are not, as those are authored by their owner.
 *
 * @see docs/features.md F19.8 — Author assignment
 */
#[AsDoctrineListener(event: Events::prePersist)]
final readonly class ContentAuthorListener
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Archetype && !$entity instanceof Page && !$entity instanceof Deck) {
            return;
        }

        // User-owned decks are authored by their owner, not the creator.
        if ($entity instanceof Deck && !$entity->isArchetypeVariant()) {
            return;
        }

        if (null !== $entity->getAuthor()) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $entity->setAuthor($user);
        }
    }
}
