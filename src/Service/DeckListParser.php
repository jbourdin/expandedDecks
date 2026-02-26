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
 * Parses a PTCG-format deck list (copy-pasted from PTCGO/PTCGL).
 *
 * Expected format:
 *   Pokémon: 12
 *   4 Pikachu V CEL 25
 *   ...
 *   Trainer: 36
 *   4 Professor's Research CEL 24
 *   ...
 *   Energy: 12
 *   4 Lightning Energy SVE 4
 *
 * @see docs/features.md F6.1 — Parse PTCG text format
 */
class DeckListParser
{
    private const string SECTION_HEADER_PATTERN = '/^(Pok[eé]mon|Trainer|Energy)\s*:\s*(\d+)$/iu';
    private const string CARD_LINE_PATTERN = '/^(\d+)\s+(.+?)\s+([A-Z][A-Za-z0-9-]{1,5})\s+(\S+)$/';
    private const string TOTAL_LINE_PATTERN = '/^Total\s+Cards\s*:/i';

    private const array SECTION_MAP = [
        'pokemon' => 'pokemon',
        'pokémon' => 'pokemon',
        'trainer' => 'trainer',
        'energy' => 'energy',
    ];

    public function parse(string $rawText): DeckListParseResult
    {
        $lines = explode("\n", $rawText);
        $cards = [];
        $errors = [];
        $sectionTotals = [];
        $currentSection = null;

        foreach ($lines as $lineNumber => $rawLine) {
            $line = trim($rawLine);

            if ('' === $line) {
                continue;
            }

            if (preg_match(self::TOTAL_LINE_PATTERN, $line)) {
                continue;
            }

            if (preg_match(self::SECTION_HEADER_PATTERN, $line, $matches)) {
                $sectionKey = mb_strtolower($matches[1]);
                $currentSection = self::SECTION_MAP[$sectionKey] ?? null;

                if (null !== $currentSection) {
                    $sectionTotals[$currentSection] = (int) $matches[2];
                }

                continue;
            }

            if (preg_match(self::CARD_LINE_PATTERN, $line, $matches)) {
                if (null === $currentSection) {
                    $errors[] = \sprintf('Line %d: card line found before any section header: "%s"', $lineNumber + 1, $line);

                    continue;
                }

                $cards[] = new ParsedCard(
                    quantity: (int) $matches[1],
                    cardName: $matches[2],
                    setCode: $matches[3],
                    cardNumber: $matches[4],
                    cardType: $currentSection,
                );

                continue;
            }

            $errors[] = \sprintf('Line %d: unrecognized format: "%s"', $lineNumber + 1, $line);
        }

        return new DeckListParseResult($cards, $errors, $sectionTotals);
    }
}
