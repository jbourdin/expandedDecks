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

use App\DTO\BannedCardGroup;
use App\Entity\BannedCard;

/**
 * Groups BannedCard rows that share the same functional card (CardIdentity)
 * so the public page shows one tile per card with all banned printings inside
 * the modal.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final readonly class BannedCardGrouper
{
    public function __construct(
        private BannedCardPrintingLinker $imageResolver,
    ) {
    }

    /**
     * @param list<BannedCard> $cards
     *
     * @return list<BannedCardGroup>
     */
    public function group(array $cards, string $locale): array
    {
        /** @var array<string, list<BannedCard>> $byKey */
        $byKey = [];
        foreach ($cards as $card) {
            $key = $this->groupKey($card);
            $byKey[$key][] = $card;
        }

        $groups = [];
        foreach ($byKey as $cardsInGroup) {
            $groups[] = $this->buildGroup($cardsInGroup, $locale);
        }

        usort($groups, static function (BannedCardGroup $a, BannedCardGroup $b): int {
            $aDate = $a->effectiveDate?->getTimestamp() ?? 0;
            $bDate = $b->effectiveDate?->getTimestamp() ?? 0;
            if ($aDate !== $bDate) {
                return $bDate <=> $aDate;
            }

            return strcmp($a->cardName, $b->cardName);
        });

        return $groups;
    }

    private function groupKey(BannedCard $card): string
    {
        $printing = $card->getCardPrinting();
        if (null !== $printing) {
            return 'identity:'.$printing->getCardIdentity()->getId();
        }

        // No linked printing → treat this row as its own group. Grouping by name
        // alone is unsafe because cards like Unown share a name across distinct
        // functional cards (different abilities/attacks).
        return 'unlinked:'.$card->getSetCode().':'.$card->getCardNumber();
    }

    /**
     * @param list<BannedCard> $cards
     */
    private function buildGroup(array $cards, string $locale): BannedCardGroup
    {
        [$representative, $imageUrl] = $this->pickRepresentative($cards, $locale);

        $earliestDate = null;
        $sourceUrl = null;
        $explanation = null;
        foreach ($cards as $card) {
            $date = $card->getEffectiveDate();
            if (null !== $date && (null === $earliestDate || $date < $earliestDate)) {
                $earliestDate = $date;
            }
            if (null === $sourceUrl) {
                $candidate = $card->getSourceUrl();
                if (null !== $candidate && '' !== $candidate) {
                    $sourceUrl = $candidate;
                }
            }
            if (null === $explanation) {
                $candidate = $card->getExplanation();
                if (null !== $candidate && '' !== trim($candidate)) {
                    $explanation = $candidate;
                }
            }
        }

        $printings = $cards;
        usort($printings, static function (BannedCard $a, BannedCard $b): int {
            $cmp = strcmp($a->getSetCode(), $b->getSetCode());
            if (0 !== $cmp) {
                return $cmp;
            }

            return strcmp($a->getCardNumber(), $b->getCardNumber());
        });

        return new BannedCardGroup(
            cardName: $representative->getCardName(),
            representative: $representative,
            imageUrl: $imageUrl,
            printings: $printings,
            effectiveDate: $earliestDate,
            sourceUrl: $sourceUrl,
            explanation: $explanation,
        );
    }

    /**
     * Pick the lowest-rarity printing that has a resolvable image URL. If none
     * have an image, fall back to the lowest-rarity printing overall, then to
     * the first card. Returns a (card, imageUrl) pair to avoid resolving the
     * same image twice.
     *
     * @param list<BannedCard> $cards
     *
     * @return array{0: BannedCard, 1: ?string}
     */
    private function pickRepresentative(array $cards, string $locale): array
    {
        $bestWithImage = null;
        $bestWithImageTier = \PHP_INT_MAX;
        $bestWithImageUrl = null;

        $bestAny = null;
        $bestAnyTier = \PHP_INT_MAX;

        foreach ($cards as $card) {
            $printing = $card->getCardPrinting();
            $tier = null !== $printing ? $printing->getRarityTier() : \PHP_INT_MAX;

            if ($tier < $bestAnyTier) {
                $bestAnyTier = $tier;
                $bestAny = $card;
            }

            $imageUrl = $this->imageResolver->resolveImageUrl($card, $locale);
            if (null !== $imageUrl && $tier < $bestWithImageTier) {
                $bestWithImageTier = $tier;
                $bestWithImage = $card;
                $bestWithImageUrl = $imageUrl;
            }
        }

        if (null !== $bestWithImage) {
            return [$bestWithImage, $bestWithImageUrl];
        }

        $fallback = $bestAny ?? $cards[0];

        return [$fallback, $this->imageResolver->resolveImageUrl($fallback, $locale)];
    }
}
