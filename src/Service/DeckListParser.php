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

use Symfony\Contracts\Translation\TranslatorInterface;

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
    public const string UNKNOWN_CARD_TYPE = 'unknown';

    /** Basic energy card names — detected as energy even without section headers. */
    public const array BASIC_ENERGY_NAMES = [
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

    /**
     * Default basic energy printings for minified export.
     * MEE (Mega Evolution Energy) for the 8 standard types, SUM (Sun & Moon) for Fairy.
     *
     * @see data/basic_energies.json — full catalogue
     * @see docs/technicalities/basic_energy_images.md
     *
     * @var array<string, array{setCode: string, cardNumber: string, imageUrl: string}>
     */
    public const array DEFAULT_BASIC_ENERGY_PRINTINGS = [
        'Grass Energy' => ['setCode' => 'MEE', 'cardNumber' => '1', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png'],
        'Fire Energy' => ['setCode' => 'MEE', 'cardNumber' => '2', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png'],
        'Water Energy' => ['setCode' => 'MEE', 'cardNumber' => '3', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png'],
        'Lightning Energy' => ['setCode' => 'MEE', 'cardNumber' => '4', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png'],
        'Psychic Energy' => ['setCode' => 'MEE', 'cardNumber' => '5', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png'],
        'Fighting Energy' => ['setCode' => 'MEE', 'cardNumber' => '6', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png'],
        'Darkness Energy' => ['setCode' => 'MEE', 'cardNumber' => '7', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png'],
        'Metal Energy' => ['setCode' => 'MEE', 'cardNumber' => '8', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png'],
        'Fairy Energy' => ['setCode' => 'SUM', 'cardNumber' => '172', 'imageUrl' => 'https://images.pokemontcg.io/sm1/172_hires.png'],
    ];

    private const array SECTION_MAP = [
        'pokemon' => 'pokemon',
        'pokémon' => 'pokemon',
        'trainer' => 'trainer',
        'energy' => 'energy',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

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
                $cardName = $matches[2];
                $cardType = $currentSection
                    ?? (\in_array($cardName, self::BASIC_ENERGY_NAMES, true) ? 'energy' : self::UNKNOWN_CARD_TYPE);

                $cards[] = new ParsedCard(
                    quantity: (int) $matches[1],
                    cardName: $cardName,
                    setCode: $matches[3],
                    cardNumber: $matches[4],
                    cardType: $cardType,
                );

                continue;
            }

            $errors[] = $this->translator->trans('app.deck.parse.unrecognized_format', [
                '%line%' => $lineNumber + 1,
                '%content%' => $line,
            ]);
        }

        return new DeckListParseResult($cards, $errors, $sectionTotals);
    }
}
