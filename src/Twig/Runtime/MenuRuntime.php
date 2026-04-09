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
use App\Service\Channel\ChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Provides menu categories with published pages for the site navigation.
 *
 * @see docs/features.md F11.2 — Menu categories
 * @see docs/features.md F18.8 — Add channel association to MenuCategory
 */
class MenuRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly MenuCategoryRepository $menuCategoryRepository,
        private readonly ChannelContext $channelContext,
        private readonly CacheInterface $menuCategoriesCache,
    ) {
    }

    /**
     * @return list<MenuCategory>
     */
    public function getCategories(): array
    {
        $channel = $this->channelContext->getChannel();
        $cacheKey = 'menu_categories_'.$channel->getCode();

        /** @var list<MenuCategory> $categories */
        $categories = $this->menuCategoriesCache->get($cacheKey, function (ItemInterface $item) use ($channel): array {
            $item->expiresAfter(3600);

            return $this->menuCategoryRepository->findWithPublishedPages($channel);
        });

        return $categories;
    }

    /**
     * @return list<MenuCategory>
     */
    public function getFooterCategories(): array
    {
        $channel = $this->channelContext->getChannel();
        $cacheKey = 'footer_categories_'.$channel->getCode();

        /** @var list<MenuCategory> $categories */
        $categories = $this->menuCategoriesCache->get($cacheKey, function (ItemInterface $item) use ($channel): array {
            $item->expiresAfter(3600);

            return $this->menuCategoryRepository->findFooterWithPublishedPages($channel);
        });

        return $categories;
    }

    public function invalidateCache(): void
    {
        // Clear all known channel cache keys
        $this->menuCategoriesCache->delete('menu_categories_app');
        $this->menuCategoriesCache->delete('menu_categories_content');
        $this->menuCategoriesCache->delete('footer_categories_app');
        $this->menuCategoriesCache->delete('footer_categories_content');
    }
}
