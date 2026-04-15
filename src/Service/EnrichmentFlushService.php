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

namespace App\Service;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

/**
 * Flushes all enrichment-derived data, resetting deck versions to pre-enrichment state.
 *
 * Clears: DeckCard enrichment fields (tcgdexId, imageUrl, trainerSubtype, cardPrinting),
 * DeckVersion generated fields (enrichmentStatus, mosaicImageUrl, minifiedList, minifiedCardViews, minifiedMosaicImageUrl),
 * all CardPrinting and CardIdentity records, and all mosaic image files from storage.
 */
class EnrichmentFlushService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FilesystemOperator $mosaicStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function flush(): void
    {
        // 1. Clear DeckCard → CardPrinting FK (enrichment data lives on CardPrinting)
        $this->connection->executeStatement(
            'UPDATE deck_card SET card_printing_id = NULL',
        );

        // 2. Reset DeckVersion generated fields
        $this->connection->executeStatement(
            "UPDATE deck_version SET enrichment_status = 'pending', mosaic_image_url = NULL, minified_list = NULL, minified_card_views = NULL, minified_mosaic_image_url = NULL",
        );

        // 3. Delete all CardPrinting records (FK to CardIdentity)
        $this->connection->executeStatement('DELETE FROM card_printing');

        // 4. Delete all CardIdentity records
        $this->connection->executeStatement('DELETE FROM card_identity');

        // 5. Clear mosaic storage
        $this->clearMosaicStorage();

        $this->logger->warning('All enrichment data has been flushed.');
    }

    private function clearMosaicStorage(): void
    {
        try {
            $contents = $this->mosaicStorage->listContents('mosaic', true);

            foreach ($contents as $item) {
                if ($item->isFile()) {
                    $this->mosaicStorage->delete($item->path());
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to clear mosaic storage: {error}', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
