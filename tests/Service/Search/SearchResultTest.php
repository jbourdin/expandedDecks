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

namespace App\Tests\Service\Search;

use App\Service\Search\SearchResult;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F18.2 — Global search results page
 */
class SearchResultTest extends TestCase
{
    public function testFromHitArchetype(): void
    {
        $hit = [
            'id' => '42_en',
            'type' => 'archetype',
            'name' => 'Regidrago',
            'slug' => 'regidrago',
            'description' => 'A powerful dragon archetype.',
            '_formatted' => [
                'name' => '<mark>Regidrago</mark>',
                'description' => 'A powerful <mark>dragon</mark> archetype.',
            ],
        ];

        $result = SearchResult::fromHit($hit);

        self::assertSame('archetype', $result->type);
        self::assertSame('<mark>Regidrago</mark>', $result->title);
        self::assertSame('A powerful <mark>dragon</mark> archetype.', $result->excerpt);
        self::assertSame('regidrago', $result->slug);
        self::assertNull($result->secondaryInfo);
        self::assertNull($result->archetypeSlug);
    }

    public function testFromHitPage(): void
    {
        $hit = [
            'type' => 'page',
            'title' => 'Welcome',
            'slug' => 'welcome',
            'content' => 'Welcome to the site.',
            '_formatted' => [
                'title' => '<mark>Welcome</mark>',
                'content' => '<mark>Welcome</mark> to the site.',
            ],
        ];

        $result = SearchResult::fromHit($hit);

        self::assertSame('page', $result->type);
        self::assertSame('<mark>Welcome</mark>', $result->title);
        self::assertStringContainsString('Welcome', $result->excerpt);
        self::assertSame('welcome', $result->slug);
    }

    public function testFromHitEvent(): void
    {
        $hit = [
            'id' => '7',
            'type' => 'event',
            'name' => 'Paris League',
            'description' => 'Monthly league event.',
            'date' => '2026-05-01',
            '_formatted' => [
                'name' => '<mark>Paris</mark> League',
                'description' => 'Monthly league event.',
            ],
        ];

        $result = SearchResult::fromHit($hit);

        self::assertSame('event', $result->type);
        self::assertSame('<mark>Paris</mark> League', $result->title);
        self::assertSame('7', $result->slug);
        self::assertSame('2026-05-01', $result->secondaryInfo);
    }

    public function testFromHitDeck(): void
    {
        $hit = [
            'id' => 'ABC123',
            'type' => 'deck',
            'name' => 'My Regidrago',
            'shortTag' => 'ABC123',
            'ownerName' => 'Julien',
            '_formatted' => [
                'name' => 'My <mark>Regidrago</mark>',
            ],
        ];

        $result = SearchResult::fromHit($hit);

        self::assertSame('deck', $result->type);
        self::assertSame('My <mark>Regidrago</mark>', $result->title);
        self::assertSame('ABC123', $result->slug);
        self::assertSame('Julien', $result->secondaryInfo);
    }

    public function testFromHitVariant(): void
    {
        $hit = [
            'id' => 'XYZ789',
            'type' => 'variant',
            'name' => 'Turbo Regidrago',
            'shortTag' => 'XYZ789',
            'archetypeName' => 'Regidrago',
            'archetypeSlug' => 'regidrago',
            '_formatted' => [
                'name' => 'Turbo <mark>Regidrago</mark>',
            ],
        ];

        $result = SearchResult::fromHit($hit);

        self::assertSame('variant', $result->type);
        self::assertSame('Turbo <mark>Regidrago</mark>', $result->title);
        self::assertSame('XYZ789', $result->slug);
        self::assertSame('Regidrago', $result->secondaryInfo);
        self::assertSame('regidrago', $result->archetypeSlug);
    }

    public function testFromHitEventWithNumericId(): void
    {
        $hit = [
            'id' => 42,
            'type' => 'event',
            'name' => 'Test Event',
            '_formatted' => ['name' => 'Test Event'],
        ];

        $result = SearchResult::fromHit($hit);

        self::assertSame('42', $result->slug);
    }

    public function testFromHitWithMissingFields(): void
    {
        $hit = [
            'type' => 'archetype',
        ];

        $result = SearchResult::fromHit($hit);

        self::assertSame('archetype', $result->type);
        self::assertSame('', $result->title);
        self::assertSame('', $result->excerpt);
        self::assertSame('', $result->slug);
    }

    public function testFromHitWithMissingType(): void
    {
        $hit = [
            'name' => 'Something',
        ];

        $result = SearchResult::fromHit($hit);

        self::assertSame('unknown', $result->type);
    }

    public function testExcerptTruncatesLongContent(): void
    {
        $longText = str_repeat('word ', 100);
        $hit = [
            'type' => 'page',
            'title' => 'Long Page',
            'slug' => 'long',
            'content' => $longText,
            '_formatted' => [
                'title' => 'Long Page',
                'content' => $longText,
            ],
        ];

        $result = SearchResult::fromHit($hit);

        self::assertStringEndsWith('…', $result->excerpt);
        self::assertLessThanOrEqual(210, mb_strlen($result->excerpt));
    }
}
