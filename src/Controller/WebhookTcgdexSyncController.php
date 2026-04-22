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

use App\Enum\SyncMode;
use App\Message\SyncTcgdexSeriesMessage;
use App\Service\Tcgdex\TcgdexSyncStatusService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Anonymous webhook endpoint for triggering TCGdex insert-mode sync.
 *
 * Protected by HMAC-SHA256 signature verification. Designed to be called
 * by a periodic serverless job (cron, Lambda, Scaleway Serverless).
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
class WebhookTcgdexSyncController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly TcgdexSyncStatusService $syncStatus,
        private readonly string $tcgdexSyncWebhookSecret,
    ) {
    }

    #[Route('/webhook/tcgdex-sync', name: 'app_webhook_tcgdex_sync', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ('' === $this->tcgdexSyncWebhookSecret) {
            return new JsonResponse(['error' => 'Webhook not configured.'], Response::HTTP_NOT_FOUND);
        }

        $signature = $request->headers->get('X-Sync-Signature', '');
        $body = $request->getContent();

        if (!$this->isSignatureValid($signature, $body)) {
            return new JsonResponse(['error' => 'Invalid signature.'], Response::HTTP_FORBIDDEN);
        }

        $queueDepth = $this->syncStatus->getQueueDepth();

        if ($queueDepth > 0) {
            return new JsonResponse([
                'status' => 'already_in_progress',
                'queue_depth' => $queueDepth,
            ]);
        }

        $this->messageBus->dispatch(new SyncTcgdexSeriesMessage(SyncMode::Insert));

        return new JsonResponse(['status' => 'dispatched'], Response::HTTP_ACCEPTED);
    }

    private function isSignatureValid(string $signature, string $body): bool
    {
        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $body, $this->tcgdexSyncWebhookSecret);

        return hash_equals($expected, $signature);
    }
}
