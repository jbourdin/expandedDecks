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

namespace App\Twig\Runtime;

use App\Entity\MenuCategory;
use App\Repository\MenuCategoryRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Provides menu categories with published pages for the site navigation.
 *
 * @see docs/features.md F11.2 — Menu categories
 */
class MenuRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly MenuCategoryRepository $menuCategoryRepository,
        private readonly CacheInterface $menuCategoriesCache,
    ) {
    }

    /**
     * @return list<MenuCategory>
     */
    public function getCategories(): array
    {
        /** @var list<MenuCategory> $categories */
        $categories = $this->menuCategoriesCache->get('menu_categories', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            return $this->menuCategoryRepository->findWithPublishedPages();
        });

        return $categories;
    }

    public function invalidateCache(): void
    {
        $this->menuCategoriesCache->delete('menu_categories');
    }
}
