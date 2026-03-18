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

use App\Message\GenerateDeckMosaicMessage;
use App\Repository\DeckVersionRepository;
use App\Service\Mosaic\MosaicGenerator;
use App\Service\Mosaic\MosaicUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
#[AsMessageHandler]
class GenerateDeckMosaicHandler
{
    public function __construct(
        private readonly MosaicGenerator $mosaicGenerator,
        private readonly MosaicUrlResolver $mosaicUrlResolver,
        private readonly DeckVersionRepository $versionRepo,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateDeckMosaicMessage $message): void
    {
        $version = $this->versionRepo->find($message->deckVersionId);

        if (null === $version) {
            $this->logger->warning('DeckVersion #{id} not found for mosaic generation.', [
                'id' => $message->deckVersionId,
            ]);

            return;
        }

        if ('done' !== $version->getEnrichmentStatus()) {
            $this->logger->info('DeckVersion #{id} not fully enriched (status: {status}), skipping mosaic.', [
                'id' => $message->deckVersionId,
                'status' => $version->getEnrichmentStatus(),
            ]);

            return;
        }

        try {
            $this->mosaicGenerator->generate($version);
            $publicUrl = $this->mosaicUrlResolver->resolveForVersion($version);

            $version->setMosaicImageUrl($publicUrl);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->logger->error('Mosaic generation failed for DeckVersion #{id}: {error}', [
                'id' => $message->deckVersionId,
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
