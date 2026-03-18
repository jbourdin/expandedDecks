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

use App\Entity\DeckCard;
use App\Message\GenerateMinifiedMosaicMessage;
use App\Repository\CardPrintingRepository;
use App\Repository\DeckVersionRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Mosaic\MosaicGenerator;
use App\Service\Mosaic\MosaicUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @see docs/features.md F6.6b — Minified mosaic
 */
#[AsMessageHandler]
class GenerateMinifiedMosaicHandler
{
    private const array BASIC_ENERGY_NAMES = [
        'Grass Energy', 'Fire Energy', 'Water Energy', 'Lightning Energy',
        'Psychic Energy', 'Fighting Energy', 'Darkness Energy', 'Metal Energy', 'Fairy Energy',
    ];

    public function __construct(
        private readonly MosaicGenerator $mosaicGenerator,
        private readonly MosaicUrlResolver $mosaicUrlResolver,
        private readonly CardPrintingRepository $printingRepository,
        private readonly CardIdentityResolver $identityResolver,
        private readonly DeckVersionRepository $versionRepo,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateMinifiedMosaicMessage $message): void
    {
        $version = $this->versionRepo->find($message->deckVersionId);

        if (null === $version) {
            $this->logger->warning('DeckVersion #{id} not found for minified mosaic.', [
                'id' => $message->deckVersionId,
            ]);

            return;
        }

        if ('done' !== $version->getEnrichmentStatus()) {
            return;
        }

        try {
            $imageUrlOverrides = $this->buildImageOverrides($version->getCards()->toArray());

            $storagePath = $this->mosaicGenerator->generate($version, 'minified', $imageUrlOverrides);
            $publicUrl = $this->mosaicUrlResolver->resolve($storagePath);

            $version->setMinifiedMosaicImageUrl($publicUrl);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->logger->error('Minified mosaic generation failed for DeckVersion #{id}: {error}', [
                'id' => $message->deckVersionId,
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    /**
     * Build a map of DeckCard ID → lowest-rarity image URL.
     *
     * @param array<int, DeckCard> $cards
     *
     * @return array<int, string>
     */
    private function buildImageOverrides(array $cards): array
    {
        $overrides = [];

        foreach ($cards as $card) {
            $cardId = $card->getId();
            $printing = $card->getCardPrinting();

            if (null === $cardId || null === $printing) {
                continue;
            }

            $identity = $printing->getCardIdentity();

            // Expand printings if needed
            if ($identity->getPrintings()->count() <= 1) {
                $this->identityResolver->expandPrintings($identity);
            }

            if (\in_array($card->getCardName(), self::BASIC_ENERGY_NAMES, true)) {
                $bestPrinting = $this->printingRepository->findLatestForIdentity($identity);
            } else {
                $bestPrinting = $this->printingRepository->findLowestRarityForIdentity($identity);
            }

            if (null !== $bestPrinting && null !== $bestPrinting->getImageUrl()) {
                $overrides[$cardId] = $bestPrinting->getImageUrl();
            }
        }

        return $overrides;
    }
}
