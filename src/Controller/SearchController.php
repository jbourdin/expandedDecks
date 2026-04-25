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

use App\Entity\Channel;
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

        $validTypes = ['archetypes', 'variants', 'pages', 'events', 'decks'];
        if (null !== $typeFilter && !\in_array($typeFilter, $validTypes, true)) {
            $typeFilter = null;
        }

        $channel = $request->attributes->get('_channel');
        $enabledTypes = $channel instanceof Channel ? self::getEnabledTypes($channel) : null;

        $results = '' !== $query ? $searchService->searchAll($query, $locale, $typeFilter, $enabledTypes) : [
            'archetypes' => [],
            'variants' => [],
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

    /**
     * Build the list of content types enabled on the current channel.
     *
     * @return list<string>
     */
    private static function getEnabledTypes(Channel $channel): array
    {
        $types = ['pages']; // pages always enabled

        if ($channel->getEnableArchetypes()) {
            $types[] = 'archetypes';
        }
        if ($channel->getEnableDecks()) {
            $types[] = 'decks';
        }
        if ($channel->getEnableEvents()) {
            $types[] = 'events';
        }

        return $types;
    }
}
