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
    private const array TYPE_ORDER = ['pokemon' => 0, 'trainer' => 1, 'energy' => 2];
    private const array SUBTYPE_ORDER = ['supporter' => 0, 'item' => 1, 'tool' => 2, 'stadium' => 3];

    /**
     * Compare two deck versions and return both categorized and unified card changes.
     *
     * The `unified` list merges all cards into a single sorted list ordered by
     * card type (Pokemon → Trainer by subtype → Energy), then by new quantity
     * descending, then by name ascending. Each entry includes a `status` field
     * (added, removed, changed, unchanged) and a `delta` field (+N or -N).
     *
     * @return array{
     *     added: list<array{cardName: string, setCode: string, cardNumber: string, quantity: int, cardType: string, trainerSubtype: string|null, imageUrl: string|null}>,
     *     removed: list<array{cardName: string, setCode: string, cardNumber: string, quantity: int, cardType: string, trainerSubtype: string|null, imageUrl: string|null}>,
     *     changed: list<array{cardName: string, setCode: string, cardNumber: string, oldQuantity: int, newQuantity: int, cardType: string, trainerSubtype: string|null, imageUrl: string|null}>,
     *     unchanged: list<array{cardName: string, setCode: string, cardNumber: string, quantity: int, cardType: string, trainerSubtype: string|null, imageUrl: string|null}>,
     *     unified: list<array{cardName: string, setCode: string, cardNumber: string, oldQuantity: int, newQuantity: int, delta: int, status: string, cardType: string, trainerSubtype: string|null, imageUrl: string|null}>
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
        $unified = [];

        foreach ($newCards as $key => $card) {
            $trainerSubtype = $card->getTrainerSubtype();

            if (!isset($oldCards[$key])) {
                $entry = $this->buildCardEntry($card, $trainerSubtype);
                $added[] = $entry;
                $unified[] = $entry + ['oldQuantity' => 0, 'newQuantity' => $card->getQuantity(), 'delta' => $card->getQuantity(), 'status' => 'added'];
            } elseif ($oldCards[$key]->getQuantity() !== $card->getQuantity()) {
                $oldQuantity = $oldCards[$key]->getQuantity();
                $newQuantity = $card->getQuantity();
                $changedEntry = $this->buildCardEntry($card, $trainerSubtype);
                $changedEntry['oldQuantity'] = $oldQuantity;
                $changedEntry['newQuantity'] = $newQuantity;
                unset($changedEntry['quantity']);
                $changed[] = $changedEntry;
                $unified[] = $this->buildCardEntry($card, $trainerSubtype) + ['oldQuantity' => $oldQuantity, 'newQuantity' => $newQuantity, 'delta' => $newQuantity - $oldQuantity, 'status' => 'changed'];
            } else {
                $entry = $this->buildCardEntry($card, $trainerSubtype);
                $unchanged[] = $entry;
                $unified[] = $entry + ['oldQuantity' => $card->getQuantity(), 'newQuantity' => $card->getQuantity(), 'delta' => 0, 'status' => 'unchanged'];
            }
        }

        foreach ($oldCards as $key => $card) {
            if (!isset($newCards[$key])) {
                $trainerSubtype = $card->getTrainerSubtype();
                $entry = $this->buildCardEntry($card, $trainerSubtype);
                $removed[] = $entry;
                $unified[] = $entry + ['oldQuantity' => $card->getQuantity(), 'newQuantity' => 0, 'delta' => -$card->getQuantity(), 'status' => 'removed'];
            }
        }

        usort($unified, $this->unifiedSortComparator(...));

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'unchanged' => $unchanged,
            'unified' => $unified,
        ];
    }

    /**
     * @return array{cardName: string, setCode: string, cardNumber: string, quantity: int, cardType: string, trainerSubtype: string|null, imageUrl: string|null}
     */
    private function buildCardEntry(DeckCard $card, ?string $trainerSubtype): array
    {
        return [
            'cardName' => $card->getCardName(),
            'setCode' => $card->getSetCode(),
            'cardNumber' => $card->getCardNumber(),
            'quantity' => $card->getQuantity(),
            'cardType' => $card->getCardType(),
            'trainerSubtype' => $trainerSubtype,
            'imageUrl' => $card->getImageUrl(),
        ];
    }

    /**
     * Sort unified diff entries by type (Pokemon → Trainer by subtype → Energy),
     * then by new quantity descending, then by name ascending.
     *
     * @param array{cardType: string, trainerSubtype: string|null, newQuantity: int, cardName: string} $entryA
     * @param array{cardType: string, trainerSubtype: string|null, newQuantity: int, cardName: string} $entryB
     */
    private function unifiedSortComparator(array $entryA, array $entryB): int
    {
        $typeA = self::TYPE_ORDER[strtolower($entryA['cardType'])] ?? 9;
        $typeB = self::TYPE_ORDER[strtolower($entryB['cardType'])] ?? 9;

        if ($typeA !== $typeB) {
            return $typeA <=> $typeB;
        }

        // Within trainers, sort by subtype
        if ('trainer' === strtolower($entryA['cardType'])) {
            $subtypeA = self::SUBTYPE_ORDER[strtolower((string) $entryA['trainerSubtype'])] ?? 9;
            $subtypeB = self::SUBTYPE_ORDER[strtolower((string) $entryB['trainerSubtype'])] ?? 9;

            if ($subtypeA !== $subtypeB) {
                return $subtypeA <=> $subtypeB;
            }
        }

        // Then by new quantity descending (removed cards with qty 0 sort by old quantity instead)
        if ($entryA['newQuantity'] !== $entryB['newQuantity']) {
            return $entryB['newQuantity'] <=> $entryA['newQuantity'];
        }

        // When new quantity is 0 (removed cards), sort by old quantity descending
        if (0 === $entryA['newQuantity'] && $entryA['oldQuantity'] !== $entryB['oldQuantity']) {
            return $entryB['oldQuantity'] <=> $entryA['oldQuantity'];
        }

        // Then by name ascending
        return strcmp($entryA['cardName'], $entryB['cardName']);
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
