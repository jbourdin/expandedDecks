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

namespace App\Tests\Enum;

use App\Enum\HomepageBlockType;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F10.3 — HomepageLayout entity and data model
 */
class HomepageBlockTypeTest extends TestCase
{
    public function testAllCasesHaveLabels(): void
    {
        foreach (HomepageBlockType::cases() as $case) {
            self::assertStringStartsWith('app.homepage.block_type.', $case->label());
        }
    }

    public function testAllCasesHaveIcons(): void
    {
        foreach (HomepageBlockType::cases() as $case) {
            self::assertStringStartsWith('bi-', $case->icon());
        }
    }

    public function testHeroHasTranslatableContent(): void
    {
        self::assertTrue(HomepageBlockType::Hero->hasTranslatableContent());
    }

    public function testRichTextHasTranslatableContent(): void
    {
        self::assertTrue(HomepageBlockType::RichText->hasTranslatableContent());
    }

    public function testCarouselHasNoTranslatableContent(): void
    {
        self::assertFalse(HomepageBlockType::Carousel->hasTranslatableContent());
    }

    public function testLatestPagesHasNoTranslatableContent(): void
    {
        self::assertFalse(HomepageBlockType::LatestPages->hasTranslatableContent());
    }

    public function testFeaturedDeckHasTranslatableContent(): void
    {
        self::assertTrue(HomepageBlockType::FeaturedDeck->hasTranslatableContent());
    }

    public function testFeaturedEventHasTranslatableContent(): void
    {
        self::assertTrue(HomepageBlockType::FeaturedEvent->hasTranslatableContent());
    }

    public function testTryFromWithValidValue(): void
    {
        self::assertSame(HomepageBlockType::Hero, HomepageBlockType::tryFrom('hero'));
        self::assertSame(HomepageBlockType::RichText, HomepageBlockType::tryFrom('richText'));
        self::assertSame(HomepageBlockType::Carousel, HomepageBlockType::tryFrom('carousel'));
        self::assertSame(HomepageBlockType::LatestPages, HomepageBlockType::tryFrom('latestPages'));
        self::assertSame(HomepageBlockType::FeaturedDeck, HomepageBlockType::tryFrom('featuredDeck'));
        self::assertSame(HomepageBlockType::FeaturedEvent, HomepageBlockType::tryFrom('featuredEvent'));
    }

    public function testTryFromWithInvalidValueReturnsNull(): void
    {
        self::assertNull(HomepageBlockType::tryFrom('invalid'));
    }

    public function testLabelValues(): void
    {
        self::assertSame('app.homepage.block_type.hero', HomepageBlockType::Hero->label());
        self::assertSame('app.homepage.block_type.rich_text', HomepageBlockType::RichText->label());
        self::assertSame('app.homepage.block_type.carousel', HomepageBlockType::Carousel->label());
        self::assertSame('app.homepage.block_type.latest_pages', HomepageBlockType::LatestPages->label());
        self::assertSame('app.homepage.block_type.featured_deck', HomepageBlockType::FeaturedDeck->label());
        self::assertSame('app.homepage.block_type.featured_event', HomepageBlockType::FeaturedEvent->label());
    }
}
