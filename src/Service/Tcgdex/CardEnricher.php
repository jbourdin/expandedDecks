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

use App\Entity\CardPrinting;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\DeckListParser;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enriches DeckCards with card data, linking them to CardPrinting/CardIdentity.
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
     * Fallback image URLs for basic energy cards when no TCGdex printing exists.
     *
     * Uses MEE (Mega Evolution Energy) from pokemon.com CDN for the 8 standard types,
     * and sm1 from pokemontcg.io for Fairy Energy.
     *
     * @see data/basic_energies.json — full catalogue of all basic energy printings
     * @see docs/technicalities/basic_energy_images.md
     */
    private const array BASIC_ENERGY_IMAGES = [
        // English
        'Grass Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
        'Fire Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png',
        'Water Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png',
        'Lightning Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png',
        'Psychic Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png',
        'Fighting Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png',
        'Darkness Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png',
        'Metal Energy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png',
        'Fairy Energy' => 'https://images.pokemontcg.io/sm1/172_hires.png',
        // French
        'Énergie Plante' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
        'Énergie Feu' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png',
        'Énergie Eau' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png',
        'Énergie Électrique' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png',
        'Énergie Psy' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png',
        'Énergie Combat' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png',
        'Énergie Obscurité' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png',
        'Énergie Métal' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png',
        'Énergie Fée' => 'https://images.pokemontcg.io/sm1/172_hires.png',
        // German
        'Pflanzenenergie' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
        'Feuerenergie' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png',
        'Wasserenergie' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png',
        'Elektroenergie' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png',
        'Psychoenergie' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png',
        'Kampfenergie' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png',
        'Finsternis-Energie' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png',
        'Metallenergie' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png',
        'Feen-Energie' => 'https://images.pokemontcg.io/sm1/172_hires.png',
        // Spanish
        'Energía Planta' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
        'Energía Fuego' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png',
        'Energía Agua' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png',
        'Energía Rayo' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png',
        'Energía Psíquica' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png',
        'Energía Lucha' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png',
        'Energía Oscura' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png',
        'Energía Metálica' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png',
        'Energía Hada' => 'https://images.pokemontcg.io/sm1/172_hires.png',
        // Italian
        'Energia Erba' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
        'Energia Fuoco' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png',
        'Energia Acqua' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png',
        'Energia Lampo' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png',
        'Energia Psico' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png',
        'Energia Lotta' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png',
        'Energia Oscurità' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png',
        'Energia Metallo' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png',
        'Energia Folletto' => 'https://images.pokemontcg.io/sm1/172_hires.png',
        // Portuguese
        'Energia de Grama' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
        'Energia de Fogo' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png',
        'Energia de Água' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png',
        'Energia de Raios' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png',
        'Energia Psíquica' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png',
        'Energia de Luta' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png',
        'Energia Noturna' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png',
        'Energia de Metal' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png',
        'Energia de Fada' => 'https://images.pokemontcg.io/sm1/172_hires.png',
        // Japanese
        '基本草エネルギー' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png',
        '基本炎エネルギー' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png',
        '基本水エネルギー' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png',
        '基本雷エネルギー' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png',
        '基本超エネルギー' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png',
        '基本闘エネルギー' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png',
        '基本悪エネルギー' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png',
        '基本鋼エネルギー' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png',
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

                // Strategy 1: PTCGL set code + card number (international, exact match)
                $tcgdexCard = $this->apiClient->findCard($card->getSetCode(), $card->getCardNumber());

                // Strategy 2: Asian alias — set code maps to international set, search by name within it
                if (null === $tcgdexCard) {
                    $tcgdexCard = $this->apiClient->findCardByNameInAliasedSet($card->getSetCode(), $card->getCardName());

                    if (null !== $tcgdexCard) {
                        $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
                        $card->setCardPrinting($printing);
                        $this->resolveCardType($card, $tcgdexCard->category);
                        $this->resolveImageUrl($printing, $tcgdexCard, $card->getCardName());
                        $this->applyImageOverride($printing, $card->getSetCode(), $card->getCardNumber());

                        $legalityWarnings[] = \sprintf(
                            '"%s" (%s %s): resolved via Asian set alias — matched by name within %s.',
                            $card->getCardName(),
                            $card->getSetCode(),
                            $card->getCardNumber(),
                            $tcgdexCard->setCode ?? 'unknown set',
                        );
                        ++$enrichedCount;

                        continue;
                    }
                }

                // Both strategies failed — card cannot be resolved
                if (null === $tcgdexCard) {
                    ++$notFoundCount;
                    $notFoundCards[] = \sprintf('%s (%s %s)', $card->getCardName(), $card->getSetCode(), $card->getCardNumber());

                    continue;
                }

                $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
                $card->setCardPrinting($printing);
                $this->resolveCardType($card, $tcgdexCard->category);
                $this->resolveImageUrl($printing, $tcgdexCard, $card->getCardName());
                $this->applyImageOverride($printing, $card->getSetCode(), $card->getCardNumber());

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
            // Static energy image — no CardPrinting (energy-only sets aren't in TCGdex)
            // Try to find a printing anyway for enrichment completeness
            $tcgdexCard = $this->findSimplestBasicEnergyByName($card->getCardName());

            if (null !== $tcgdexCard) {
                $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
                $printing->setImageUrl(self::ENERGY_SET_IMAGES[$energySetKey]);
                $card->setCardPrinting($printing);
            }

            return;
        }

        // For non-energy sets (e.g. SVI), try set+number lookup in TCGdex
        if (!\in_array($setCode, self::ENERGY_SET_CODES, true)) {
            $tcgdexCard = $this->apiClient->findCard($card->getSetCode(), $card->getCardNumber());

            if (null !== $tcgdexCard) {
                $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
                $card->setCardPrinting($printing);

                return;
            }
        }

        // Fallback: pick simplest TCGdex printing by name
        $tcgdexCard = $this->findSimplestBasicEnergyByName($card->getCardName());

        if (null !== $tcgdexCard) {
            $printing = $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
            $card->setCardPrinting($printing);

            return;
        }

        // Final fallback: create a synthetic printing with a static image URL
        $fallbackImageUrl = self::BASIC_ENERGY_IMAGES[$card->getCardName()] ?? null;

        if (null !== $fallbackImageUrl) {
            $syntheticDto = new TcgdexCard(
                id: \sprintf('energy-%s', strtolower(str_replace(' ', '-', $card->getCardName()))),
                name: $card->getCardName(),
                category: 'Energy',
                trainerType: null,
                imageUrl: $fallbackImageUrl,
                isExpandedLegal: true,
            );
            $printing = $this->identityResolver->resolveFromTcgdexCard($syntheticDto);
            $printing->setImageUrl($fallbackImageUrl);
            $card->setCardPrinting($printing);
        }
    }

    /**
     * Ensures the CardPrinting has a working image URL, applying fallbacks if needed.
     *
     * If the existing URL returns a 404, or if no URL is set, tries PokemonTCG.io
     * CDN and name-based search as fallbacks.
     */
    private function resolveImageUrl(CardPrinting $printing, TcgdexCard $tcgdexCard, string $cardName): void
    {
        $currentUrl = $printing->getImageUrl();

        if (null !== $currentUrl && $this->isImageReachable($currentUrl)) {
            return;
        }

        $fallbackUrl = self::buildPokemontcgioUrl($tcgdexCard->id);

        if (null !== $fallbackUrl && $this->isImageReachable($fallbackUrl)) {
            $printing->setImageUrl($fallbackUrl);

            return;
        }

        $nameUrl = $this->apiClient->findImageByName($cardName);

        if (null !== $nameUrl) {
            $printing->setImageUrl($nameUrl);
        }
    }

    /**
     * Check if an image URL is reachable with a lightweight HEAD request.
     */
    private function isImageReachable(string $url): bool
    {
        $headers = @get_headers($url);

        if (!\is_array($headers) || [] === $headers) {
            return false;
        }

        $statusLine = $headers[0];

        return \is_string($statusLine) && str_contains($statusLine, '200');
    }

    /**
     * Applies a static image override for known TCGdex data issues.
     *
     * Call after setting the CardPrinting. If the card's set code + number
     * matches a known buggy entry, the printing's image is replaced.
     */
    private function applyImageOverride(CardPrinting $printing, string $setCode, string $cardNumber): void
    {
        $key = strtoupper($setCode).'|'.$cardNumber;
        $override = self::IMAGE_OVERRIDES[$key] ?? null;

        if (null !== $override) {
            $printing->setImageUrl($override);
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
}
