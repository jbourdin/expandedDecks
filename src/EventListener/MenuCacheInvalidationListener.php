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

use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use App\Entity\Page;
use App\Entity\PageTranslation;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Invalidates the menu categories cache when CMS entities are modified.
 *
 * @see docs/features.md F11.2 — Menu categories
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class MenuCacheInvalidationListener
{
    private bool $shouldInvalidate = false;

    private const array WATCHED_ENTITIES = [
        Page::class,
        PageTranslation::class,
        MenuCategory::class,
        MenuCategoryTranslation::class,
    ];

    public function __construct(
        private readonly CacheInterface $menuCategoriesCache,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($this->isWatched($entity)) {
                $this->shouldInvalidate = true;

                return;
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($this->isWatched($entity)) {
                $this->shouldInvalidate = true;

                return;
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->isWatched($entity)) {
                $this->shouldInvalidate = true;

                return;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->shouldInvalidate) {
            $this->shouldInvalidate = false;
            $this->menuCategoriesCache->delete('menu_categories');
        }
    }

    private function isWatched(object $entity): bool
    {
        foreach (self::WATCHED_ENTITIES as $class) {
            if ($entity instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
