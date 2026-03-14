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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface as MessengerSerializerInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Receives messages pushed by an external queue service (e.g. AWS SQS via SNS)
 * and dispatches them to the Messenger bus for synchronous processing.
 *
 * Authentication is via a shared secret compared against the X-Messenger-Signature header.
 *
 * @see docs/features.md F14.3 — SQS-compatible webhook message consumer
 */
class MessengerWebhookController
{
    private const array ALLOWED_TRANSPORTS = [
        'transactional_email',
        'deck_enrichment',
        'notification',
        'borrow_lifecycle',
    ];

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly MessengerSerializerInterface $messengerSerializer,
        private readonly LoggerInterface $logger,
        private readonly string $webhookSecret,
    ) {
    }

    #[Route('/webhook/messenger/{transport}', name: 'app_messenger_webhook', methods: ['POST'])]
    public function __invoke(string $transport, Request $request): JsonResponse
    {
        if ('' === $this->webhookSecret) {
            $this->logger->error('Messenger webhook called but MESSENGER_WEBHOOK_SECRET is not configured.');

            return new JsonResponse(['error' => 'Webhook not configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (!\in_array($transport, self::ALLOWED_TRANSPORTS, true)) {
            return new JsonResponse(['error' => 'Unknown transport'], Response::HTTP_NOT_FOUND);
        }

        $signature = $request->headers->get('X-Messenger-Signature', '');
        $body = (string) $request->getContent();

        if (!hash_equals(hash_hmac('sha256', $body, $this->webhookSecret), (string) $signature)) {
            $this->logger->warning('Messenger webhook authentication failed for transport "{transport}".', [
                'transport' => $transport,
            ]);

            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        try {
            $envelope = $this->messengerSerializer->decode([
                'body' => $body,
                'headers' => [],
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Messenger webhook deserialization failed: {message}', [
                'message' => $exception->getMessage(),
                'transport' => $transport,
            ]);

            return new JsonResponse(['error' => 'Bad request'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->messageBus->dispatch($envelope->with(new ReceivedStamp($transport)));
        } catch (\Throwable $exception) {
            $this->logger->error('Messenger webhook handler failed: {message}', [
                'message' => $exception->getMessage(),
                'transport' => $transport,
                'exception' => $exception,
            ]);

            return new JsonResponse(['error' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
