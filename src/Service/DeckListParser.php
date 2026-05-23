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

    /**
     * Basic energy card names — detected as energy even without section headers.
     * Includes all PTCGL export languages: EN, FR, DE, ES, IT, PT, JA.
     */
    public const array BASIC_ENERGY_NAMES = [
        // English
        'Grass Energy',
        'Fire Energy',
        'Water Energy',
        'Lightning Energy',
        'Psychic Energy',
        'Fighting Energy',
        'Darkness Energy',
        'Metal Energy',
        'Fairy Energy',
        // French
        'Énergie Plante',
        'Énergie Feu',
        'Énergie Eau',
        'Énergie Électrique',
        'Énergie Psy',
        'Énergie Combat',
        'Énergie Obscurité',
        'Énergie Métal',
        'Énergie Fée',
        // German
        'Pflanzenenergie',
        'Feuerenergie',
        'Wasserenergie',
        'Elektroenergie',
        'Psychoenergie',
        'Kampfenergie',
        'Finsternis-Energie',
        'Metallenergie',
        'Feen-Energie',
        // Spanish
        'Energía Planta',
        'Energía Fuego',
        'Energía Agua',
        'Energía Rayo',
        'Energía Psíquica',
        'Energía Lucha',
        'Energía Oscura',
        'Energía Metálica',
        'Energía Hada',
        // Italian
        'Energia Erba',
        'Energia Fuoco',
        'Energia Acqua',
        'Energia Lampo',
        'Energia Psico',
        'Energia Lotta',
        'Energia Oscurità',
        'Energia Metallo',
        'Energia Folletto',
        // Portuguese
        'Energia de Grama',
        'Energia de Fogo',
        'Energia de Água',
        'Energia de Raios',
        'Energia Psíquica',
        'Energia de Luta',
        'Energia Noturna',
        'Energia de Metal',
        'Energia de Fada',
        // Japanese
        '基本草エネルギー',
        '基本炎エネルギー',
        '基本水エネルギー',
        '基本雷エネルギー',
        '基本超エネルギー',
        '基本闘エネルギー',
        '基本悪エネルギー',
        '基本鋼エネルギー',
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
        // French
        'Énergie Plante' => ['setCode' => 'MEE', 'cardNumber' => '1', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png'],
        'Énergie Feu' => ['setCode' => 'MEE', 'cardNumber' => '2', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png'],
        'Énergie Eau' => ['setCode' => 'MEE', 'cardNumber' => '3', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png'],
        'Énergie Électrique' => ['setCode' => 'MEE', 'cardNumber' => '4', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png'],
        'Énergie Psy' => ['setCode' => 'MEE', 'cardNumber' => '5', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png'],
        'Énergie Combat' => ['setCode' => 'MEE', 'cardNumber' => '6', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png'],
        'Énergie Obscurité' => ['setCode' => 'MEE', 'cardNumber' => '7', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png'],
        'Énergie Métal' => ['setCode' => 'MEE', 'cardNumber' => '8', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png'],
        'Énergie Fée' => ['setCode' => 'SUM', 'cardNumber' => '172', 'imageUrl' => 'https://images.pokemontcg.io/sm1/172_hires.png'],
        // German
        'Pflanzenenergie' => ['setCode' => 'MEE', 'cardNumber' => '1', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png'],
        'Feuerenergie' => ['setCode' => 'MEE', 'cardNumber' => '2', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png'],
        'Wasserenergie' => ['setCode' => 'MEE', 'cardNumber' => '3', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png'],
        'Elektroenergie' => ['setCode' => 'MEE', 'cardNumber' => '4', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png'],
        'Psychoenergie' => ['setCode' => 'MEE', 'cardNumber' => '5', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png'],
        'Kampfenergie' => ['setCode' => 'MEE', 'cardNumber' => '6', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png'],
        'Finsternis-Energie' => ['setCode' => 'MEE', 'cardNumber' => '7', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png'],
        'Metallenergie' => ['setCode' => 'MEE', 'cardNumber' => '8', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png'],
        'Feen-Energie' => ['setCode' => 'SUM', 'cardNumber' => '172', 'imageUrl' => 'https://images.pokemontcg.io/sm1/172_hires.png'],
        // Spanish
        'Energía Planta' => ['setCode' => 'MEE', 'cardNumber' => '1', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png'],
        'Energía Fuego' => ['setCode' => 'MEE', 'cardNumber' => '2', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png'],
        'Energía Agua' => ['setCode' => 'MEE', 'cardNumber' => '3', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png'],
        'Energía Rayo' => ['setCode' => 'MEE', 'cardNumber' => '4', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png'],
        'Energía Psíquica' => ['setCode' => 'MEE', 'cardNumber' => '5', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png'],
        'Energía Lucha' => ['setCode' => 'MEE', 'cardNumber' => '6', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png'],
        'Energía Oscura' => ['setCode' => 'MEE', 'cardNumber' => '7', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png'],
        'Energía Metálica' => ['setCode' => 'MEE', 'cardNumber' => '8', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png'],
        'Energía Hada' => ['setCode' => 'SUM', 'cardNumber' => '172', 'imageUrl' => 'https://images.pokemontcg.io/sm1/172_hires.png'],
        // Italian
        'Energia Erba' => ['setCode' => 'MEE', 'cardNumber' => '1', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png'],
        'Energia Fuoco' => ['setCode' => 'MEE', 'cardNumber' => '2', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png'],
        'Energia Acqua' => ['setCode' => 'MEE', 'cardNumber' => '3', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png'],
        'Energia Lampo' => ['setCode' => 'MEE', 'cardNumber' => '4', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png'],
        'Energia Psico' => ['setCode' => 'MEE', 'cardNumber' => '5', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png'],
        'Energia Lotta' => ['setCode' => 'MEE', 'cardNumber' => '6', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png'],
        'Energia Oscurità' => ['setCode' => 'MEE', 'cardNumber' => '7', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png'],
        'Energia Metallo' => ['setCode' => 'MEE', 'cardNumber' => '8', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png'],
        'Energia Folletto' => ['setCode' => 'SUM', 'cardNumber' => '172', 'imageUrl' => 'https://images.pokemontcg.io/sm1/172_hires.png'],
        // Portuguese
        'Energia de Grama' => ['setCode' => 'MEE', 'cardNumber' => '1', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png'],
        'Energia de Fogo' => ['setCode' => 'MEE', 'cardNumber' => '2', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png'],
        'Energia de Água' => ['setCode' => 'MEE', 'cardNumber' => '3', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png'],
        'Energia de Raios' => ['setCode' => 'MEE', 'cardNumber' => '4', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png'],
        'Energia Psíquica' => ['setCode' => 'MEE', 'cardNumber' => '5', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png'],
        'Energia de Luta' => ['setCode' => 'MEE', 'cardNumber' => '6', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png'],
        'Energia Noturna' => ['setCode' => 'MEE', 'cardNumber' => '7', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png'],
        'Energia de Metal' => ['setCode' => 'MEE', 'cardNumber' => '8', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png'],
        'Energia de Fada' => ['setCode' => 'SUM', 'cardNumber' => '172', 'imageUrl' => 'https://images.pokemontcg.io/sm1/172_hires.png'],
        // Japanese
        '基本草エネルギー' => ['setCode' => 'MEE', 'cardNumber' => '1', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png'],
        '基本炎エネルギー' => ['setCode' => 'MEE', 'cardNumber' => '2', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png'],
        '基本水エネルギー' => ['setCode' => 'MEE', 'cardNumber' => '3', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png'],
        '基本雷エネルギー' => ['setCode' => 'MEE', 'cardNumber' => '4', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png'],
        '基本超エネルギー' => ['setCode' => 'MEE', 'cardNumber' => '5', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png'],
        '基本闘エネルギー' => ['setCode' => 'MEE', 'cardNumber' => '6', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png'],
        '基本悪エネルギー' => ['setCode' => 'MEE', 'cardNumber' => '7', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png'],
        '基本鋼エネルギー' => ['setCode' => 'MEE', 'cardNumber' => '8', 'imageUrl' => 'https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png'],
    ];

    /**
     * Static minified printing overrides for cards with known TCGdex data issues.
     *
     * When TCGdex returns incorrect images for specific printings, this map
     * forces the minified export to use an alternative printing instead.
     * Keyed by "{PTCGL_SET_CODE}|{cardNumber}".
     *
     * @var array<string, array{setCode: string, cardNumber: string, imageUrl: string}>
     */
    public const array MINIFIED_PRINTING_OVERRIDES = [
        // GEN 73: TCGdex image for g1-73 shows the full-art 73a instead of regular Uncommon
        'GEN|73' => ['setCode' => 'XY', 'cardNumber' => '129', 'imageUrl' => 'https://assets.tcgdex.net/en/xy/xy1/129/high.webp'],
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
                    sortOrder: $lineNumber,
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
