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
use App\Service\DeckListParser;
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
    /** Maps TCGdex category names to internal card types. */
    private const array CATEGORY_TO_CARD_TYPE = [
        'pokemon' => 'pokemon',
        'pokémon' => 'pokemon',
        'trainer' => 'trainer',
        'energy' => 'energy',
    ];

    /**
     * Energy set codes (SVE, SME…) don't exist in TCGdex.
     * Cards from these sets are basic energy — skip set+number lookup.
     */
    private const array ENERGY_SET_CODES = ['SVE', 'SME', 'XYE', 'BWE'];

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
                    $this->resolveCardType($card, 'Energy');
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
                        $this->resolveCardType($card, $tcgdexCard->category);

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
                $this->resolveCardType($card, $tcgdexCard->category);

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
        if (\in_array($card->getCardName(), DeckListParser::BASIC_ENERGY_NAMES, true)) {
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

        // For energy sets (SVE, SME…) or when set+number failed: pick simplest recent printing
        $tcgdexCard = $this->findSimplestBasicEnergyByName($card->getCardName());

        if (null !== $tcgdexCard) {
            $card->setTcgdexId($tcgdexCard->id);
            $card->setImageUrl($tcgdexCard->imageUrl);
            $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
            $card->setCardPrinting($printing);

            return;
        }

        // Final fallback: static image map
        $card->setImageUrl(self::BASIC_ENERGY_IMAGES[$card->getCardName()] ?? null);
    }

    /**
     * Updates the card type from TCGdex category when it was not determined by section headers.
     *
     * @see docs/features.md F6.1 — Parse PTCG text format
     */
    private function resolveCardType(DeckCard $card, string $tcgdexCategory): void
    {
        if (DeckListParser::UNKNOWN_CARD_TYPE !== $card->getCardType()) {
            return;
        }

        $mapped = self::CATEGORY_TO_CARD_TYPE[strtolower($tcgdexCategory)] ?? null;

        if (null !== $mapped) {
            $card->setCardType($mapped);
        }
    }

    /**
     * Find the simplest, most recent basic energy printing from TCGdex.
     *
     * Prefers Common rarity over special art variants, then picks the most recent
     * release date. This avoids stamped, foiled, or secret rare energy cards
     * (e.g. swsh12.5 Ultra Rare, swsh8 Secret Rare) and selects the plain version
     * from the latest core set (e.g. sm1 Common).
     *
     * @see docs/features.md F6.9 — Improved energy card enrichment
     */
    private function findSimplestBasicEnergyByName(string $cardName): ?TcgdexCard
    {
        $printings = $this->apiClient->findAllPrintingsByName($cardName);

        $best = null;

        foreach ($printings as $printing) {
            if ($printing->name !== $cardName || null === $printing->imageUrl) {
                continue;
            }

            if (null === $best) {
                $best = $printing;

                continue;
            }

            $bestIsCommon = null !== $best->rarity && 'Common' === $best->rarity;
            $currentIsCommon = null !== $printing->rarity && 'Common' === $printing->rarity;

            // Prefer Common rarity over non-Common
            if ($currentIsCommon && !$bestIsCommon) {
                $best = $printing;

                continue;
            }

            if (!$currentIsCommon && $bestIsCommon) {
                continue;
            }

            // Within same rarity class, prefer most recent release date
            if (($printing->setReleaseDate ?? '') > ($best->setReleaseDate ?? '')) {
                $best = $printing;
            }
        }

        return $best;
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
