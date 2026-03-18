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
use App\Service\Mosaic\MosaicTile;
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

    private const array TYPE_ORDER = ['pokemon' => 0, 'trainer' => 1, 'energy' => 2];
    private const array TRAINER_SUBTYPE_ORDER = ['supporter' => 0, 'item' => 1, 'tool' => 2, 'stadium' => 3];

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
            $tiles = $this->buildMergedTiles($version->getCards()->toArray());

            $this->mosaicGenerator->generateFromTiles($version, $tiles, 'minified');
            $publicUrl = $this->mosaicUrlResolver->resolveForVersion($version, 'minified');

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
     * Build merged MosaicTile list: resolve lowest-rarity image for each card,
     * then merge tiles that resolve to the same image+name into one tile with summed quantity.
     *
     * @param array<int, DeckCard> $cards
     *
     * @return list<MosaicTile>
     */
    private function buildMergedTiles(array $cards): array
    {
        /** @var array<string, array{name: string, quantity: int, imageUrl: ?string, cardType: string, trainerSubtype: ?string}> $merged */
        $merged = [];

        foreach ($cards as $card) {
            $imageUrl = $this->resolveMinifiedImageUrl($card);
            $key = \sprintf('%s|%s', $card->getCardName(), $imageUrl ?? '');

            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $card->getQuantity();
            } else {
                $merged[$key] = [
                    'name' => $card->getCardName(),
                    'quantity' => $card->getQuantity(),
                    'imageUrl' => $imageUrl,
                    'cardType' => $card->getCardType(),
                    'trainerSubtype' => $card->getTrainerSubtype(),
                ];
            }
        }

        $tiles = [];

        foreach ($merged as $entry) {
            $tiles[] = new MosaicTile(
                $entry['name'],
                $entry['quantity'],
                $entry['imageUrl'],
                $entry['cardType'],
                $entry['trainerSubtype'],
            );
        }

        // Sort: Pokemon → Trainer (supporter, item, tool, stadium) → Energy
        usort($tiles, static function (MosaicTile $tileA, MosaicTile $tileB): int {
            $typeA = self::TYPE_ORDER[$tileA->cardType] ?? 3;
            $typeB = self::TYPE_ORDER[$tileB->cardType] ?? 3;

            if ($typeA !== $typeB) {
                return $typeA <=> $typeB;
            }

            if ('trainer' === $tileA->cardType) {
                $subtypeA = self::TRAINER_SUBTYPE_ORDER[strtolower((string) $tileA->trainerSubtype)] ?? 4;
                $subtypeB = self::TRAINER_SUBTYPE_ORDER[strtolower((string) $tileB->trainerSubtype)] ?? 4;

                if ($subtypeA !== $subtypeB) {
                    return $subtypeA <=> $subtypeB;
                }
            }

            if ($tileA->quantity !== $tileB->quantity) {
                return $tileB->quantity <=> $tileA->quantity;
            }

            return $tileA->cardName <=> $tileB->cardName;
        });

        return $tiles;
    }

    private function resolveMinifiedImageUrl(DeckCard $card): ?string
    {
        $printing = $card->getCardPrinting();

        if (null === $printing) {
            return $card->getImageUrl();
        }

        $identity = $printing->getCardIdentity();

        if ($identity->getPrintings()->count() <= 1) {
            $this->identityResolver->expandPrintings($identity);
        }

        if (\in_array($card->getCardName(), self::BASIC_ENERGY_NAMES, true)) {
            $bestPrinting = $this->printingRepository->findLatestForIdentity($identity);
        } else {
            $bestPrinting = $this->printingRepository->findLowestRarityForIdentity($identity);
        }

        return $bestPrinting?->getImageUrl() ?? $card->getImageUrl();
    }
}
