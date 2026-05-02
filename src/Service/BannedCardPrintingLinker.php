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

use App\Entity\BannedCard;
use App\Entity\CardPrinting;
use App\Repository\CardPrintingRepository;
use App\Repository\TcgdexSetRepository;

/**
 * Resolves a BannedCard's CardPrinting link by its (setCode, cardNumber) pair,
 * and provides a chain of CDN fallbacks when no canonical imageUrl is stored.
 *
 * The fallback chain mirrors the patterns used by CardImageResolver and
 * CardEnricher::resolveImageUrl: TCGdex CDN (with dot-stripped set IDs),
 * then PokemonTCG.io. Image URLs are returned, not downloaded; the browser
 * picks them up at render time.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final readonly class BannedCardPrintingLinker
{
    private const string TCGDEX_CDN_BASE = 'https://assets.tcgdex.net';
    private const string POKEMONTCG_IO_BASE = 'https://images.pokemontcg.io';

    public function __construct(
        private CardPrintingRepository $cardPrintingRepository,
        private TcgdexSetRepository $tcgdexSetRepository,
    ) {
    }

    /**
     * Sets `cardPrinting` if currently null and a matching local printing exists.
     * No-op when a link is already set or no printing matches.
     *
     * For the richer enrichment path (TCGdex API + CardIdentity creation),
     * use BannedCardEnricher instead.
     */
    public function linkIfMissing(BannedCard $card): void
    {
        if (null !== $card->getCardPrinting()) {
            return;
        }

        $printing = $this->cardPrintingRepository->findFirstBySetCodeAndCardNumber(
            $card->getSetCode(),
            $card->getCardNumber(),
        );

        if (null !== $printing) {
            $card->setCardPrinting($printing);
        }
    }

    /**
     * Resolves a high-res image URL for the public page.
     *
     * Order:
     *   1. CardPrinting.imageUrl (canonical TCGdex URL set during enrichment).
     *   2. TCGdex CDN URL built from CardPrinting.tcgdexId.
     *   3. PokemonTCG.io CDN URL built from CardPrinting.tcgdexId.
     *   4. TCGdex CDN URL built from TcgdexSet (PTCG code → serie + set IDs)
     *      using the BannedCard's raw setCode.
     */
    public function resolveImageUrl(BannedCard $card, string $locale = 'en'): ?string
    {
        $printing = $card->getCardPrinting();

        if (null !== $printing) {
            $direct = $printing->getImageUrl();
            if (null !== $direct && '' !== $direct) {
                return $direct;
            }

            $tcgdexFallback = $this->buildTcgdexCdnFromPrinting($printing, $locale);
            if (null !== $tcgdexFallback) {
                return $tcgdexFallback;
            }

            $pokemonTcgIoFallback = $this->buildPokemontcgioFromPrinting($printing);
            if (null !== $pokemonTcgIoFallback) {
                return $pokemonTcgIoFallback;
            }
        }

        return $this->buildTcgdexCdnFromSetCode($card, $locale);
    }

    private function buildTcgdexCdnFromPrinting(CardPrinting $printing, string $locale): ?string
    {
        $tcgdexCard = $printing->getTcgdexCard();

        if (null === $tcgdexCard) {
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
                str_replace('.', '', $setId),
                $localId,
            );
        }

        $set = $tcgdexCard->getSet();

        return \sprintf(
            '%s/%s/%s/%s/%s/high.webp',
            self::TCGDEX_CDN_BASE,
            $locale,
            $set->getSerie()->getId(),
            str_replace('.', '', $set->getId()),
            $tcgdexCard->getLocalId(),
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

    private function buildTcgdexCdnFromSetCode(BannedCard $card, string $locale): ?string
    {
        $set = $this->tcgdexSetRepository->findByPtcgCode($card->getSetCode());
        if (null === $set) {
            return null;
        }

        return \sprintf(
            '%s/%s/%s/%s/%s/high.webp',
            self::TCGDEX_CDN_BASE,
            $locale,
            $set->getSerie()->getId(),
            str_replace('.', '', $set->getId()),
            $card->getCardNumber(),
        );
    }

    /**
     * @return array{0: string, 1: string}|null [setId, localId] when the id is splittable on the first dash
     */
    private function parseTcgdexId(string $tcgdexId): ?array
    {
        $dashPos = strpos($tcgdexId, '-');
        if (false === $dashPos) {
            return null;
        }

        return [substr($tcgdexId, 0, $dashPos), substr($tcgdexId, $dashPos + 1)];
    }

    /**
     * Guess the TCGdex serie slug from a set ID prefix. Used only when the
     * printing has no TcgdexCard link to read the serie from.
     */
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
