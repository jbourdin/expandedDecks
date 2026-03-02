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

use App\Entity\User;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 */
#[Route('/api/deck')]
#[IsGranted('ROLE_USER')]
class DeckSearchController extends AbstractController
{
    /**
     * @see docs/features.md F4.12 — Walk-up lending (direct lend)
     */
    #[Route('/search', name: 'app_deck_search', methods: ['GET'])]
    public function search(Request $request, DeckRepository $deckRepository, EventRepository $eventRepository): JsonResponse
    {
        $query = trim($request->query->getString('q'));
        $eventId = $request->query->getInt('event_id');

        if (0 === $eventId) {
            return $this->json([]);
        }

        $event = $eventRepository->find($eventId);
        if (null === $event) {
            return $this->json([]);
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$event->isOrganizerOrStaff($user) && null === $event->getEngagementFor($user)) {
            return $this->json([]);
        }

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        $decks = $deckRepository->searchAvailableForEvent($query, $event);

        $results = array_map(static fn ($deck) => [
            'id' => $deck->getId(),
            'name' => $deck->getName(),
            'shortTag' => $deck->getShortTag(),
            'ownerName' => $deck->getOwner()->getScreenName(),
            'status' => $deck->getStatus()->value,
        ], $decks);

        return $this->json($results);
    }
}
