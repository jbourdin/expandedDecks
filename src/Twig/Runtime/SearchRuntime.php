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

namespace App\Twig\Runtime;

use App\Service\Search\SearchResult;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @see docs/features.md F18.2 — Global search results page
 */
class SearchRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function searchResultUrl(SearchResult $result): string
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';

        return match ($result->type) {
            'archetype' => $this->urlGenerator->generate('app_archetype_show', ['slug' => $result->slug, '_locale' => $locale]),
            'page' => $this->urlGenerator->generate('app_page_show', ['slug' => $result->slug, '_locale' => $locale]),
            'event' => $this->urlGenerator->generate('app_event_show', ['id' => $result->slug]),
            'deck' => $this->urlGenerator->generate('app_deck_show', ['short_tag' => $result->slug]),
            default => '/',
        };
    }
}
