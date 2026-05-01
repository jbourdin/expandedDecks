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

use App\Entity\DeckVersion;

/**
 * Formats a DeckVersion's original cards into standard PTCGL text with section headers.
 *
 * Unlike MinifiedListGenerator, this does not resolve alternative printings —
 * it uses the owner's original set codes and card numbers.
 *
 * @see docs/features.md F6.7 — Export deck list as PTCGL text
 */
class OriginalListFormatter
{
    private const array SECTION_LABELS = ['pokemon' => 'Pokémon', 'trainer' => 'Trainer', 'energy' => 'Energy'];
    private const array TYPE_ORDER = ['pokemon' => 0, 'trainer' => 1, 'energy' => 2];
    private const array TRAINER_SUBTYPE_ORDER = ['supporter' => 0, 'item' => 1, 'tool' => 2, 'stadium' => 3];

    public function format(DeckVersion $version): string
    {
        /** @var list<array{quantity: int, name: string, setCode: string, cardNumber: string, cardType: string, trainerSubtype: ?string}> $entries */
        $entries = [];

        foreach ($version->getCards() as $card) {
            $entries[] = [
                'quantity' => $card->getQuantity(),
                'name' => $card->getCardName(),
                'setCode' => $card->getSetCode(),
                'cardNumber' => $card->getCardNumber(),
                'cardType' => $card->getCardType(),
                'trainerSubtype' => $card->getTrainerSubtype(),
            ];
        }

        usort($entries, static function (array $entryA, array $entryB): int {
            $typeA = self::TYPE_ORDER[$entryA['cardType']] ?? 3;
            $typeB = self::TYPE_ORDER[$entryB['cardType']] ?? 3;

            if ($typeA !== $typeB) {
                return $typeA <=> $typeB;
            }

            if ('trainer' === $entryA['cardType']) {
                $subtypeA = self::TRAINER_SUBTYPE_ORDER[strtolower((string) $entryA['trainerSubtype'])] ?? 4;
                $subtypeB = self::TRAINER_SUBTYPE_ORDER[strtolower((string) $entryB['trainerSubtype'])] ?? 4;

                if ($subtypeA !== $subtypeB) {
                    return $subtypeA <=> $subtypeB;
                }
            }

            if ($entryA['quantity'] !== $entryB['quantity']) {
                return $entryB['quantity'] <=> $entryA['quantity'];
            }

            return $entryA['name'] <=> $entryB['name'];
        });

        $lines = [];
        $currentType = null;

        foreach ($entries as $entry) {
            $type = $entry['cardType'];

            if ($type !== $currentType) {
                if (null !== $currentType) {
                    $lines[] = '';
                }

                $sectionCount = $this->countSectionCards($entries, $type);
                $lines[] = \sprintf('%s: %d', self::SECTION_LABELS[$type] ?? ucfirst($type), $sectionCount);
                $currentType = $type;
            }

            $lines[] = \sprintf('%d %s %s %s', $entry['quantity'], $entry['name'], $entry['setCode'], CardNumberFormatter::display($entry['cardNumber']));
        }

        $lines[] = '';
        $lines[] = \sprintf('Total Cards: %d', $this->countTotalCards($entries));

        return implode("\n", $lines);
    }

    /**
     * @param list<array{quantity: int, name: string, setCode: string, cardNumber: string, cardType: string, trainerSubtype: ?string}> $entries
     */
    private function countSectionCards(array $entries, string $type): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            if ($entry['cardType'] === $type) {
                $count += $entry['quantity'];
            }
        }

        return $count;
    }

    /**
     * @param list<array{quantity: int, name: string, setCode: string, cardNumber: string, cardType: string, trainerSubtype: ?string}> $entries
     */
    private function countTotalCards(array $entries): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            $count += $entry['quantity'];
        }

        return $count;
    }
}
