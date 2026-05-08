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

use App\Constants\ListingIntroPage;
use App\Entity\MenuCategory;
use App\Entity\Page;
use App\Repository\MenuCategoryRepository;
use App\Service\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
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

    /**
     * Build the public URL for a CMS page.
     *
     * Reserved listing-intro slugs (banned/staple) resolve to their canonical
     * listing route so navigation links don't bounce through a 301 redirect.
     */
    public function getPageUrl(Page $page): string
    {
        $slug = $page->getSlug();
        $listingRoute = ListingIntroPage::routeForSlug($slug);

        $parameters = [];
        $locale = $this->requestStack->getCurrentRequest()?->getLocale();
        if (null !== $locale) {
            $parameters['_locale'] = $locale;
        }

        if (null !== $listingRoute) {
            return $this->urlGenerator->generate($listingRoute, $parameters);
        }

        $parameters['slug'] = $slug;

        return $this->urlGenerator->generate('app_page_show', $parameters);
    }

    /**
     * Whether a reserved listing-intro slug is currently published AND assigned
     * to a menu category for the active channel — used to suppress the
     * hardcoded fallback nav link when the page has been moved into the menu.
     */
    public function isListingIntroInMenu(string $slug): bool
    {
        if (!ListingIntroPage::isListingSlug($slug)) {
            return false;
        }

        foreach ($this->getCategories() as $category) {
            foreach ($category->getPages() as $page) {
                if ($page->getSlug() === $slug) {
                    return true;
                }
            }
        }

        return false;
    }
}
