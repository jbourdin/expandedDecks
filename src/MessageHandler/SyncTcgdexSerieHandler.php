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

use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use App\Message\SyncTcgdexSerieMessage;
use App\Message\SyncTcgdexSetMessage;
use App\Repository\TcgdexCardRepository;
use App\Service\Tcgdex\TcgdexApiThrottle;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Syncs a single serie: fetches set list, creates missing sets, and dispatches
 * SyncTcgdexSetMessage for new or changed sets (sorted by release date DESC).
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
#[AsMessageHandler]
class SyncTcgdexSerieHandler
{
    public function __construct(
        private readonly HttpClientInterface $tcgdexClient,
        private readonly TcgdexApiThrottle $throttle,
        private readonly TcgdexCardRepository $cardRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncTcgdexSerieMessage $message): void
    {
        $serieId = $message->serieId;

        $this->throttle->waitIfNeeded();

        try {
            $response = $this->tcgdexClient->request('GET', '/series/'.$serieId);
            /** @var array<string, mixed> $serieData */
            $serieData = $response->toArray();
        } catch (\Throwable $exception) {
            $this->throttle->reportFailure();
            $this->logger->error('TCGdex sync: failed to fetch serie {serieId}: {error}', [
                'serieId' => $serieId,
                'error' => $exception->getMessage(),
            ]);
            $this->messageBus->dispatch($message, [new DelayStamp(60000)]);

            return;
        }

        $this->throttle->reportSuccess();

        /** @var list<array<string, mixed>> $setsData */
        $setsData = isset($serieData['sets']) && \is_array($serieData['sets']) ? $serieData['sets'] : [];

        $serie = $this->entityManager->find(TcgdexSerie::class, $serieId);

        if (null === $serie) {
            $this->logger->warning('TCGdex sync: serie {serieId} not found in database, skipping.', [
                'serieId' => $serieId,
            ]);

            return;
        }

        // Update serie logo if available
        $logo = $this->extractString($serieData, 'logo');

        if (null !== $logo) {
            $serie->setLogoUrl($logo);
        }

        // Identify existing sets by ID
        $existingSetIds = [];

        foreach ($serie->getSets() as $existingSet) {
            $existingSetIds[$existingSet->getId()] = true;
        }

        /** @var list<array{setId: string, isNew: bool, releaseDate: string}> $setsToSync */
        $setsToSync = [];
        $created = 0;

        foreach ($setsData as $setData) {
            $setId = $this->extractString($setData, 'id');

            if (null === $setId) {
                continue;
            }

            $releaseDate = $this->extractString($setData, 'releaseDate') ?? '0000-00-00';

            if (!isset($existingSetIds[$setId])) {
                // New set — create entity
                $set = new TcgdexSet($setId, $serie);

                $name = $this->extractString($setData, 'name');

                if (null !== $name) {
                    $set->setName(['en' => $name]);
                }

                $set->setLogoUrl($this->extractString($setData, 'logo'));
                $set->setSymbolUrl($this->extractString($setData, 'symbol'));

                $this->entityManager->persist($set);
                ++$created;

                $setsToSync[] = ['setId' => $setId, 'isNew' => true, 'releaseDate' => $releaseDate];
            } else {
                // Existing set — check if card count changed
                $apiCardCount = $this->extractInt($setData, 'cardCount');
                $localCardCount = $this->cardRepository->countBySetId($setId);

                if (null !== $apiCardCount && $apiCardCount !== $localCardCount) {
                    $setsToSync[] = ['setId' => $setId, 'isNew' => false, 'releaseDate' => $releaseDate];
                }
            }
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        // Sort by releaseDate DESC (newest first)
        usort($setsToSync, static fn (array $itemA, array $itemB): int => $itemB['releaseDate'] <=> $itemA['releaseDate']);

        foreach ($setsToSync as $setToSync) {
            $this->messageBus->dispatch(new SyncTcgdexSetMessage($setToSync['setId']));
        }

        if ([] !== $setsToSync) {
            $this->logger->info('TCGdex sync: serie {serieId} — {created} new sets, {total} to sync.', [
                'serieId' => $serieId,
                'created' => $created,
                'total' => \count($setsToSync),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractString(array $data, string $key): ?string
    {
        return isset($data[$key]) && \is_string($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractInt(array $data, string $key): ?int
    {
        return isset($data[$key]) && \is_int($data[$key]) ? $data[$key] : null;
    }
}
