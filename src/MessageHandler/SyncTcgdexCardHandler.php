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

namespace App\MessageHandler;

use App\Entity\TcgdexCard;
use App\Entity\TcgdexSet;
use App\Message\SyncTcgdexCardMessage;
use App\Service\Tcgdex\TcgdexApiThrottle;
use App\Service\Tcgdex\TcgdexCardHydrator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches a single card from the TCGdex API and persists it via the hydrator.
 *
 * On 404: logs a warning and does not redispatch (card genuinely doesn't exist).
 * On other HTTP errors: redispatches with a delay for retry.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
#[AsMessageHandler]
class SyncTcgdexCardHandler
{
    private const string BASE_URL = 'https://api.tcgdex.net/v2/en';

    public function __construct(
        private readonly HttpClientInterface $tcgdexClient,
        private readonly TcgdexApiThrottle $throttle,
        private readonly TcgdexCardHydrator $hydrator,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncTcgdexCardMessage $message): void
    {
        $cardId = $message->cardId;
        $setId = $message->setId;

        // Skip if already exists (race condition guard)
        $existing = $this->entityManager->find(TcgdexCard::class, $cardId);

        if (null !== $existing) {
            return;
        }

        $this->throttle->waitIfNeeded();

        try {
            $response = $this->tcgdexClient->request('GET', self::BASE_URL.'/cards/'.$cardId);
            $statusCode = $response->getStatusCode();
        } catch (\Throwable $exception) {
            $this->throttle->reportFailure();
            $this->logger->error('TCGdex sync: failed to fetch card {cardId}: {error}', [
                'cardId' => $cardId,
                'error' => $exception->getMessage(),
            ]);
            $this->messageBus->dispatch($message, [new DelayStamp(60000)]);

            return;
        }

        if (404 === $statusCode) {
            $this->throttle->reportSuccess();
            $this->logger->warning('TCGdex sync: card {cardId} returned 404, skipping.', [
                'cardId' => $cardId,
            ]);

            return;
        }

        $this->throttle->reportSuccess();

        $set = $this->entityManager->find(TcgdexSet::class, $setId);

        if (null === $set) {
            $this->logger->warning('TCGdex sync: set {setId} not found for card {cardId}, skipping.', [
                'setId' => $setId,
                'cardId' => $cardId,
            ]);

            return;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = $response->toArray();
            $card = $this->hydrator->hydrateFromApiResponse($data, $set);
        } catch (\Throwable $exception) {
            $this->logger->error('TCGdex sync: failed to hydrate card {cardId}: {error}', [
                'cardId' => $cardId,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $this->entityManager->persist($card);
        $this->entityManager->flush();
    }
}
