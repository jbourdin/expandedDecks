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
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
 */
class SearchIndexerTest extends TestCase
{
    public function testStripMarkdownRemovesArchetypeTags(): void
    {
        $result = $this->invokeStripMarkdown('Check out [[archetype:kyurem]] for details.');

        self::assertSame('Check out for details.', $result);
    }

    public function testStripMarkdownRemovesDeckTags(): void
    {
        $result = $this->invokeStripMarkdown('See [[deck:ABC123]] deck list.');

        self::assertSame('See deck list.', $result);
    }

    public function testStripMarkdownRemovesCardTags(): void
    {
        $result = $this->invokeStripMarkdown('Uses [[card:swsh9/TG30]] effectively.');

        self::assertSame('Uses effectively.', $result);
    }

    public function testStripMarkdownRemovesLinks(): void
    {
        $result = $this->invokeStripMarkdown('Visit [our site](https://example.com) for more.');

        self::assertSame('Visit our site for more.', $result);
    }

    public function testStripMarkdownRemovesImages(): void
    {
        $result = $this->invokeStripMarkdown('Look at ![card image](https://example.com/image.png) here.');

        self::assertSame('Look at here.', $result);
    }

    public function testStripMarkdownRemovesHeadings(): void
    {
        $result = $this->invokeStripMarkdown("## Strategy\nThe deck focuses on...");

        self::assertSame('Strategy The deck focuses on...', $result);
    }

    public function testStripMarkdownRemovesBoldAndItalic(): void
    {
        $result = $this->invokeStripMarkdown('This is **bold** and *italic* and __underline__.');

        self::assertSame('This is bold and italic and underline.', $result);
    }

    public function testStripMarkdownCollapsesWhitespace(): void
    {
        $result = $this->invokeStripMarkdown("Line one.\n\n\n  Line two.  \n\nLine three.");

        self::assertSame('Line one. Line two. Line three.', $result);
    }

    public function testStripMarkdownHandlesEmptyString(): void
    {
        $result = $this->invokeStripMarkdown('');

        self::assertSame('', $result);
    }

    public function testIndexConstants(): void
    {
        self::assertSame('archetypes', SearchIndexer::INDEX_ARCHETYPES);
        self::assertSame('pages', SearchIndexer::INDEX_PAGES);
        self::assertSame('events', SearchIndexer::INDEX_EVENTS);
        self::assertSame('decks', SearchIndexer::INDEX_DECKS);
    }

    private function invokeStripMarkdown(string $markdown): string
    {
        $reflectionMethod = new \ReflectionMethod(SearchIndexer::class, 'stripMarkdown');

        // Create a minimal instance — stripMarkdown is a pure function
        $indexer = (new \ReflectionClass(SearchIndexer::class))->newInstanceWithoutConstructor();

        /** @var string $result */
        $result = $reflectionMethod->invoke($indexer, $markdown);

        return $result;
    }
}
