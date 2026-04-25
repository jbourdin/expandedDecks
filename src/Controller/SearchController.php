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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Full-page search results with type-grouped display.
 *
 * @see docs/features.md F18.2 — Global search results page
 */
class SearchController extends AbstractController
{
    /**
     * @see docs/features.md F18.2 — Global search results page
     */
    #[Route('/{_locale}/search', name: 'app_search', methods: ['GET'], requirements: ['_locale' => 'en|fr'])]
    public function search(Request $request, SearchService $searchService): Response
    {
        $query = trim($request->query->getString('q'));
        $typeFilter = $request->query->getString('type') ?: null;
        $locale = $request->getLocale();

        $validTypes = ['archetypes', 'pages', 'events', 'decks'];
        if (null !== $typeFilter && !\in_array($typeFilter, $validTypes, true)) {
            $typeFilter = null;
        }

        $results = '' !== $query ? $searchService->searchAll($query, $locale, $typeFilter) : [
            'archetypes' => [],
            'pages' => [],
            'events' => [],
            'decks' => [],
        ];

        $totalCount = array_sum(array_map('count', $results));

        return $this->render('search/results.html.twig', [
            'query' => $query,
            'typeFilter' => $typeFilter,
            'results' => $results,
            'totalCount' => $totalCount,
        ]);
    }
}
