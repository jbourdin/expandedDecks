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

namespace App\Service\Search;

use Meilisearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Queries MeiliSearch indexes and returns typed search results.
 *
 * Supports both full search (all indexes, grouped by type) and quick
 * search (limited results per type for navbar autocomplete).
 *
 * @see docs/features.md F18.2 — Global search results page
 * @see docs/features.md F18.3 — Quick-search autocomplete (navbar)
 */
class SearchService
{
    private const int QUICK_SEARCH_LIMIT = 3;
    private const int FULL_SEARCH_LIMIT = 20;

    private readonly Client $client;

    public function __construct(
        #[Autowire(env: 'MEILI_URL')]
        string $meilisearchUrl,
        #[Autowire(env: 'MEILI_MASTER_KEY')]
        string $meiliMasterKey,
        private readonly LoggerInterface $logger,
    ) {
        $this->client = new Client($meilisearchUrl, $meiliMasterKey);
    }

    /**
     * Search all indexes and return results grouped by content type.
     *
     * @return array{
     *     archetypes: list<SearchResult>,
     *     pages: list<SearchResult>,
     *     events: list<SearchResult>,
     *     decks: list<SearchResult>,
     * }
     */
    public function searchAll(string $query, string $locale, ?string $typeFilter = null, int $limit = self::FULL_SEARCH_LIMIT, int $offset = 0): array
    {
        $results = [
            'archetypes' => [],
            'pages' => [],
            'events' => [],
            'decks' => [],
        ];

        if ('' === trim($query)) {
            return $results;
        }

        $indexes = $this->getIndexesForType($typeFilter);

        foreach ($indexes as $indexName) {
            try {
                $searchParams = $this->buildSearchParams($indexName, $locale, $limit, $offset);
                $response = $this->client->index($indexName)->search($query, $searchParams);

                $type = $this->indexToType($indexName);
                /** @var array<string, mixed> $hit */
                foreach ($response->getHits() as $hit) {
                    $results[$type][] = SearchResult::fromHit($hit);
                }
            } catch (\Throwable $exception) {
                $this->logger->warning('Search query failed on index {index}: {error}', [
                    'index' => $indexName,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        // @phpstan-ignore return.type
        return $results;
    }

    /**
     * Quick search for navbar autocomplete — max 3 results per type.
     *
     * @return array{
     *     archetypes: list<SearchResult>,
     *     pages: list<SearchResult>,
     *     events: list<SearchResult>,
     *     decks: list<SearchResult>,
     * }
     */
    public function quickSearch(string $query, string $locale): array
    {
        return $this->searchAll($query, $locale, limit: self::QUICK_SEARCH_LIMIT);
    }

    /**
     * @return list<string>
     */
    private function getIndexesForType(?string $typeFilter): array
    {
        if (null !== $typeFilter) {
            return match ($typeFilter) {
                'archetypes' => [SearchIndexer::INDEX_ARCHETYPES],
                'pages' => [SearchIndexer::INDEX_PAGES],
                'events' => [SearchIndexer::INDEX_EVENTS],
                'decks' => [SearchIndexer::INDEX_DECKS],
                default => [],
            };
        }

        return [
            SearchIndexer::INDEX_ARCHETYPES,
            SearchIndexer::INDEX_PAGES,
            SearchIndexer::INDEX_EVENTS,
            SearchIndexer::INDEX_DECKS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSearchParams(string $indexName, string $locale, int $limit, int $offset): array
    {
        $params = [
            'limit' => $limit,
            'offset' => $offset,
            'attributesToHighlight' => ['*'],
            'highlightPreTag' => '<mark>',
            'highlightPostTag' => '</mark>',
        ];

        // Translatable indexes support locale filtering
        if (\in_array($indexName, [SearchIndexer::INDEX_ARCHETYPES, SearchIndexer::INDEX_PAGES], true)) {
            $params['filter'] = \sprintf("locale = '%s'", $locale);
        }

        return $params;
    }

    private function indexToType(string $indexName): string
    {
        return match ($indexName) {
            SearchIndexer::INDEX_ARCHETYPES => 'archetypes',
            SearchIndexer::INDEX_PAGES => 'pages',
            SearchIndexer::INDEX_EVENTS => 'events',
            SearchIndexer::INDEX_DECKS => 'decks',
            default => 'unknown',
        };
    }
}
