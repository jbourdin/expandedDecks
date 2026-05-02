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
use App\Repository\CardPrintingRepository;
use App\Repository\TcgdexSetRepository;

/**
 * Resolves a BannedCard's CardPrinting link by its (setCode, cardNumber) pair,
 * and provides a TCGdex CDN URL fallback when no local printing is available.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final readonly class BannedCardPrintingLinker
{
    private const string TCGDEX_CDN_BASE = 'https://assets.tcgdex.net';

    public function __construct(
        private CardPrintingRepository $cardPrintingRepository,
        private TcgdexSetRepository $tcgdexSetRepository,
    ) {
    }

    /**
     * Sets `cardPrinting` if currently null and a matching printing exists.
     * No-op when a link is already set or no printing matches.
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
     * Falls back to the TCGdex CDN URL pattern (`<base>/<lang>/<serie>/<set>/<number>/high.webp`)
     * when no local CardPrinting is linked but the (setCode, cardNumber) pair maps to a known TcgdexSet.
     */
    public function resolveImageUrl(BannedCard $card, string $locale = 'en'): ?string
    {
        $printing = $card->getCardPrinting();
        if (null !== $printing && null !== $printing->getImageUrl()) {
            return $printing->getImageUrl();
        }

        $set = $this->tcgdexSetRepository->findByPtcgCode($card->getSetCode());
        if (null === $set) {
            return null;
        }

        return \sprintf(
            '%s/%s/%s/%s/%s/high.webp',
            self::TCGDEX_CDN_BASE,
            $locale,
            $set->getSerie()->getId(),
            $set->getId(),
            $card->getCardNumber(),
        );
    }
}
