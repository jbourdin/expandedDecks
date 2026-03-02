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
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F3.5 — Assign event staff team
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 */
#[Route('/api/user')]
#[IsGranted('ROLE_USER')]
class UserSearchController extends AbstractController
{
    /**
     * @see docs/features.md F3.5 — Assign event staff team
     * @see docs/features.md F4.12 — Walk-up lending (direct lend)
     */
    #[Route('/search', name: 'app_user_search', methods: ['GET'])]
    public function search(Request $request, UserRepository $userRepository, EventRepository $eventRepository): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Event staff/organizer can search users for walk-up lending
        $eventId = $request->query->getInt('event_id');
        $hasEventAccess = false;

        if ($eventId > 0) {
            $event = $eventRepository->find($eventId);
            if (null !== $event && $event->isOrganizerOrStaff($currentUser)) {
                $hasEventAccess = true;
            }
        }

        if (!$hasEventAccess && !$this->isGranted('ROLE_ORGANIZER')) {
            return $this->json([]);
        }

        $query = trim($request->query->getString('q'));

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        $users = $userRepository->searchUsers($query);

        $results = array_map(static fn ($user) => [
            'id' => $user->getId(),
            'screenName' => $user->getScreenName(),
            'email' => $user->getEmail(),
            'playerId' => $user->getPlayerId(),
        ], $users);

        return $this->json($results);
    }
}
