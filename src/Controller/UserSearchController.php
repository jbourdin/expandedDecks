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

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F3.5 — Assign event staff team
 */
#[Route('/api/user')]
#[IsGranted('ROLE_ORGANIZER')]
class UserSearchController extends AbstractController
{
    /**
     * @see docs/features.md F3.5 — Assign event staff team
     */
    #[Route('/search', name: 'app_user_search', methods: ['GET'])]
    public function search(Request $request, UserRepository $userRepository): JsonResponse
    {
        $query = trim($request->query->getString('q'));

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        $users = $userRepository->searchUsers($query);

        $results = array_map(static fn ($user) => [
            'screenName' => $user->getScreenName(),
            'email' => $user->getEmail(),
            'playerId' => $user->getPlayerId(),
        ], $users);

        return $this->json($results);
    }
}
