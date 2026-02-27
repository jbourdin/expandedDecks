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

/**
 * Validates a parsed deck list against Expanded format rules.
 *
 * @see docs/features.md F6.3 — Validate deck list (card count, duplicates)
 */
class DeckListValidator
{
    private const int REQUIRED_CARD_COUNT = 60;
    private const int MAX_COPIES = 4;

    /** Official basic energy card names (unlimited copies allowed). */
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

    public function validate(DeckListParseResult $parseResult): DeckValidationResult
    {
        $errors = [];
        $warnings = [];

        $totalCards = $parseResult->totalCards();
        if (self::REQUIRED_CARD_COUNT !== $totalCards) {
            $errors[] = \sprintf(
                'A deck must contain exactly %d cards, but this list has %d.',
                self::REQUIRED_CARD_COUNT,
                $totalCards,
            );
        }

        // Group quantities by card identity (setCode|cardNumber)
        /** @var array<string, array{quantity: int, name: string}> $cardCounts */
        $cardCounts = [];

        foreach ($parseResult->cards as $card) {
            if ($this->isBasicEnergy($card)) {
                continue;
            }

            $key = $card->setCode.'|'.$card->cardNumber;

            if (!isset($cardCounts[$key])) {
                $cardCounts[$key] = ['quantity' => 0, 'name' => $card->cardName];
            }

            $cardCounts[$key]['quantity'] += $card->quantity;
        }

        foreach ($cardCounts as $data) {
            if ($data['quantity'] > self::MAX_COPIES) {
                $errors[] = \sprintf(
                    'Card "%s" appears %d times, but the maximum is %d copies.',
                    $data['name'],
                    $data['quantity'],
                    self::MAX_COPIES,
                );
            }
        }

        return new DeckValidationResult($errors, $warnings);
    }

    private function isBasicEnergy(ParsedCard $card): bool
    {
        return 'energy' === $card->cardType && \in_array($card->cardName, self::BASIC_ENERGY_NAMES, true);
    }
}
