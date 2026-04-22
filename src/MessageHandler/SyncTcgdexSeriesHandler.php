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
use App\Message\SyncTcgdexCompleteMessage;
use App\Message\SyncTcgdexSerieMessage;
use App\Message\SyncTcgdexSeriesMessage;
use App\Repository\TcgdexSerieRepository;
use App\Service\Tcgdex\TcgdexApiThrottle;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Root handler: fetches all series from TCGdex, creates missing entities,
 * and dispatches SyncTcgdexSerieMessage for each (newest first).
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
#[AsMessageHandler]
class SyncTcgdexSeriesHandler
{
    /** Serie ID to exclude (Pokemon TCG Pocket, not relevant). */
    private const string EXCLUDED_SERIE = 'tcgp';

    public function __construct(
        private readonly HttpClientInterface $tcgdexClient,
        private readonly TcgdexApiThrottle $throttle,
        private readonly TcgdexSerieRepository $serieRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncTcgdexSeriesMessage $message): void
    {
        $this->logger->info('TCGdex sync: fetching all series…');

        $this->throttle->waitIfNeeded();

        try {
            $response = $this->tcgdexClient->request('GET', '/series');
            /** @var list<array<string, mixed>> $seriesList */
            $seriesList = $response->toArray();
        } catch (\Throwable $exception) {
            $this->throttle->reportFailure();
            $this->logger->error('TCGdex sync: failed to fetch series list: {error}', [
                'error' => $exception->getMessage(),
            ]);
            $this->messageBus->dispatch($message, [new DelayStamp(60000)]);

            return;
        }

        $this->throttle->reportSuccess();

        $existingIds = array_flip($this->serieRepository->findAllIds());
        $created = 0;

        foreach ($seriesList as $serieData) {
            $serieId = $this->extractString($serieData, 'id');

            if (null === $serieId || self::EXCLUDED_SERIE === $serieId) {
                continue;
            }

            if (!isset($existingIds[$serieId])) {
                $serie = new TcgdexSerie($serieId);

                $name = $this->extractString($serieData, 'name');

                if (null !== $name) {
                    $serie->setName(['en' => $name]);
                }

                $serie->setLogoUrl($this->extractString($serieData, 'logo'));

                $this->entityManager->persist($serie);
                ++$created;
            }
        }

        if ($created > 0) {
            $this->entityManager->flush();
            $this->logger->info('TCGdex sync: created {count} new series.', ['count' => $created]);
        }

        // Sort by releaseDate DESC (newest first) and dispatch for all series
        usort($seriesList, static function (array $itemA, array $itemB): int {
            $dateA = (isset($itemA['releaseDate']) && \is_string($itemA['releaseDate'])) ? $itemA['releaseDate'] : '0000-00-00';
            $dateB = (isset($itemB['releaseDate']) && \is_string($itemB['releaseDate'])) ? $itemB['releaseDate'] : '0000-00-00';

            return $dateB <=> $dateA;
        });

        $dispatched = 0;

        foreach ($seriesList as $serieData) {
            $serieId = $this->extractString($serieData, 'id');

            if (null === $serieId || self::EXCLUDED_SERIE === $serieId) {
                continue;
            }

            $this->messageBus->dispatch(new SyncTcgdexSerieMessage($serieId));
            ++$dispatched;
        }

        $this->logger->info('TCGdex sync: dispatched {count} serie sync messages.', ['count' => $dispatched]);

        // Schedule post-sync completion (10 min delay to let the cascade finish)
        $this->messageBus->dispatch(new SyncTcgdexCompleteMessage(), [new DelayStamp(600_000)]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractString(array $data, string $key): ?string
    {
        return isset($data[$key]) && \is_string($data[$key]) ? $data[$key] : null;
    }
}
