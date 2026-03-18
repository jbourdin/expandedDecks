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
use App\Service\CardIdentity\CardIdentityResolver;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enriches DeckCards with data from TCGdex (trainerSubtype, imageUrl, tcgdexId).
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 * @see docs/features.md F6.9 — Improved energy card enrichment
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class CardEnricher
{
    /**
     * Energy set codes (SVE, SME…) don't exist in TCGdex.
     * Cards from these sets are basic energy — skip set+number lookup.
     */
    private const array ENERGY_SET_CODES = ['SVE', 'SME', 'XYE', 'BWE'];

    /** Basic energy card names for detection regardless of set code. */
    private const array BASIC_ENERGY_NAMES = [
        'Grass Energy',
        'Fire Energy',
        'Water Energy',
        'Lightning Energy',
        'Psychic Energy',
        'Fighting Energy',
        'Darkness Energy',
        'Metal Energy',
        'Fairy Energy',
    ];

    /**
     * Fallback image URLs for basic energy cards when TCGdex returns nothing.
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
        private readonly CardIdentityResolver $identityResolver,
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
                // Basic energy: detect by name (regardless of set code)
                if ($this->isBasicEnergy($card)) {
                    $this->enrichBasicEnergy($card);
                    ++$enrichedCount;

                    continue;
                }

                $tcgdexCard = $this->apiClient->findCard($card->getSetCode(), $card->getCardNumber());

                // Fallback: if set+number lookup failed, try by card name
                if (null === $tcgdexCard) {
                    $tcgdexCard = $this->findFirstPrintingByName($card->getCardName());

                    if (null !== $tcgdexCard) {
                        $card->setTcgdexId($tcgdexCard->id);
                        $card->setImageUrl($tcgdexCard->imageUrl);

                        if (null !== $tcgdexCard->trainerType) {
                            $card->setTrainerSubtype($tcgdexCard->trainerType);
                        }

                        $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
                        $card->setCardPrinting($printing);

                        $legalityWarnings[] = \sprintf(
                            '"%s" (%s %s): set code not recognized — matched by name only (image may not correspond to the exact card version).',
                            $card->getCardName(),
                            $card->getSetCode(),
                            $card->getCardNumber(),
                        );
                        ++$enrichedCount;

                        continue;
                    }

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

                // Link to CardIdentity/CardPrinting model
                $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
                $card->setCardPrinting($printing);

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

    private function isBasicEnergy(DeckCard $card): bool
    {
        // Detect by name (covers all sets including non-energy sets like SVI)
        if (\in_array($card->getCardName(), self::BASIC_ENERGY_NAMES, true)) {
            return true;
        }

        // Also detect by energy set code for safety
        return \in_array(strtoupper($card->getSetCode()), self::ENERGY_SET_CODES, true);
    }

    /**
     * @see docs/features.md F6.9 — Improved energy card enrichment
     */
    private function enrichBasicEnergy(DeckCard $card): void
    {
        $setCode = strtoupper($card->getSetCode());

        // For non-energy sets (e.g. SVI), try set+number lookup first
        if (!\in_array($setCode, self::ENERGY_SET_CODES, true)) {
            $tcgdexCard = $this->apiClient->findCard($card->getSetCode(), $card->getCardNumber());

            if (null !== $tcgdexCard) {
                $card->setTcgdexId($tcgdexCard->id);
                $card->setImageUrl($tcgdexCard->imageUrl);
                $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
                $card->setCardPrinting($printing);

                return;
            }
        }

        // Try name-based search to get a tcgdexId
        $imageUrl = $this->apiClient->findImageByName($card->getCardName());

        if (null !== $imageUrl) {
            $card->setImageUrl($imageUrl);

            return;
        }

        // Final fallback: static image map
        $card->setImageUrl(self::BASIC_ENERGY_IMAGES[$card->getCardName()] ?? null);
    }

    /**
     * Find the first exact-name-matching printing from TCGdex, returning a full TcgdexCard.
     *
     * Unlike findImageByName() which only returns a URL, this returns the complete
     * card data needed for CardIdentity resolution.
     */
    private function findFirstPrintingByName(string $cardName): ?TcgdexCard
    {
        $printings = $this->apiClient->findAllPrintingsByName($cardName);

        foreach ($printings as $printing) {
            if ($printing->name === $cardName && null !== $printing->imageUrl) {
                return $printing;
            }
        }

        return null;
    }
}
