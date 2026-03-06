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

use App\Service\PokemonEventSync\PokemonEventSyncException;
use App\Service\PokemonEventSync\PokemonEventSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Proxy endpoint for syncing event data from Pokemon event pages.
 *
 * @see docs/features.md F3.18 — Sync from Pokemon event page
 */
class EventSyncController extends AbstractController
{
    #[Route('/api/event/sync', name: 'app_event_sync', methods: ['POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function sync(Request $request, PokemonEventSyncService $syncService): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $tournamentId = isset($payload['tournamentId']) && \is_string($payload['tournamentId'])
            ? $payload['tournamentId']
            : '';

        try {
            $data = $syncService->fetchEventData($tournamentId);

            return $this->json([
                'success' => true,
                'data' => $data->toArray(),
            ]);
        } catch (PokemonEventSyncException $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        }
    }
}
