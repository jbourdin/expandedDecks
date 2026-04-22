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

use App\Message\BuildSetMappingsMessage;
use App\Message\EnrichDeckVersionMessage;
use App\Message\SyncTcgdexCompleteMessage;
use App\Repository\DeckVersionRepository;
use App\Service\Tcgdex\TcgdexSyncStatusService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Post-sync handler: rebuilds set mappings and re-enriches failed deck versions.
 *
 * This message is dispatched with a 10-minute delay by SyncTcgdexSeriesHandler
 * to allow the cascade to finish before triggering downstream actions.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
#[AsMessageHandler]
class SyncTcgdexCompleteHandler
{
    public function __construct(
        private readonly DeckVersionRepository $versionRepository,
        private readonly TcgdexSyncStatusService $syncStatus,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncTcgdexCompleteMessage $message): void
    {
        $this->logger->info('TCGdex sync complete: triggering post-sync actions.');

        $this->syncStatus->recordSyncCompleted();

        // Rebuild set mappings
        $this->messageBus->dispatch(new BuildSetMappingsMessage());

        // Re-enrich deck versions that failed or are still pending
        $notEnriched = $this->versionRepository->findNotEnriched();
        $dispatched = 0;

        foreach ($notEnriched as $version) {
            $versionId = $version->getId();

            if (null === $versionId) {
                continue;
            }

            $this->messageBus->dispatch(new EnrichDeckVersionMessage($versionId));
            ++$dispatched;
        }

        if ($dispatched > 0) {
            $this->logger->info('TCGdex sync complete: dispatched {count} re-enrichment messages.', [
                'count' => $dispatched,
            ]);
        }
    }
}
