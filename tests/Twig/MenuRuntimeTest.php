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

use App\Entity\MenuCategory;
use App\Repository\MenuCategoryRepository;
use App\Twig\Runtime\MenuRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @see docs/features.md F11.2 — Menu categories
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
            ->with('menu_categories', self::callback(static fn (mixed $value): bool => \is_callable($value)))
            ->willReturn([$category]);

        $runtime = new MenuRuntime($repository, $cache);
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
            ->with('menu_categories', self::callback(static fn (mixed $value): bool => \is_callable($value)))
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createStub(ItemInterface::class);

                return $callback($item);
            });

        $runtime = new MenuRuntime($repository, $cache);
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

        $runtime = new MenuRuntime($repository, $cache);
        $result = $runtime->getCategories();

        self::assertSame([], $result);
    }

    public function testInvalidateCacheDeletesCacheKey(): void
    {
        $repository = $this->createStub(MenuCategoryRepository::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('delete')
            ->with('menu_categories');

        $runtime = new MenuRuntime($repository, $cache);
        $runtime->invalidateCache();
    }
}
