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

namespace App\Tests\Twig\Extension;

use App\Twig\Extension\SeoExtension;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F19.7 — Meta descriptions on all indexable pages
 */
class SeoExtensionTest extends TestCase
{
    private SeoExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new SeoExtension();
    }

    public function testNullBecomesEmptyString(): void
    {
        self::assertSame('', $this->extension->truncate(null));
    }

    public function testShortTextIsReturnedUnchanged(): void
    {
        self::assertSame('A concise summary.', $this->extension->truncate('A concise summary.'));
    }

    public function testWhitespaceIsCollapsed(): void
    {
        self::assertSame('A summary with gaps.', $this->extension->truncate("A   summary\n with    gaps."));
    }

    public function testLongTextIsTruncatedOnAWordBoundaryWithEllipsis(): void
    {
        $text = str_repeat('word ', 60); // 300 chars, well over the limit
        $result = $this->extension->truncate($text);

        self::assertStringEndsWith('…', $result);
        // Truncated: shorter than the source, but rounded up to a word boundary
        // so it stays in the neighbourhood of the limit (never mid-word).
        self::assertLessThan(mb_strlen($text), mb_strlen($result));
        self::assertStringNotContainsString('wor…', $result);
    }

    public function testCustomLengthIsHonored(): void
    {
        $result = $this->extension->truncate('one two three four five six seven', 10);

        self::assertStringEndsWith('…', $result);
        // Word-boundary truncation rounds up to the next boundary after the limit.
        self::assertSame('one two three…', $result);
    }

    public function testFilterIsRegistered(): void
    {
        $names = array_map(static fn ($filter) => $filter->getName(), $this->extension->getFilters());

        self::assertContains('seo_truncate', $names);
    }
}
