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

namespace App\Controller;

use App\Service\Search\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * JSON API endpoint for navbar quick-search autocomplete.
 *
 * @see docs/features.md F18.3 — Quick-search autocomplete (navbar)
 */
class SearchApiController extends AbstractController
{
    /**
     * @see docs/features.md F18.3 — Quick-search autocomplete (navbar)
     */
    #[Route('/api/search/quick', name: 'app_search_api_quick', methods: ['GET'])]
    public function quickSearch(Request $request, SearchService $searchService, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $query = trim($request->query->getString('q'));
        $locale = $request->getLocale();

        if (mb_strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $results = $searchService->quickSearch($query, $locale);

        $groups = [];

        foreach ($results as $type => $items) {
            if ([] === $items) {
                continue;
            }

            $group = [
                'type' => $type,
                'items' => [],
            ];

            foreach ($items as $result) {
                $group['items'][] = [
                    'title' => strip_tags($result->title),
                    'type' => $result->type,
                    'url' => $this->buildResultUrl($result, $locale, $urlGenerator),
                    'secondary' => $result->secondaryInfo,
                ];
            }

            $groups[] = $group;
        }

        return new JsonResponse($groups);
    }

    private function buildResultUrl(
        \App\Service\Search\SearchResult $result,
        string $locale,
        UrlGeneratorInterface $urlGenerator,
    ): string {
        return match ($result->type) {
            'archetype' => $urlGenerator->generate('app_archetype_show', ['slug' => $result->slug, '_locale' => $locale]),
            'variant' => $urlGenerator->generate('app_archetype_show', ['slug' => $result->archetypeSlug ?? '', '_locale' => $locale]).'#'.$result->slug,
            'page' => $urlGenerator->generate('app_page_show', ['slug' => $result->slug, '_locale' => $locale]),
            'event' => $urlGenerator->generate('app_event_show', ['id' => $result->slug]),
            'deck' => $urlGenerator->generate('app_deck_show', ['short_tag' => $result->slug]),
            default => '/',
        };
    }
}
