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

namespace App\Service\Tcgdex;

use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enriches DeckCards with data from TCGdex (trainerSubtype, imageUrl, tcgdexId).
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class CardEnricher
{
    /**
     * Energy set codes (SVE, SME…) don't exist in TCGdex.
     * Cards from these sets are basic energy — we assign a static image.
     */
    private const array ENERGY_SET_CODES = ['SVE', 'SME', 'XYE', 'BWE'];

    /**
     * Fallback image URLs for basic energy cards whose sets don't exist in TCGdex.
     * Each points to a recent TCGdex card image.
     */
    private const array BASIC_ENERGY_IMAGES = [
        'Grass Energy' => 'https://assets.tcgdex.net/en/bw/bw1/105/high.webp',
        'Fire Energy' => 'https://assets.tcgdex.net/en/bw/bw1/106/high.webp',
        'Water Energy' => 'https://assets.tcgdex.net/en/bw/bw1/107/high.webp',
        'Lightning Energy' => 'https://assets.tcgdex.net/en/bw/bw1/108/high.webp',
        'Psychic Energy' => 'https://assets.tcgdex.net/en/bw/bw1/109/high.webp',
        'Fighting Energy' => 'https://assets.tcgdex.net/en/bw/bw1/110/high.webp',
        'Darkness Energy' => 'https://assets.tcgdex.net/en/bw/bw1/111/high.webp',
        'Metal Energy' => 'https://assets.tcgdex.net/en/bw/bw1/112/high.webp',
        'Fairy Energy' => 'https://assets.tcgdex.net/en/sm/sm1/172/high.webp',
    ];

    public function __construct(
        private readonly TcgdexApiClient $apiClient,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function enrichVersion(DeckVersion $version): CardEnrichmentReport
    {
        $version->setEnrichmentStatus('enriching');
        $this->em->flush();

        $enrichedCount = 0;
        $notFoundCount = 0;
        $notFoundCards = [];
        $legalityWarnings = [];

        try {
            foreach ($version->getCards() as $card) {
                $setCode = strtoupper($card->getSetCode());

                // Basic energy sets don't exist in TCGdex — use static images
                if (\in_array($setCode, self::ENERGY_SET_CODES, true)) {
                    $this->enrichBasicEnergy($card);
                    ++$enrichedCount;

                    continue;
                }

                $tcgdexCard = $this->apiClient->findCard($card->getSetCode(), $card->getCardNumber());

                if (null === $tcgdexCard) {
                    ++$notFoundCount;
                    $notFoundCards[] = \sprintf('%s (%s %s)', $card->getCardName(), $card->getSetCode(), $card->getCardNumber());

                    continue;
                }

                $card->setTcgdexId($tcgdexCard->id);

                if (null === $tcgdexCard->imageUrl) {
                    $card->setImageUrl($this->apiClient->findImageByName($card->getCardName()));
                } else {
                    $card->setImageUrl($tcgdexCard->imageUrl);
                }

                if (null !== $tcgdexCard->trainerType) {
                    $card->setTrainerSubtype($tcgdexCard->trainerType);
                }

                if (!$tcgdexCard->isExpandedLegal) {
                    $legalityWarnings[] = \sprintf(
                        '"%s" (%s %s) is not marked as Expanded-legal in TCGdex.',
                        $card->getCardName(),
                        $card->getSetCode(),
                        $card->getCardNumber(),
                    );
                }

                ++$enrichedCount;
            }

            $version->setEnrichmentStatus('done');
            $this->em->flush();
        } catch (\Throwable $e) {
            $version->setEnrichmentStatus('failed');
            $this->em->flush();

            throw $e;
        }

        return new CardEnrichmentReport($enrichedCount, $notFoundCount, $notFoundCards, $legalityWarnings);
    }

    private function enrichBasicEnergy(DeckCard $card): void
    {
        $imageUrl = self::BASIC_ENERGY_IMAGES[$card->getCardName()] ?? null;
        $card->setImageUrl($imageUrl);
    }
}
