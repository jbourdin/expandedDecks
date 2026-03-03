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

/**
 * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
 */
class DeckOwnerSearchController extends AbstractController
{
    #[Route('/api/deck-owner/search', name: 'app_deck_owner_search', methods: ['GET'])]
    public function search(Request $request, UserRepository $userRepository): JsonResponse
    {
        $query = trim($request->query->getString('q'));

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        $users = $userRepository->searchDeckOwners($query);

        $results = array_map(static fn ($user) => [
            'id' => $user->getId(),
            'screenName' => $user->getScreenName(),
        ], $users);

        return $this->json($results);
    }
}
