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

use App\Entity\CardPrinting;
use App\Entity\StapleCard;
use App\Entity\StapleCardPrinting;
use App\Repository\TcgdexSetRepository;

/**
 * Returns a public-facing image URL for a {@see StapleCard}, walking through
 * its printings to find the lowest-rarity printing that resolves to a working URL.
 * The fallback chain mirrors {@see BannedCardImageResolver}.
 *
 * @see docs/features.md F6.15 — Staple cards
 */
final readonly class StapleCardImageResolver
{
    private const string TCGDEX_CDN_BASE = 'https://assets.tcgdex.net';
    private const string POKEMONTCG_IO_BASE = 'https://images.pokemontcg.io';

    public function __construct(
        private TcgdexSetRepository $tcgdexSetRepository,
    ) {
    }

    public function resolveForStaple(StapleCard $card, string $locale = 'en'): ?string
    {
        $representative = $card->getRepresentativePrinting();
        if (null !== $representative) {
            $url = $this->resolveForCardPrinting($representative, $card, $locale);
            if (null !== $url) {
                return $url;
            }
        }

        $printings = $card->getPrintings()->toArray();

        usort($printings, static function (StapleCardPrinting $a, StapleCardPrinting $b): int {
            $tierA = null !== $a->getCardPrinting() ? $a->getCardPrinting()->getRarityTier() : \PHP_INT_MAX;
            $tierB = null !== $b->getCardPrinting() ? $b->getCardPrinting()->getRarityTier() : \PHP_INT_MAX;

            return $tierA <=> $tierB;
        });

        foreach ($printings as $printing) {
            $cardPrinting = $printing->getCardPrinting();
            if (null !== $cardPrinting) {
                $url = $this->resolveForCardPrinting($cardPrinting, $card, $locale);
                if (null !== $url) {
                    return $url;
                }
            } else {
                $fallback = $this->buildTcgdexCdnFromSetCode($printing->getSetCode(), $printing->getCardNumber(), $locale);
                if (null !== $fallback) {
                    return $fallback;
                }
            }
        }

        return null;
    }

    private function resolveForCardPrinting(CardPrinting $printing, StapleCard $card, string $locale): ?string
    {
        // TCGdex says "no image for this card" by leaving imageBaseUrl null. The CardPrinting
        // may still have a synthesized imageUrl that 404s on the CDN (e.g. trainer-kit cards
        // are catalogued without images). Skip those so the resolver falls through to the
        // next sibling printing rather than returning a broken URL.
        $tcgdexCard = $printing->getTcgdexCard();
        if (null !== $tcgdexCard && null === $tcgdexCard->getImageBaseUrl()) {
            return null;
        }

        $direct = $printing->getImageUrl();
        if (null !== $direct && '' !== $direct) {
            return $this->normalizeTcgdexCdnUrl($direct);
        }

        $cdn = $this->buildTcgdexCdnFromPrinting($printing, $locale);
        if (null !== $cdn) {
            return $cdn;
        }

        $pokemonTcgIo = $this->buildPokemontcgioFromPrinting($printing);
        if (null !== $pokemonTcgIo) {
            return $pokemonTcgIo;
        }

        foreach ($card->getPrintings() as $staplePrinting) {
            if ($staplePrinting->getCardPrinting() === $printing) {
                return $this->buildTcgdexCdnFromSetCode(
                    $staplePrinting->getSetCode(),
                    $staplePrinting->getCardNumber(),
                    $locale,
                );
            }
        }

        return null;
    }

    private function buildTcgdexCdnFromPrinting(CardPrinting $printing, string $locale): ?string
    {
        $tcgdexCard = $printing->getTcgdexCard();

        if (null !== $tcgdexCard) {
            $set = $tcgdexCard->getSet();

            return \sprintf(
                '%s/%s/%s/%s/%s/high.webp',
                self::TCGDEX_CDN_BASE,
                $locale,
                $set->getSerie()->getId(),
                self::setIdForCdn($set->getId()),
                $tcgdexCard->getLocalId(),
            );
        }

        $parsed = $this->parseTcgdexId($printing->getTcgdexId());
        if (null === $parsed) {
            return null;
        }
        [$setId, $localId] = $parsed;
        $serieId = $this->guessSerieIdFromSetId($setId);
        if (null === $serieId) {
            return null;
        }

        return \sprintf(
            '%s/%s/%s/%s/%s/high.webp',
            self::TCGDEX_CDN_BASE,
            $locale,
            $serieId,
            self::setIdForCdn($setId),
            $localId,
        );
    }

    private function buildPokemontcgioFromPrinting(CardPrinting $printing): ?string
    {
        $parsed = $this->parseTcgdexId($printing->getTcgdexId());
        if (null === $parsed) {
            return null;
        }
        [$setId, $localId] = $parsed;

        return \sprintf(
            '%s/%s/%s_hires.png',
            self::POKEMONTCG_IO_BASE,
            str_replace('.', '', $setId),
            $localId,
        );
    }

    private function buildTcgdexCdnFromSetCode(string $setCode, string $cardNumber, string $locale): ?string
    {
        $set = $this->tcgdexSetRepository->findByPtcgCode($setCode);
        if (null === $set) {
            return null;
        }

        return \sprintf(
            '%s/%s/%s/%s/%s/high.webp',
            self::TCGDEX_CDN_BASE,
            $locale,
            $set->getSerie()->getId(),
            self::setIdForCdn($set->getId()),
            $cardNumber,
        );
    }

    private function normalizeTcgdexCdnUrl(string $url): string
    {
        if (!str_starts_with($url, self::TCGDEX_CDN_BASE.'/')) {
            return $url;
        }

        $normalized = preg_replace_callback(
            '@^(https://assets\.tcgdex\.net/[^/]+/[^/]+/)([^/]+)(/)@',
            static fn (array $matches): string => $matches[1].self::setIdForCdn($matches[2]).$matches[3],
            $url,
        );

        return $normalized ?? $url;
    }

    /**
     * TCGdex's CDN convention: sm-era strips dots from set IDs ("sm7.5" → "sm75",
     * "sm3.5" → "sm35"). Every other era — xy / bw / swsh / sv / me — keeps dots
     * verbatim ("swsh4.5", "sv08.5", "me02.5"). Verified empirically.
     */
    private static function setIdForCdn(string $setId): string
    {
        return str_starts_with($setId, 'sm') ? str_replace('.', '', $setId) : $setId;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parseTcgdexId(string $tcgdexId): ?array
    {
        $dashPos = strpos($tcgdexId, '-');
        if (false === $dashPos) {
            return null;
        }

        return [substr($tcgdexId, 0, $dashPos), substr($tcgdexId, $dashPos + 1)];
    }

    private function guessSerieIdFromSetId(string $setId): ?string
    {
        return match (true) {
            str_starts_with($setId, 'sv') => 'sv',
            str_starts_with($setId, 'swsh') => 'swsh',
            str_starts_with($setId, 'sm') => 'sm',
            str_starts_with($setId, 'xy') => 'xy',
            str_starts_with($setId, 'bw') => 'bw',
            default => null,
        };
    }
}
