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
    ) {
    }

    /**
     * @return list<MenuCategory>
     */
    public function getCategories(): array
    {
        return $this->menuCategoryRepository->findWithPublishedPages();
    }
}
