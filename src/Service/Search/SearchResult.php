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

/**
 * A single search result returned by MeiliSearch.
 *
 * @see docs/features.md F18.2 — Global search results page
 */
final readonly class SearchResult
{
    public function __construct(
        public string $type,
        public string $title,
        public string $excerpt,
        public string $slug,
        public ?string $secondaryInfo = null,
    ) {
    }

    /**
     * @param array<string, mixed> $hit
     */
    public static function fromHit(array $hit): self
    {
        $type = \is_string($hit['type'] ?? null) ? $hit['type'] : 'unknown';
        /** @var array<string, mixed> $formatted */
        $formatted = \is_array($hit['_formatted'] ?? null) ? $hit['_formatted'] : $hit;

        return new self(
            type: $type,
            title: self::extractTitle($hit, $formatted),
            excerpt: self::extractExcerpt($formatted, $type),
            slug: self::extractSlug($hit, $type),
            secondaryInfo: self::extractSecondaryInfo($hit, $type),
        );
    }

    /**
     * @param array<string, mixed> $hit
     * @param array<string, mixed> $formatted
     */
    private static function extractTitle(array $hit, array $formatted): string
    {
        // Prefer highlighted title, fall back to raw
        foreach (['name', 'title'] as $field) {
            if (\is_string($formatted[$field] ?? null) && '' !== $formatted[$field]) {
                return $formatted[$field];
            }
            if (\is_string($hit[$field] ?? null) && '' !== $hit[$field]) {
                return $hit[$field];
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $formatted
     */
    private static function extractExcerpt(array $formatted, string $type): string
    {
        $field = match ($type) {
            'archetype' => 'description',
            'page' => 'content',
            'event' => 'description',
            default => null,
        };

        if (null !== $field && \is_string($formatted[$field] ?? null)) {
            $text = $formatted[$field];

            // Truncate to ~200 chars while preserving <mark> tags
            if (mb_strlen(strip_tags($text)) > 200) {
                $text = mb_substr($text, 0, 200).'…';
            }

            return $text;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $hit
     */
    private static function extractSlug(array $hit, string $type): string
    {
        return match ($type) {
            'deck' => \is_string($hit['shortTag'] ?? null) ? $hit['shortTag'] : '',
            'event' => \is_string($hit['id'] ?? null) ? $hit['id'] : (\is_int($hit['id'] ?? null) ? (string) $hit['id'] : ''),
            default => \is_string($hit['slug'] ?? null) ? $hit['slug'] : '',
        };
    }

    /**
     * @param array<string, mixed> $hit
     */
    private static function extractSecondaryInfo(array $hit, string $type): ?string
    {
        return match ($type) {
            'event' => \is_string($hit['date'] ?? null) ? $hit['date'] : null,
            'deck' => \is_string($hit['ownerName'] ?? null) ? $hit['ownerName'] : null,
            default => null,
        };
    }
}
