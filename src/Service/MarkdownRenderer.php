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

use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * @see docs/features.md F11.3 — Page rendering & locale fallback
 * @see docs/features.md F17.4 — Image upload backend (Flysystem)
 */
class MarkdownRenderer
{
    private readonly MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // External links open in a new tab and gain rel="noopener noreferrer" to
            // prevent tab-nabbing. Internal links (no host or matching one of the
            // hosts in `internal_hosts`) keep their default in-tab behaviour.
            'external_link' => [
                'open_in_new_window' => true,
                'nofollow' => '',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new AttributesExtension());
        $environment->addExtension(new ExternalLinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Convert Markdown content to HTML.
     *
     * Supports Pandoc-style attributes: `{#id .class width=400 height=300}`
     *
     * @throws CommonMarkException
     */
    public function render(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
