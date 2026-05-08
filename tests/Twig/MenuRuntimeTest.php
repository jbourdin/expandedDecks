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

namespace App\Tests\Twig;

use App\Entity\Channel;
use App\Entity\MenuCategory;
use App\Entity\Page;
use App\Repository\MenuCategoryRepository;
use App\Service\Channel\ChannelContext;
use App\Twig\Runtime\MenuRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @see docs/features.md F11.2 — Menu categories
 * @see docs/features.md F18.8 — Add channel association to MenuCategory
 */
class MenuRuntimeTest extends TestCase
{
    public function testGetCategoriesReturnsCachedResult(): void
    {
        $category = new MenuCategory();
        $category->setPosition(1);

        $repository = $this->createStub(MenuCategoryRepository::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->with('menu_categories_app', self::callback(static fn (mixed $value): bool => \is_callable($value)))
            ->willReturn([$category]);

        $runtime = new MenuRuntime($repository, $this->createChannelContext(), $cache, $this->createStub(UrlGeneratorInterface::class), new RequestStack());
        $result = $runtime->getCategories();

        self::assertCount(1, $result);
        self::assertSame($category, $result[0]);
    }

    public function testGetCategoriesCallsRepositoryOnCacheMiss(): void
    {
        $category = new MenuCategory();
        $category->setPosition(1);

        $repository = $this->createMock(MenuCategoryRepository::class);
        $repository->expects(self::once())
            ->method('findWithPublishedPages')
            ->willReturn([$category]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->with('menu_categories_app', self::callback(static fn (mixed $value): bool => \is_callable($value)))
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createStub(ItemInterface::class);

                return $callback($item);
            });

        $runtime = new MenuRuntime($repository, $this->createChannelContext(), $cache, $this->createStub(UrlGeneratorInterface::class), new RequestStack());
        $result = $runtime->getCategories();

        self::assertCount(1, $result);
        self::assertSame($category, $result[0]);
    }

    public function testGetCategoriesReturnsEmptyArrayWhenNoCategories(): void
    {
        $repository = $this->createStub(MenuCategoryRepository::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->willReturn([]);

        $runtime = new MenuRuntime($repository, $this->createChannelContext(), $cache, $this->createStub(UrlGeneratorInterface::class), new RequestStack());
        $result = $runtime->getCategories();

        self::assertSame([], $result);
    }

    public function testGetFooterCategoriesReturnsCachedResult(): void
    {
        $category = new MenuCategory();
        $category->setPosition(1);
        $category->setIsFooter(true);

        $repository = $this->createStub(MenuCategoryRepository::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->with('footer_categories_app', self::callback(static fn (mixed $value): bool => \is_callable($value)))
            ->willReturn([$category]);

        $runtime = new MenuRuntime($repository, $this->createChannelContext(), $cache, $this->createStub(UrlGeneratorInterface::class), new RequestStack());
        $result = $runtime->getFooterCategories();

        self::assertCount(1, $result);
        self::assertSame($category, $result[0]);
    }

    public function testGetFooterCategoriesCallsRepositoryOnCacheMiss(): void
    {
        $category = new MenuCategory();
        $category->setPosition(1);
        $category->setIsFooter(true);

        $repository = $this->createMock(MenuCategoryRepository::class);
        $repository->expects(self::once())
            ->method('findFooterWithPublishedPages')
            ->willReturn([$category]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->with('footer_categories_app', self::callback(static fn (mixed $value): bool => \is_callable($value)))
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createStub(ItemInterface::class);

                return $callback($item);
            });

        $runtime = new MenuRuntime($repository, $this->createChannelContext(), $cache, $this->createStub(UrlGeneratorInterface::class), new RequestStack());
        $result = $runtime->getFooterCategories();

        self::assertCount(1, $result);
        self::assertSame($category, $result[0]);
    }

    public function testInvalidateCacheDeletesAllChannelKeys(): void
    {
        $repository = $this->createStub(MenuCategoryRepository::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::exactly(4))
            ->method('delete')
            ->willReturnCallback(static function (string $key): bool {
                self::assertContains($key, [
                    'menu_categories_app',
                    'menu_categories_content',
                    'footer_categories_app',
                    'footer_categories_content',
                ]);

                return true;
            });

        $runtime = new MenuRuntime($repository, $this->createChannelContext(), $cache, $this->createStub(UrlGeneratorInterface::class), new RequestStack());
        $runtime->invalidateCache();
    }

    public function testGetPageUrlForRegularPageUsesAppPageShowRoute(): void
    {
        $page = (new Page())->setSlug('about-us');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_page_show', ['_locale' => 'fr', 'slug' => 'about-us'])
            ->willReturn('/fr/pages/about-us');

        $runtime = new MenuRuntime(
            $this->createStub(MenuCategoryRepository::class),
            $this->createChannelContext(),
            $this->createStub(CacheInterface::class),
            $urlGenerator,
            $this->createRequestStackWithLocale('fr'),
        );

        self::assertSame('/fr/pages/about-us', $runtime->getPageUrl($page));
    }

    public function testGetPageUrlForBannedIntroSlugUsesListingRoute(): void
    {
        $page = (new Page())->setSlug('banned-cards-intro');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_banned_card_list', ['_locale' => 'en'])
            ->willReturn('/en/banned-cards');

        $runtime = new MenuRuntime(
            $this->createStub(MenuCategoryRepository::class),
            $this->createChannelContext(),
            $this->createStub(CacheInterface::class),
            $urlGenerator,
            $this->createRequestStackWithLocale('en'),
        );

        self::assertSame('/en/banned-cards', $runtime->getPageUrl($page));
    }

    public function testGetPageUrlForStapleIntroSlugUsesListingRoute(): void
    {
        $page = (new Page())->setSlug('staple-cards-intro');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_staple_card_list', ['_locale' => 'en'])
            ->willReturn('/en/staple-cards');

        $runtime = new MenuRuntime(
            $this->createStub(MenuCategoryRepository::class),
            $this->createChannelContext(),
            $this->createStub(CacheInterface::class),
            $urlGenerator,
            $this->createRequestStackWithLocale('en'),
        );

        self::assertSame('/en/staple-cards', $runtime->getPageUrl($page));
    }

    public function testIsListingIntroInMenuReturnsTrueWhenIntroPageIsCategorised(): void
    {
        $introPage = (new Page())->setSlug('banned-cards-intro')->setIsPublished(true);
        $category = new MenuCategory();
        $category->getPages()->add($introPage);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn([$category]);

        $runtime = new MenuRuntime(
            $this->createStub(MenuCategoryRepository::class),
            $this->createChannelContext(),
            $cache,
            $this->createStub(UrlGeneratorInterface::class),
            new RequestStack(),
        );

        self::assertTrue($runtime->isListingIntroInMenu('banned-cards-intro'));
    }

    public function testIsListingIntroInMenuReturnsFalseWhenIntroPageHasNoCategory(): void
    {
        $regularPage = (new Page())->setSlug('about-us')->setIsPublished(true);
        $category = new MenuCategory();
        $category->getPages()->add($regularPage);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn([$category]);

        $runtime = new MenuRuntime(
            $this->createStub(MenuCategoryRepository::class),
            $this->createChannelContext(),
            $cache,
            $this->createStub(UrlGeneratorInterface::class),
            new RequestStack(),
        );

        self::assertFalse($runtime->isListingIntroInMenu('banned-cards-intro'));
    }

    public function testIsListingIntroInMenuReturnsFalseForNonReservedSlug(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::never())->method('get');

        $runtime = new MenuRuntime(
            $this->createStub(MenuCategoryRepository::class),
            $this->createChannelContext(),
            $cache,
            $this->createStub(UrlGeneratorInterface::class),
            new RequestStack(),
        );

        self::assertFalse($runtime->isListingIntroInMenu('about-us'));
    }

    private function createChannelContext(): ChannelContext
    {
        $channel = (new Channel())->setCode('app')->setDomain('expandeddecks.wip');

        $request = new Request();
        $request->attributes->set('_channel', $channel);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new ChannelContext($requestStack);
    }

    private function createRequestStackWithLocale(string $locale): RequestStack
    {
        $request = new Request();
        $request->setLocale($locale);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }
}
