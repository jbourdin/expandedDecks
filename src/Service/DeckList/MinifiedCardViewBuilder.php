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

namespace App\Service\DeckList;

use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Repository\CardPrintingRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\DeckListParser;

/**
 * Builds grouped MinifiedCardView arrays for the deck detail table view.
 *
 * Same merging/sorting logic as MinifiedListGenerator, but returns structured
 * card view objects instead of PTCGL text lines.
 *
 * @see docs/features.md F6.8 — Minified deck list export
 */
class MinifiedCardViewBuilder
{
    private const array TYPE_ORDER = ['pokemon' => 0, 'trainer' => 1, 'energy' => 2];
    private const array TRAINER_SUBTYPE_ORDER = ['supporter' => 0, 'item' => 1, 'tool' => 2, 'stadium' => 3];

    public function __construct(
        private readonly CardPrintingRepository $printingRepository,
        private readonly CardIdentityResolver $identityResolver,
    ) {
    }

    /**
     * Build grouped minified card views for a DeckVersion.
     *
     * @return array<string, list<MinifiedCardView>> keyed by card type (pokemon, trainer, energy)
     */
    public function buildGrouped(DeckVersion $version): array
    {
        $cards = $this->buildMergedCards($version);

        // Sort like the mosaic
        usort($cards, static function (MinifiedCardView $cardA, MinifiedCardView $cardB): int {
            $typeA = self::TYPE_ORDER[$cardA->getCardType()] ?? 3;
            $typeB = self::TYPE_ORDER[$cardB->getCardType()] ?? 3;

            if ($typeA !== $typeB) {
                return $typeA <=> $typeB;
            }

            if ('trainer' === $cardA->getCardType()) {
                $subtypeA = self::TRAINER_SUBTYPE_ORDER[strtolower((string) $cardA->getTrainerSubtype())] ?? 4;
                $subtypeB = self::TRAINER_SUBTYPE_ORDER[strtolower((string) $cardB->getTrainerSubtype())] ?? 4;

                if ($subtypeA !== $subtypeB) {
                    return $subtypeA <=> $subtypeB;
                }
            }

            if ($cardA->getQuantity() !== $cardB->getQuantity()) {
                return $cardB->getQuantity() <=> $cardA->getQuantity();
            }

            return $cardA->getCardName() <=> $cardB->getCardName();
        });

        // Group by type
        $grouped = [];

        foreach ($cards as $card) {
            $grouped[$card->getCardType()][] = $card;
        }

        // Ensure consistent section order
        $ordered = [];

        foreach (['pokemon', 'trainer', 'energy'] as $section) {
            if (isset($grouped[$section])) {
                $ordered[$section] = $grouped[$section];
            }
        }

        return $ordered;
    }

    /**
     * @return list<MinifiedCardView>
     */
    private function buildMergedCards(DeckVersion $version): array
    {
        /** @var array<string, array{name: string, quantity: int, setCode: string, cardNumber: string, cardType: string, trainerSubtype: ?string, imageUrl: ?string}> $merged */
        $merged = [];

        foreach ($version->getCards() as $card) {
            $resolved = $this->resolveMinifiedCard($card);
            $key = \sprintf('%s|%s|%s', $resolved['name'], $resolved['setCode'], $resolved['cardNumber']);

            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $resolved['quantity'];
            } else {
                $merged[$key] = $resolved;
            }
        }

        $views = [];

        foreach ($merged as $entry) {
            $views[] = new MinifiedCardView(
                $entry['name'],
                $entry['quantity'],
                $entry['setCode'],
                $entry['cardNumber'],
                $entry['cardType'],
                $entry['trainerSubtype'],
                $entry['imageUrl'],
            );
        }

        return $views;
    }

    /**
     * @return array{name: string, quantity: int, setCode: string, cardNumber: string, cardType: string, trainerSubtype: ?string, imageUrl: ?string}
     */
    private function resolveMinifiedCard(DeckCard $card): array
    {
        // Static overrides for known TCGdex data issues
        $overrideKey = strtoupper($card->getSetCode()).'|'.$card->getCardNumber();
        $override = DeckListParser::MINIFIED_PRINTING_OVERRIDES[$overrideKey] ?? null;

        if (null !== $override) {
            return [
                'name' => $card->getCardName(),
                'quantity' => $card->getQuantity(),
                'setCode' => $override['setCode'],
                'cardNumber' => $override['cardNumber'],
                'cardType' => $card->getCardType(),
                'trainerSubtype' => $card->getTrainerSubtype(),
                'imageUrl' => $override['imageUrl'],
            ];
        }

        // Basic energies always use the default printing (MEE for standard types, SUM for Fairy)
        $energyDefault = DeckListParser::DEFAULT_BASIC_ENERGY_PRINTINGS[$card->getCardName()] ?? null;

        if (null !== $energyDefault) {
            return [
                'name' => $card->getCardName(),
                'quantity' => $card->getQuantity(),
                'setCode' => $energyDefault['setCode'],
                'cardNumber' => $energyDefault['cardNumber'],
                'cardType' => $card->getCardType(),
                'trainerSubtype' => $card->getTrainerSubtype(),
                'imageUrl' => $energyDefault['imageUrl'],
            ];
        }

        $default = [
            'name' => $card->getCardName(),
            'quantity' => $card->getQuantity(),
            'setCode' => $card->getSetCode(),
            'cardNumber' => $card->getCardNumber(),
            'cardType' => $card->getCardType(),
            'trainerSubtype' => $card->getTrainerSubtype(),
            'imageUrl' => $card->getImageUrl(),
        ];

        $printing = $card->getCardPrinting();

        if (null === $printing) {
            return $default;
        }

        $identity = $printing->getCardIdentity();

        if ($identity->getPrintings()->count() <= 1) {
            $this->identityResolver->expandPrintings($identity);
        }

        $bestPrinting = $this->printingRepository->findLowestRarityForIdentity($identity);

        if (null === $bestPrinting) {
            return $default;
        }

        $setCode = $bestPrinting->getSetCode();

        if ('' === $setCode) {
            $setCode = $card->getSetCode();
        }

        return [
            'name' => $card->getCardName(),
            'quantity' => $card->getQuantity(),
            'setCode' => $setCode,
            'cardNumber' => $bestPrinting->getCardNumber(),
            'cardType' => $card->getCardType(),
            'trainerSubtype' => $card->getTrainerSubtype(),
            'imageUrl' => $bestPrinting->getImageUrl(),
        ];
    }
}
