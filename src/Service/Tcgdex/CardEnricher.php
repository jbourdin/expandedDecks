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
    /**
     * Static overrides for known TCGdex data issues.
     *
     * When TCGdex returns incorrect images or data for specific cards,
     * this map forces the correct image URL keyed by "{PTCGL_SET_CODE}|{cardNumber}".
     * Add entries here when a TCGdex image/data bug is discovered.
     */
    private const array IMAGE_OVERRIDES = [
        // g1-73 (GEN 73): TCGdex image shows the full-art 73a instead of the regular Uncommon
        'GEN|73' => 'https://assets.tcgdex.net/en/xy/xy1/129/high.webp',
    ];

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
    private const array ENERGY_SET_CODES = ['SVE', 'SME', 'XYE', 'BWE', 'MEE'];

    /**
     * Known energy-set card images, keyed by "{PTCGL_SET_CODE}|{cardNumber}".
     * Sourced from data/basic_energies.json — enables exact image matching
     * for energy-set codes that TCGdex does not index.
     *
     * @see docs/technicalities/basic_energy_images.md
     */
    private const array ENERGY_SET_IMAGES = [
        'SVE|1' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_1.png',
        'SVE|2' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_2.png',
        'SVE|3' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_3.png',
        'SVE|4' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_4.png',
        'SVE|5' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_5.png',
        'SVE|6' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_6.png',
        'SVE|7' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_7.png',
        'SVE|8' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_8.png',
        'SVE|9' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_9.png',
        'SVE|10' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_10.png',
        'SVE|11' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_11.png',
        'SVE|12' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_12.png',
        'SVE|13' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_13.png',
        'SVE|14' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_14.png',
        'SVE|15' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_15.png',
        'SVE|16' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_16.png',
        'MEE|1' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
        'MEE|2' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png',
        'MEE|3' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png',
        'MEE|4' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png',
        'MEE|5' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png',
        'MEE|6' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png',
        'MEE|7' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png',
        'MEE|8' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png',
    ];

    /**
     * Fallback image URLs for basic energy cards when TCGdex returns nothing.
     * Uses MEE (Mega Evolution Energy) from pokemon.com CDN for the 8 standard types,
     * and sm1 from pokemontcg.io for Fairy Energy.
     *
     * @see data/basic_energies.json — full catalogue of all basic energy printings
     * @see docs/technicalities/basic_energy_images.md
     */
    private const array BASIC_ENERGY_IMAGES = [
        'Grass Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
        'Fire Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png',
        'Water Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png',
        'Lightning Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png',
        'Psychic Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png',
        'Fighting Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png',
        'Darkness Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png',
        'Metal Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png',
        'Fairy Energy' => 'https://images.pokemontcg.io/sm1/172_hires.png',
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
                        $this->applyImageOverride($card);

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
                    // TCGdex has no image for this card — try PokemonTCG.io CDN as exact fallback
                    $card->setImageUrl(
                        self::buildPokemontcgioUrl($tcgdexCard->id)
                        ?? $this->apiClient->findImageByName($card->getCardName()),
                    );
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
                $this->applyImageOverride($card);

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

        // For energy-only sets (SVE, MEE…): use our static image map for exact match
        // Normalize card number by stripping leading zeros (SVE 04, SVE 004 → SVE 4)
        $normalizedNumber = ltrim($card->getCardNumber(), '0') ?: '0';
        $energySetKey = $setCode.'|'.$normalizedNumber;

        if (isset(self::ENERGY_SET_IMAGES[$energySetKey])) {
            $card->setImageUrl(self::ENERGY_SET_IMAGES[$energySetKey]);

            return;
        }

        // For non-energy sets (e.g. SVI), try set+number lookup in TCGdex
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

        // Fallback: pick simplest TCGdex printing by name
        $tcgdexCard = $this->findSimplestBasicEnergyByName($card->getCardName());

        if (null !== $tcgdexCard) {
            $card->setTcgdexId($tcgdexCard->id);
            $card->setImageUrl($tcgdexCard->imageUrl);
            $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
            $card->setCardPrinting($printing);

            return;
        }

        // Final fallback: static image map by energy name
        $card->setImageUrl(self::BASIC_ENERGY_IMAGES[$card->getCardName()] ?? null);
    }

    /**
     * Applies a static image override for known TCGdex data issues.
     *
     * Call after setting the card image URL from TCGdex. If the card's
     * set code + number matches a known buggy entry, the image is replaced.
     */
    private function applyImageOverride(DeckCard $card): void
    {
        $key = strtoupper($card->getSetCode()).'|'.$card->getCardNumber();
        $override = self::IMAGE_OVERRIDES[$key] ?? null;

        if (null !== $override) {
            $card->setImageUrl($override);
        }
    }

    /**
     * Builds a PokemonTCG.io image URL from a TCGdex card ID.
     *
     * TCGdex ID format: "{setId}-{localId}" (e.g. "sm3.5-68", "sm6-82").
     * PokemonTCG.io URL: "https://images.pokemontcg.io/{setId}/{localId}_hires.png"
     * with dots removed from set ID (sm3.5 → sm35, sv03.5 → sv03pt5 is handled
     * differently by pokemontcg.io, so only dot-to-nothing conversion is applied).
     *
     * Returns null if the ID format is unrecognized.
     */
    private static function buildPokemontcgioUrl(string $tcgdexId): ?string
    {
        if (!str_contains($tcgdexId, '-')) {
            return null;
        }

        $dashPos = (int) strpos($tcgdexId, '-');
        $setId = substr($tcgdexId, 0, $dashPos);
        $localId = substr($tcgdexId, $dashPos + 1);

        // PokemonTCG.io uses dots removed from set IDs (sm3.5 → sm35)
        $pokemontcgSetId = str_replace('.', '', $setId);

        return \sprintf('https://images.pokemontcg.io/%s/%s_hires.png', $pokemontcgSetId, $localId);
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
