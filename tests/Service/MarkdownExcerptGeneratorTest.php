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

use App\Service\MarkdownExcerptGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F21.1 — RSS feed per page category
 */
class MarkdownExcerptGeneratorTest extends TestCase
{
    private MarkdownExcerptGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MarkdownExcerptGenerator();
    }

    public function testToPlainTextRemovesArchetypeTags(): void
    {
        $result = $this->generator->toPlainText('Check out [[archetype:kyurem]] for details.');

        self::assertSame('Check out for details.', $result);
    }

    public function testToPlainTextRemovesDeckTags(): void
    {
        $result = $this->generator->toPlainText('See [[deck:ABC123]] deck list.');

        self::assertSame('See deck list.', $result);
    }

    public function testToPlainTextRemovesCardTags(): void
    {
        $result = $this->generator->toPlainText('Uses [[card:swsh9/TG30]] effectively.');

        self::assertSame('Uses effectively.', $result);
    }

    public function testToPlainTextRemovesLinks(): void
    {
        $result = $this->generator->toPlainText('Visit [our site](https://example.com) for more.');

        self::assertSame('Visit our site for more.', $result);
    }

    public function testToPlainTextRemovesImages(): void
    {
        $result = $this->generator->toPlainText('Look at ![card image](https://example.com/image.png) here.');

        self::assertSame('Look at here.', $result);
    }

    public function testToPlainTextRemovesHeadings(): void
    {
        $result = $this->generator->toPlainText("## Strategy\nThe deck focuses on...");

        self::assertSame('Strategy The deck focuses on...', $result);
    }

    public function testToPlainTextRemovesBoldAndItalic(): void
    {
        $result = $this->generator->toPlainText('This is **bold** and *italic* and __underline__.');

        self::assertSame('This is bold and italic and underline.', $result);
    }

    public function testToPlainTextCollapsesWhitespace(): void
    {
        $result = $this->generator->toPlainText("Line one.\n\n\n  Line two.  \n\nLine three.");

        self::assertSame('Line one. Line two. Line three.', $result);
    }

    public function testToPlainTextHandlesEmptyString(): void
    {
        $result = $this->generator->toPlainText('');

        self::assertSame('', $result);
    }

    public function testExcerptReturnsFirstParagraph(): void
    {
        $markdown = "First paragraph with **bold** text.\n\nSecond paragraph that should not appear.";

        $result = $this->generator->excerpt($markdown);

        self::assertSame('First paragraph with bold text.', $result);
    }

    public function testExcerptSkipsLeadingHeading(): void
    {
        $markdown = "## Your shared deck library\n\nThe real first paragraph of the page.\n\nAnother paragraph.";

        $result = $this->generator->excerpt($markdown);

        self::assertSame('The real first paragraph of the page.', $result);
    }

    public function testExcerptSkipsLeadingImageOnlyBlock(): void
    {
        $markdown = "![banner](https://example.com/banner.png)\n\nText after the banner image.";

        $result = $this->generator->excerpt($markdown);

        self::assertSame('Text after the banner image.', $result);
    }

    public function testExcerptTruncatesLongParagraphs(): void
    {
        $markdown = str_repeat('word ', 100);

        $result = $this->generator->excerpt($markdown, 50);

        self::assertLessThanOrEqual(51, mb_strlen($result));
        self::assertStringEndsWith('…', $result);
    }

    public function testExcerptReturnsEmptyStringWhenNoParagraph(): void
    {
        $result = $this->generator->excerpt("## Only a heading\n\n### Another heading");

        self::assertSame('', $result);
    }
}
