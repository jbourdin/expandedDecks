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

namespace App\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use function Symfony\Component\String\u;

/**
 * SEO helper filters for templates.
 *
 * @see docs/features.md F19.7 — Meta descriptions on all indexable pages
 */
class SeoExtension extends AbstractExtension
{
    /**
     * Recommended upper bound for a `<meta name="description">`; search engines
     * truncate longer snippets anyway.
     */
    public const int META_DESCRIPTION_MAX_LENGTH = 160;

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('seo_truncate', $this->truncate(...)),
        ];
    }

    /**
     * Collapse whitespace and truncate on a word boundary (never mid-word),
     * appending an ellipsis when the text is cut.
     */
    public function truncate(?string $text, int $length = self::META_DESCRIPTION_MAX_LENGTH): string
    {
        if (null === $text) {
            return '';
        }

        $normalized = u($text)->collapseWhitespace();

        if ($normalized->isEmpty()) {
            return '';
        }

        return $normalized->truncate($length, '…', cut: false)->toString();
    }
}
