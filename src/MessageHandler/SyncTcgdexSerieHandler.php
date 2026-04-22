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
use App\Enum\SyncMode;
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
    private const string BASE_URL = 'https://api.tcgdex.net/v2/en';

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
        $mode = $message->mode;

        $this->throttle->waitIfNeeded();

        try {
            $response = $this->tcgdexClient->request('GET', self::BASE_URL.'/series/'.$serieId);
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
            } elseif (SyncMode::Insert !== $mode) {
                // Update/full mode: always sync existing sets (metadata + potential new cards)
                $setsToSync[] = ['setId' => $setId, 'isNew' => false, 'releaseDate' => $releaseDate];
            } else {
                // Insert mode: only sync if card count changed
                $apiCardCount = $this->extractTotalCardCount($setData);
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
            $this->messageBus->dispatch(new SyncTcgdexSetMessage($setToSync['setId'], $mode));
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
     * Extract the total card count from the nested cardCount object.
     *
     * API format: {"cardCount": {"official": 162, "total": 218}}
     *
     * @param array<string, mixed> $setData
     */
    private function extractTotalCardCount(array $setData): ?int
    {
        if (!isset($setData['cardCount']) || !\is_array($setData['cardCount'])) {
            return null;
        }

        $total = $setData['cardCount']['total'] ?? null;

        return \is_int($total) ? $total : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractString(array $data, string $key): ?string
    {
        return isset($data[$key]) && \is_string($data[$key]) ? $data[$key] : null;
    }
}
