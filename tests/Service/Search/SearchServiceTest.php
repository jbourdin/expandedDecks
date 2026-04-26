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

use App\Service\Search\SearchIndexer;
use App\Service\Search\SearchService;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F18.2 — Global search results page
 */
class SearchServiceTest extends TestCase
{
    // ── getIndexesForType (via reflection) ──────────────────────────────

    public function testGetIndexesForTypeReturnsAllWhenNoFilter(): void
    {
        $indexes = $this->invokeGetIndexesForType(null, null);

        self::assertCount(5, $indexes);
        self::assertContains(SearchIndexer::INDEX_ARCHETYPES, $indexes);
        self::assertContains(SearchIndexer::INDEX_VARIANTS, $indexes);
        self::assertContains(SearchIndexer::INDEX_PAGES, $indexes);
        self::assertContains(SearchIndexer::INDEX_EVENTS, $indexes);
        self::assertContains(SearchIndexer::INDEX_DECKS, $indexes);
    }

    public function testGetIndexesForTypeFiltersArchetypes(): void
    {
        $indexes = $this->invokeGetIndexesForType('archetypes', null);

        self::assertCount(2, $indexes);
        self::assertContains(SearchIndexer::INDEX_ARCHETYPES, $indexes);
        self::assertContains(SearchIndexer::INDEX_VARIANTS, $indexes);
    }

    public function testGetIndexesForTypeFiltersVariants(): void
    {
        $indexes = $this->invokeGetIndexesForType('variants', null);

        self::assertCount(1, $indexes);
        self::assertContains(SearchIndexer::INDEX_VARIANTS, $indexes);
    }

    public function testGetIndexesForTypeFiltersPages(): void
    {
        $indexes = $this->invokeGetIndexesForType('pages', null);

        self::assertSame([SearchIndexer::INDEX_PAGES], $indexes);
    }

    public function testGetIndexesForTypeFiltersEvents(): void
    {
        $indexes = $this->invokeGetIndexesForType('events', null);

        self::assertSame([SearchIndexer::INDEX_EVENTS], $indexes);
    }

    public function testGetIndexesForTypeFiltersDecks(): void
    {
        $indexes = $this->invokeGetIndexesForType('decks', null);

        self::assertSame([SearchIndexer::INDEX_DECKS], $indexes);
    }

    public function testGetIndexesForTypeReturnsEmptyForUnknownFilter(): void
    {
        $indexes = $this->invokeGetIndexesForType('unknown', null);

        self::assertSame([], $indexes);
    }

    // ── Channel-aware filtering ────────────────────────────────────────

    public function testEnabledTypesFiltersOutArchetypes(): void
    {
        $indexes = $this->invokeGetIndexesForType(null, ['pages', 'decks', 'events']);

        self::assertNotContains(SearchIndexer::INDEX_ARCHETYPES, $indexes);
        self::assertNotContains(SearchIndexer::INDEX_VARIANTS, $indexes);
        self::assertContains(SearchIndexer::INDEX_PAGES, $indexes);
        self::assertContains(SearchIndexer::INDEX_EVENTS, $indexes);
        self::assertContains(SearchIndexer::INDEX_DECKS, $indexes);
    }

    public function testEnabledTypesFiltersOutDecksAndEvents(): void
    {
        $indexes = $this->invokeGetIndexesForType(null, ['pages', 'archetypes']);

        self::assertContains(SearchIndexer::INDEX_ARCHETYPES, $indexes);
        self::assertContains(SearchIndexer::INDEX_VARIANTS, $indexes);
        self::assertContains(SearchIndexer::INDEX_PAGES, $indexes);
        self::assertNotContains(SearchIndexer::INDEX_EVENTS, $indexes);
        self::assertNotContains(SearchIndexer::INDEX_DECKS, $indexes);
    }

    public function testEnabledTypesOnlyPages(): void
    {
        $indexes = $this->invokeGetIndexesForType(null, ['pages']);

        self::assertSame([SearchIndexer::INDEX_PAGES], $indexes);
    }

    public function testEnabledTypesWithTypeFilterCombined(): void
    {
        // Type filter asks for archetypes, but channel only has pages
        $indexes = $this->invokeGetIndexesForType('archetypes', ['pages']);

        self::assertSame([], $indexes);
    }

    public function testNullEnabledTypesReturnsAll(): void
    {
        $indexes = $this->invokeGetIndexesForType(null, null);

        self::assertCount(5, $indexes);
    }

    // ── indexToType ────────────────────────────────────────────────────

    public function testIndexToTypeMapping(): void
    {
        self::assertSame('archetypes', $this->invokeIndexToType(SearchIndexer::INDEX_ARCHETYPES));
        self::assertSame('variants', $this->invokeIndexToType(SearchIndexer::INDEX_VARIANTS));
        self::assertSame('pages', $this->invokeIndexToType(SearchIndexer::INDEX_PAGES));
        self::assertSame('events', $this->invokeIndexToType(SearchIndexer::INDEX_EVENTS));
        self::assertSame('decks', $this->invokeIndexToType(SearchIndexer::INDEX_DECKS));
        self::assertSame('unknown', $this->invokeIndexToType('nonexistent'));
    }

    // ── buildSearchParams ──────────────────────────────────────────────

    public function testBuildSearchParamsForArchetypesIncludesLocaleFilter(): void
    {
        $params = $this->invokeBuildSearchParams(SearchIndexer::INDEX_ARCHETYPES, 'fr', null, 10, 0);

        self::assertSame("locale = 'fr'", $params['filter']);
        self::assertSame(10, $params['limit']);
        self::assertSame(0, $params['offset']);
        self::assertSame('<mark>', $params['highlightPreTag']);
    }

    public function testBuildSearchParamsForPagesIncludesLocaleFilter(): void
    {
        $params = $this->invokeBuildSearchParams(SearchIndexer::INDEX_PAGES, 'en', null, 20, 5);

        self::assertSame("locale = 'en'", $params['filter']);
    }

    public function testBuildSearchParamsForEventsHasNoLocaleFilter(): void
    {
        $params = $this->invokeBuildSearchParams(SearchIndexer::INDEX_EVENTS, 'fr', null, 10, 0);

        self::assertArrayNotHasKey('filter', $params);
    }

    public function testBuildSearchParamsForDecksHasNoLocaleFilter(): void
    {
        $params = $this->invokeBuildSearchParams(SearchIndexer::INDEX_DECKS, 'en', null, 10, 0);

        self::assertArrayNotHasKey('filter', $params);
    }

    public function testBuildSearchParamsForPagesWithChannelCodeIncludesBothFilters(): void
    {
        $params = $this->invokeBuildSearchParams(SearchIndexer::INDEX_PAGES, 'en', 'content', 10, 0);

        self::assertSame("locale = 'en' AND channelCode = 'content'", $params['filter']);
    }

    public function testBuildSearchParamsChannelCodeIgnoredForNonPageIndexes(): void
    {
        $params = $this->invokeBuildSearchParams(SearchIndexer::INDEX_ARCHETYPES, 'fr', 'content', 10, 0);

        self::assertSame("locale = 'fr'", $params['filter']);
    }

    public function testBuildSearchParamsIncludesRankingScoreThreshold(): void
    {
        $params = $this->invokeBuildSearchParams(SearchIndexer::INDEX_DECKS, 'en', null, 10, 0);

        self::assertArrayHasKey('rankingScoreThreshold', $params);
        self::assertIsFloat($params['rankingScoreThreshold']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function createService(): SearchService
    {
        return (new \ReflectionClass(SearchService::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param list<string>|null $enabledTypes
     *
     * @return list<string>
     */
    private function invokeGetIndexesForType(?string $typeFilter, ?array $enabledTypes): array
    {
        $method = new \ReflectionMethod(SearchService::class, 'getIndexesForType');

        /** @var list<string> $result */
        $result = $method->invoke($this->createService(), $typeFilter, $enabledTypes);

        return $result;
    }

    private function invokeIndexToType(string $indexName): string
    {
        $method = new \ReflectionMethod(SearchService::class, 'indexToType');

        /** @var string $result */
        $result = $method->invoke($this->createService(), $indexName);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeBuildSearchParams(string $indexName, string $locale, ?string $channelCode, int $limit, int $offset): array
    {
        $method = new \ReflectionMethod(SearchService::class, 'buildSearchParams');

        /** @var array<string, mixed> $result */
        $result = $method->invoke($this->createService(), $indexName, $locale, $channelCode, $limit, $offset);

        return $result;
    }
}
