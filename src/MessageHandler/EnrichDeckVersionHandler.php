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

use App\Message\EnrichDeckVersionMessage;
use App\Message\GenerateDeckMosaicMessage;
use App\Message\GenerateMinifiedListMessage;
use App\Repository\DeckVersionRepository;
use App\Service\Tcgdex\CardEnricher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
#[AsMessageHandler]
class EnrichDeckVersionHandler
{
    public function __construct(
        private readonly CardEnricher $cardEnricher,
        private readonly DeckVersionRepository $versionRepo,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(EnrichDeckVersionMessage $message): void
    {
        $version = $this->versionRepo->find($message->deckVersionId);

        if (null === $version) {
            $this->logger->warning('DeckVersion #{id} not found for enrichment.', [
                'id' => $message->deckVersionId,
            ]);

            return;
        }

        $report = $this->cardEnricher->enrichVersion($version);

        $this->logger->info('Enriched DeckVersion #{id}: {enriched} enriched, {notFound} not found.', [
            'id' => $message->deckVersionId,
            'enriched' => $report->enrichedCount,
            'notFound' => $report->notFoundCount,
        ]);

        if ([] !== $report->notFoundCards) {
            $this->logger->warning('Not found in TCGdex for DeckVersion #{id}: {cards}', [
                'id' => $message->deckVersionId,
                'cards' => implode('; ', $report->notFoundCards),
            ]);
        }

        if ([] !== $report->legalityWarnings) {
            $this->logger->warning('Legality warnings for DeckVersion #{id}: {warnings}', [
                'id' => $message->deckVersionId,
                'warnings' => implode('; ', $report->legalityWarnings),
            ]);
        }

        // Dispatch downstream generation if enrichment succeeded.
        // Standard mosaic uses DeckCard.imageUrl (set during enrichment) — safe to run in parallel.
        // Minified list must complete before minified mosaic (it populates CardPrinting via expandPrintings).
        // GenerateMinifiedListHandler dispatches GenerateMinifiedMosaicMessage after completion.
        if ('done' === $version->getEnrichmentStatus()) {
            $this->messageBus->dispatch(new GenerateDeckMosaicMessage($message->deckVersionId));
            $this->messageBus->dispatch(new GenerateMinifiedListMessage($message->deckVersionId));
        }
    }
}
