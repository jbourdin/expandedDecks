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

namespace App\Enum;

/**
 * Block types available for the homepage layout builder.
 *
 * @see docs/features.md F10.3 — HomepageLayout entity and data model
 */
enum HomepageBlockType: string
{
    case Hero = 'hero';
    case RichText = 'richText';
    case PageEmbed = 'pageEmbed';
    case Carousel = 'carousel';
    case LatestPages = 'latestPages';
    case FeaturedDeck = 'featuredDeck';
    case FeaturedEvent = 'featuredEvent';

    /**
     * Human-readable label (translation key).
     */
    public function label(): string
    {
        return match ($this) {
            self::Hero => 'app.homepage.block_type.hero',
            self::RichText => 'app.homepage.block_type.rich_text',
            self::PageEmbed => 'app.homepage.block_type.page_embed',
            self::Carousel => 'app.homepage.block_type.carousel',
            self::LatestPages => 'app.homepage.block_type.latest_pages',
            self::FeaturedDeck => 'app.homepage.block_type.featured_deck',
            self::FeaturedEvent => 'app.homepage.block_type.featured_event',
        };
    }

    /**
     * Bootstrap icon class for the block type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Hero => 'bi-megaphone',
            self::RichText => 'bi-file-richtext',
            self::PageEmbed => 'bi-box-arrow-in-right',
            self::Carousel => 'bi-images',
            self::LatestPages => 'bi-newspaper',
            self::FeaturedDeck => 'bi-collection',
            self::FeaturedEvent => 'bi-calendar-event',
        };
    }

    /**
     * Whether this block type has translatable content stored in HomepageLayoutTranslation.
     */
    public function hasTranslatableContent(): bool
    {
        return match ($this) {
            self::Hero, self::RichText, self::FeaturedDeck, self::FeaturedEvent => true,
            self::Carousel, self::LatestPages, self::PageEmbed => false,
        };
    }
}
