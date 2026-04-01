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

namespace App\Tests\Service;

use App\Entity\HomepageLayout;
use App\Entity\HomepageLayoutTranslation;
use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Enum\HomepageBlockType;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\MenuCategoryRepository;
use App\Repository\PageRepository;
use App\Service\HomepageRenderer;
use App\Service\MarkdownRenderer;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F10.4 — Homepage rendering service and Twig block partials
 */
class HomepageRendererTest extends TestCase
{
    private HomepageRenderer $renderer;
    private PageRepository&Stub $pageRepository;
    private MenuCategoryRepository&Stub $menuCategoryRepository;
    private EventRepository&Stub $eventRepository;
    private DeckRepository&Stub $deckRepository;

    protected function setUp(): void
    {
        $this->pageRepository = $this->createStub(PageRepository::class);
        $this->menuCategoryRepository = $this->createStub(MenuCategoryRepository::class);
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->deckRepository = $this->createStub(DeckRepository::class);

        $this->renderer = new HomepageRenderer(
            new MarkdownRenderer(),
            $this->pageRepository,
            $this->menuCategoryRepository,
            $this->eventRepository,
            $this->deckRepository,
        );
    }

    public function testResolveReturnsEmptyForEmptyLayout(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertSame([], $result);
    }

    public function testResolveSkipsUnknownBlockTypes(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'nonexistent', 'startAt' => null, 'endAt' => null, 'columnWidth' => null, 'cssClasses' => null],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertSame([], $result);
    }

    public function testResolveHeroBlockWithTranslations(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'hero', 'startAt' => null, 'endAt' => null, 'columnWidth' => null, 'cssClasses' => null],
        ]);

        $translation = new HomepageLayoutTranslation();
        $translation->setLocale('en');
        $translation->setBlockTranslations([
            0 => ['title' => 'Welcome', 'subtitle' => 'Hello world'],
        ]);
        $layout->addTranslation($translation);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(1, $result);
        self::assertSame(HomepageBlockType::Hero, $result[0]->type);
        self::assertSame('Welcome', $result[0]->translations['title']);
        self::assertSame('Hello world', $result[0]->translations['subtitle']);
    }

    public function testResolveRichTextWithTranslatableContent(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'richText', 'startAt' => null, 'endAt' => null, 'columnWidth' => null, 'cssClasses' => null],
        ]);

        $translation = new HomepageLayoutTranslation();
        $translation->setLocale('en');
        $translation->setBlockTranslations([
            0 => ['content' => '## Hello World'],
        ]);
        $layout->addTranslation($translation);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(1, $result);
        self::assertSame(HomepageBlockType::RichText, $result[0]->type);
        self::assertStringContainsString('Hello World', $result[0]->resolvedData['html']);
    }

    public function testResolveRichTextWithEmptyContentReturnsEmptyData(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'richText', 'startAt' => null, 'endAt' => null, 'columnWidth' => null, 'cssClasses' => null],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(1, $result);
        self::assertSame([], $result[0]->resolvedData);
    }

    public function testResolvePageEmbedWithPageSlug(): void
    {
        $page = new Page();
        $page->setSlug('welcome');
        $page->setIsPublished(true);

        $pageTranslation = new PageTranslation();
        $pageTranslation->setLocale('en');
        $pageTranslation->setTitle('Welcome');
        $pageTranslation->setContent('# Hello');
        $page->addTranslation($pageTranslation);

        $this->pageRepository->method('findBySlug')->willReturn($page);

        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'pageEmbed', 'startAt' => null, 'endAt' => null, 'columnWidth' => null, 'cssClasses' => null, 'pageSlug' => 'welcome'],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(1, $result);
        self::assertSame(HomepageBlockType::PageEmbed, $result[0]->type);
        self::assertStringContainsString('Hello', $result[0]->resolvedData['html']);
    }

    public function testResolvePageEmbedWithMissingPageReturnsEmptyData(): void
    {
        $this->pageRepository->method('findBySlug')->willReturn(null);

        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'pageEmbed', 'startAt' => null, 'endAt' => null, 'columnWidth' => null, 'cssClasses' => null, 'pageSlug' => 'nonexistent'],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(1, $result);
        self::assertSame([], $result[0]->resolvedData);
    }

    public function testResolveLatestPagesWithCategory(): void
    {
        $category = new MenuCategory();
        $categoryTranslation = new MenuCategoryTranslation();
        $categoryTranslation->setLocale('en');
        $categoryTranslation->setName('News');
        $category->addTranslation($categoryTranslation);

        $this->menuCategoryRepository->method('findAllOrdered')->willReturn([$category]);

        $page = new Page();
        $page->setSlug('test-news');
        $page->setIsPublished(true);

        $this->pageRepository->method('findPublishedByCategory')->willReturn([$page]);
        $this->pageRepository->method('countPublishedByCategory')->willReturn(1);

        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'latestPages', 'startAt' => null, 'endAt' => null, 'columnWidth' => null, 'cssClasses' => null, 'categorySlug' => 'news', 'limit' => 5],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(1, $result);
        self::assertSame(HomepageBlockType::LatestPages, $result[0]->type);
        self::assertCount(1, $result[0]->resolvedData['pages']);
        self::assertSame(1, $result[0]->resolvedData['totalCount']);
    }

    public function testResolveFeaturedEventReturnsCount(): void
    {
        $this->eventRepository->method('countUpcoming')->willReturn(7);

        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'featuredEvent', 'startAt' => null, 'endAt' => null, 'columnWidth' => 6, 'cssClasses' => null],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(1, $result);
        self::assertSame(7, $result[0]->resolvedData['count']);
        self::assertSame(6, $result[0]->columnWidth);
    }

    public function testResolveFeaturedDeckReturnsCount(): void
    {
        $this->deckRepository->method('countPublicDecks')->willReturn(12);

        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'featuredDeck', 'startAt' => null, 'endAt' => null, 'columnWidth' => 6, 'cssClasses' => null],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(1, $result);
        self::assertSame(12, $result[0]->resolvedData['count']);
    }

    public function testBlockFilteredByStartAt(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'hero', 'startAt' => '2099-01-01T00:00:00', 'endAt' => null, 'columnWidth' => null, 'cssClasses' => null],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertSame([], $result);
    }

    public function testBlockFilteredByEndAt(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'hero', 'startAt' => null, 'endAt' => '2000-01-01T00:00:00', 'columnWidth' => null, 'cssClasses' => null],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertSame([], $result);
    }

    public function testBlockVisibleWithinSchedule(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'hero', 'startAt' => '2000-01-01T00:00:00', 'endAt' => '2099-12-31T23:59:59', 'columnWidth' => null, 'cssClasses' => null],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(1, $result);
    }

    public function testCarouselItemsFilteredBySchedule(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([
            [
                'type' => 'carousel',
                'startAt' => null,
                'endAt' => null,
                'columnWidth' => null,
                'cssClasses' => null,
                'items' => [
                    ['image' => 'a.jpg', 'alt' => 'A', 'link' => '/', 'startAt' => null, 'endAt' => null],
                    ['image' => 'b.jpg', 'alt' => 'B', 'link' => '/', 'startAt' => '2099-01-01T00:00:00', 'endAt' => null],
                    ['image' => 'c.jpg', 'alt' => 'C', 'link' => '/', 'startAt' => null, 'endAt' => '2000-01-01T00:00:00'],
                ],
            ],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(1, $result);
        self::assertCount(1, $result[0]->resolvedData['items']);
        self::assertSame('a.jpg', $result[0]->resolvedData['items'][0]['image']);
    }

    public function testTranslationFallsBackToEnglish(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'hero', 'startAt' => null, 'endAt' => null, 'columnWidth' => null, 'cssClasses' => null],
        ]);

        $translation = new HomepageLayoutTranslation();
        $translation->setLocale('en');
        $translation->setBlockTranslations([
            0 => ['title' => 'English Title'],
        ]);
        $layout->addTranslation($translation);

        $result = $this->renderer->resolve($layout, 'de');

        self::assertCount(1, $result);
        self::assertSame('English Title', $result[0]->translations['title']);
    }

    public function testCssClassesPassedThrough(): void
    {
        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'hero', 'startAt' => null, 'endAt' => null, 'columnWidth' => null, 'cssClasses' => 'my-custom-class'],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertSame('my-custom-class', $result[0]->cssClasses);
    }

    public function testMultipleBlocksResolvedInOrder(): void
    {
        $this->eventRepository->method('countUpcoming')->willReturn(3);
        $this->deckRepository->method('countPublicDecks')->willReturn(5);

        $layout = new HomepageLayout();
        $layout->setBlocks([
            ['type' => 'hero', 'startAt' => null, 'endAt' => null, 'columnWidth' => null, 'cssClasses' => null],
            ['type' => 'featuredEvent', 'startAt' => null, 'endAt' => null, 'columnWidth' => 6, 'cssClasses' => null],
            ['type' => 'featuredDeck', 'startAt' => null, 'endAt' => null, 'columnWidth' => 6, 'cssClasses' => null],
        ]);

        $result = $this->renderer->resolve($layout, 'en');

        self::assertCount(3, $result);
        self::assertSame(HomepageBlockType::Hero, $result[0]->type);
        self::assertSame(HomepageBlockType::FeaturedEvent, $result[1]->type);
        self::assertSame(HomepageBlockType::FeaturedDeck, $result[2]->type);
    }
}
