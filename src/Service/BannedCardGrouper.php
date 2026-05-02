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

        return 'name:'.mb_strtolower($card->getCardName());
    }

    /**
     * @param list<BannedCard> $cards
     */
    private function buildGroup(array $cards, string $locale): BannedCardGroup
    {
        $representative = $this->pickRepresentative($cards);

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
            imageUrl: $this->imageResolver->resolveImageUrl($representative, $locale),
            printings: $printings,
            effectiveDate: $earliestDate,
            sourceUrl: $sourceUrl,
            explanation: $explanation,
        );
    }

    /**
     * Pick the card whose linked printing has the lowest rarity tier.
     * Falls back to the first card when no printings are linked.
     *
     * @param list<BannedCard> $cards
     */
    private function pickRepresentative(array $cards): BannedCard
    {
        $best = null;
        $bestTier = \PHP_INT_MAX;
        foreach ($cards as $card) {
            $printing = $card->getCardPrinting();
            if (null === $printing) {
                continue;
            }
            $tier = $printing->getRarityTier();
            if ($tier < $bestTier) {
                $bestTier = $tier;
                $best = $card;
            }
        }

        return $best ?? $cards[0];
    }
}
