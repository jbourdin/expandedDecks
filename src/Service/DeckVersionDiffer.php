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

use App\Entity\DeckCard;
use App\Entity\DeckVersion;

/**
 * @see docs/features.md F2.9 — Deck version history
 */
final class DeckVersionDiffer
{
    /**
     * Compare two deck versions and return categorized card changes.
     *
     * @return array{
     *     added: list<array{cardName: string, setCode: string, cardNumber: string, quantity: int, cardType: string, imageUrl: string|null}>,
     *     removed: list<array{cardName: string, setCode: string, cardNumber: string, quantity: int, cardType: string, imageUrl: string|null}>,
     *     changed: list<array{cardName: string, setCode: string, cardNumber: string, oldQuantity: int, newQuantity: int, cardType: string, imageUrl: string|null}>,
     *     unchanged: list<array{cardName: string, setCode: string, cardNumber: string, quantity: int, cardType: string, imageUrl: string|null}>
     * }
     */
    public function diff(DeckVersion $oldVersion, DeckVersion $newVersion): array
    {
        $oldCards = $this->indexCards($oldVersion);
        $newCards = $this->indexCards($newVersion);

        $added = [];
        $removed = [];
        $changed = [];
        $unchanged = [];

        foreach ($newCards as $key => $card) {
            if (!isset($oldCards[$key])) {
                $added[] = [
                    'cardName' => $card->getCardName(),
                    'setCode' => $card->getSetCode(),
                    'cardNumber' => $card->getCardNumber(),
                    'quantity' => $card->getQuantity(),
                    'cardType' => $card->getCardType(),
                    'imageUrl' => $card->getImageUrl(),
                ];
            } elseif ($oldCards[$key]->getQuantity() !== $card->getQuantity()) {
                $changed[] = [
                    'cardName' => $card->getCardName(),
                    'setCode' => $card->getSetCode(),
                    'cardNumber' => $card->getCardNumber(),
                    'oldQuantity' => $oldCards[$key]->getQuantity(),
                    'newQuantity' => $card->getQuantity(),
                    'cardType' => $card->getCardType(),
                    'imageUrl' => $card->getImageUrl(),
                ];
            } else {
                $unchanged[] = [
                    'cardName' => $card->getCardName(),
                    'setCode' => $card->getSetCode(),
                    'cardNumber' => $card->getCardNumber(),
                    'quantity' => $card->getQuantity(),
                    'cardType' => $card->getCardType(),
                    'imageUrl' => $card->getImageUrl(),
                ];
            }
        }

        foreach ($oldCards as $key => $card) {
            if (!isset($newCards[$key])) {
                $removed[] = [
                    'cardName' => $card->getCardName(),
                    'setCode' => $card->getSetCode(),
                    'cardNumber' => $card->getCardNumber(),
                    'quantity' => $card->getQuantity(),
                    'cardType' => $card->getCardType(),
                    'imageUrl' => $card->getImageUrl(),
                ];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'unchanged' => $unchanged,
        ];
    }

    /**
     * @return array<string, DeckCard>
     */
    private function indexCards(DeckVersion $version): array
    {
        $index = [];
        foreach ($version->getCards() as $card) {
            $key = $card->getSetCode().'|'.$card->getCardNumber();
            $index[$key] = $card;
        }

        return $index;
    }
}
