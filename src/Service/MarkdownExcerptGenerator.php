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

namespace App\Service;

use function Symfony\Component\String\u;

/**
 * Converts Markdown content to plain text and short excerpts.
 *
 * Used by the search indexer (plain-text documents) and the RSS feed
 * (item descriptions). Strips the project's custom content tags
 * ([[archetype:…]], [[deck:…]], [[card:…]]) alongside standard Markdown.
 *
 * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
 * @see docs/features.md F21.1 — RSS feed per page category
 */
class MarkdownExcerptGenerator
{
    /**
     * Strip Markdown formatting and custom content tags, returning plain text.
     */
    public function toPlainText(string $markdown): string
    {
        // Remove archetype/deck/card custom tags: [[archetype:slug]], [[deck:TAG]], [[card:...]]
        $text = (string) preg_replace('/\[\[(archetype|deck|card):[^\]]+\]\]/', '', $markdown);

        // Remove images ![alt](url) — must run before link and bold/italic removal
        $text = (string) preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', $text);

        // Remove Markdown links [text](url)
        $text = (string) preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);

        // Remove headings (#, ##, etc.)
        $text = (string) preg_replace('/^#{1,6}\s+/m', '', $text);

        // Remove bold/italic markers
        $text = str_replace(['**', '__', '*', '_'], '', $text);

        // Collapse whitespace
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Build a short plain-text excerpt: the first paragraph of the Markdown
     * content (skipping leading headings and images), truncated to $maxLength.
     */
    public function excerpt(string $markdown, int $maxLength = 300): string
    {
        // Split on blank lines into paragraph blocks (before whitespace collapsing,
        // which would erase the paragraph boundaries).
        $blocks = preg_split('/\R\s*\R/', $markdown) ?: [];

        foreach ($blocks as $block) {
            // Skip heading blocks — a title is not a summary.
            if (1 === preg_match('/^\s*#{1,6}\s/', $block)) {
                continue;
            }

            $text = $this->toPlainText($block);

            if ('' === $text) {
                continue;
            }

            return u($text)->truncate($maxLength, '…', cut: false)->toString();
        }

        return '';
    }
}
