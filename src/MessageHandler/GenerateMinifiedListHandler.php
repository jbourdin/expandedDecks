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

use App\Message\GenerateMinifiedListMessage;
use App\Repository\DeckVersionRepository;
use App\Service\DeckList\MinifiedListGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @see docs/features.md F6.8 — Minified deck list export
 */
#[AsMessageHandler]
class GenerateMinifiedListHandler
{
    public function __construct(
        private readonly MinifiedListGenerator $listGenerator,
        private readonly DeckVersionRepository $versionRepo,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateMinifiedListMessage $message): void
    {
        $version = $this->versionRepo->find($message->deckVersionId);

        if (null === $version) {
            $this->logger->warning('DeckVersion #{id} not found for minified list generation.', [
                'id' => $message->deckVersionId,
            ]);

            return;
        }

        if ('done' !== $version->getEnrichmentStatus()) {
            $this->logger->info('DeckVersion #{id} not fully enriched, skipping minified list.', [
                'id' => $message->deckVersionId,
            ]);

            return;
        }

        try {
            $minifiedList = $this->listGenerator->generate($version);
            $version->setMinifiedList($minifiedList);
            $this->entityManager->flush();

            $this->logger->info('Minified list generated for DeckVersion #{id}.', [
                'id' => $message->deckVersionId,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Minified list generation failed for DeckVersion #{id}: {error}', [
                'id' => $message->deckVersionId,
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
