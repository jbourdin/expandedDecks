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

use App\Message\BackfillDeckCardSortOrderMessage;
use App\Repository\DeckVersionRepository;
use App\Service\Deck\DeckCardSortBackfillService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Async wrapper around DeckCardSortBackfillService::backfillVersion() — loads
 * the version from its id and delegates to the synchronous service. Used by
 * the admin dashboard "Run backfill" button (which dispatches one message
 * per version).
 *
 * @see docs/features.md F2.28 — Preserve imported list order
 */
#[AsMessageHandler]
class BackfillDeckCardSortOrderHandler
{
    public function __construct(
        private readonly DeckVersionRepository $versionRepository,
        private readonly DeckCardSortBackfillService $backfillService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(BackfillDeckCardSortOrderMessage $message): void
    {
        $version = $this->versionRepository->find($message->deckVersionId);

        if (null === $version) {
            $this->logger->warning('DeckVersion #{id} not found for sort-order backfill.', [
                'id' => $message->deckVersionId,
            ]);

            return;
        }

        $report = $this->backfillService->backfillVersion($version);

        if ($report['skipped']) {
            return;
        }

        if ($report['missing'] > 0) {
            $this->logger->info('Backfill on DeckVersion #{id}: {changed} updated, {missing} DB cards not found in rawList.', [
                'id' => $message->deckVersionId,
                'changed' => $report['changed'],
                'missing' => $report['missing'],
            ]);
        }
    }
}
