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

namespace App\Constants;

/**
 * Reserved CMS page slugs that back the editable intro block on the
 * banned-cards and staple-cards listing pages.
 *
 * Each reserved slug points to a real `Page` entity (multilingual,
 * Markdown-rendered, admin-editable) but its canonical URL is the
 * listing route — not `app_page_show`. Centralised here so the
 * controllers, the search indexer, the search runtime and the
 * Doctrine listener all agree on the mapping.
 */
final class ListingIntroPage
{
    public const string BANNED_CARDS_SLUG = 'banned-cards-intro';
    public const string STAPLE_CARDS_SLUG = 'staple-cards-intro';

    /** @var list<string> */
    public const array SLUGS = [
        self::BANNED_CARDS_SLUG,
        self::STAPLE_CARDS_SLUG,
    ];

    /** @var array<string, string> */
    public const array ROUTE_BY_SLUG = [
        self::BANNED_CARDS_SLUG => 'app_banned_card_list',
        self::STAPLE_CARDS_SLUG => 'app_staple_card_list',
    ];

    public static function isListingSlug(string $slug): bool
    {
        return \in_array($slug, self::SLUGS, true);
    }

    public static function routeForSlug(string $slug): ?string
    {
        return self::ROUTE_BY_SLUG[$slug] ?? null;
    }
}
