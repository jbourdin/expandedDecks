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

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
 */
class EventSearchController extends AbstractController
{
    #[Route('/api/event/search', name: 'app_event_search', methods: ['GET'])]
    public function search(Request $request, EventRepository $eventRepository): JsonResponse
    {
        $query = trim($request->query->getString('q'));

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        $events = $eventRepository->searchByName($query);

        $results = array_map(static fn ($event) => [
            'id' => $event->getId(),
            'name' => $event->getName(),
            'date' => $event->getDate()->format('Y-m-d'),
            'location' => $event->getLocation(),
        ], $events);

        return $this->json($results);
    }
}
