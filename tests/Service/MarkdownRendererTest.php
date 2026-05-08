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

use App\Service\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

/**
 * @see https://github.com/jbourdin/expandedDecks/issues/537
 */
class MarkdownRendererTest extends TestCase
{
    public function testExternalLinkGetsTargetBlankAndRel(): void
    {
        $renderer = new MarkdownRenderer();

        $html = $renderer->render('[example](https://example.com)');

        self::assertStringContainsString('target="_blank"', $html);
        self::assertStringContainsString('rel="noopener noreferrer"', $html);
        self::assertStringContainsString('href="https://example.com"', $html);
    }

    public function testExternalLinkPreservesNoopenerNoreferrer(): void
    {
        $renderer = new MarkdownRenderer();

        // Inspect the full rel attribute value — order isn't guaranteed by CommonMark
        // so check both tokens are present.
        $html = $renderer->render('See [the docs](https://docs.example.org/foo).');

        self::assertMatchesRegularExpression('/rel="[^"]*\bnoopener\b/', $html);
        self::assertMatchesRegularExpression('/rel="[^"]*\bnoreferrer\b/', $html);
    }

    public function testRelativeLinkDoesNotGetTargetBlank(): void
    {
        $renderer = new MarkdownRenderer();

        // Internal/relative links should NOT open in a new tab — only external ones.
        $html = $renderer->render('[contact](/contact)');

        self::assertStringNotContainsString('target="_blank"', $html);
        self::assertStringContainsString('href="/contact"', $html);
    }

    public function testJavascriptLinkIsStripped(): void
    {
        $renderer = new MarkdownRenderer();

        // `allow_unsafe_links: false` (kept from the previous config) prevents
        // javascript: hrefs from being rendered as a usable link.
        $html = $renderer->render('[click](javascript:alert(1))');

        self::assertStringNotContainsString('javascript:', $html);
    }
}
