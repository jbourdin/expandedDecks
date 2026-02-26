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

namespace App\Tests\Service;

use App\Service\DeckListParser;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.1 — Parse PTCG text format
 */
class DeckListParserTest extends TestCase
{
    private DeckListParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DeckListParser();
    }

    public function testParseFullDeckList(): void
    {
        $rawList = <<<'PTCG'
            Pokémon: 16
            2 Arceus VSTAR BRS 123
            2 Arceus V BRS 122
            2 Giratina VSTAR LOR 131
            2 Giratina V LOR 130
            2 Comfey LOR 79
            1 Sableye LOR 70
            1 Lumineon V BRS 40
            1 Radiant Greninja ASR 46
            1 Manaphy BRS 41
            1 Drapion V LOR 118
            1 Cramorant LOR 50

            Trainer: 36
            4 Colress's Experiment LOR 155
            4 Battle VIP Pass FST 225
            2 Switch Cart ASR 154
            2 Escape Rope BST 125
            2 Ultra Ball BRS 150
            2 Nest Ball SVI 181
            1 Hisuian Heavy Ball ASR 146
            1 Ordinary Rod SSH 171
            1 Super Rod PAL 188
            1 Lost Vacuum LOR 162
            1 Mirage Gate LOR 163
            1 Energy Recycler BST 124
            1 Echoing Horn CRE 136
            1 Guzma & Hala CEC 193
            1 Boss's Orders BRS 132
            1 Roxanne ASR 150
            1 Klara CRE 145
            1 Trainers' Mail ROS 92
            1 Tool Scrapper DRX 116
            2 Air Balloon SSH 156
            1 Forest Seal Stone SIT 156
            1 PokéStop PGO 68
            1 Temple of Sinnoh ASR 155
            2 Lost City LOR 161

            Energy: 8
            3 Psychic Energy SVE 5
            3 Grass Energy SVE 1
            2 V Guard Energy SIT 169

            Total Cards: 60
            PTCG;

        $result = $this->parser->parse($rawList);

        self::assertTrue($result->isValid());
        self::assertSame(60, $result->totalCards());
        self::assertCount(0, $result->errors);
        self::assertCount(38, $result->cards);

        self::assertSame(['pokemon' => 16, 'trainer' => 36, 'energy' => 8], $result->sectionTotals);

        $firstCard = $result->cards[0];
        self::assertSame(2, $firstCard->quantity);
        self::assertSame('Arceus VSTAR', $firstCard->cardName);
        self::assertSame('BRS', $firstCard->setCode);
        self::assertSame('123', $firstCard->cardNumber);
        self::assertSame('pokemon', $firstCard->cardType);

        $trainerCard = $result->cards[11];
        self::assertSame('trainer', $trainerCard->cardType);

        $energyCard = $result->cards[35];
        self::assertSame('energy', $energyCard->cardType);
    }

    public function testParseWithPlainPokemonHeader(): void
    {
        $rawList = <<<'PTCG'
            Pokemon: 2
            2 Pikachu V CEL 25

            Trainer: 0

            Energy: 0
            PTCG;

        $result = $this->parser->parse($rawList);

        self::assertTrue($result->isValid());
        self::assertCount(1, $result->cards);
        self::assertSame('pokemon', $result->cards[0]->cardType);
        self::assertSame(['pokemon' => 2, 'trainer' => 0, 'energy' => 0], $result->sectionTotals);
    }

    public function testParseMultiWordNamesWithSpecialChars(): void
    {
        $rawList = <<<'PTCG'
            Trainer: 3
            1 Guzma & Hala CEC 193
            1 Trainers' Mail ROS 92
            1 Professor's Research CEL 24
            PTCG;

        $result = $this->parser->parse($rawList);

        self::assertTrue($result->isValid());
        self::assertCount(3, $result->cards);
        self::assertSame('Guzma & Hala', $result->cards[0]->cardName);
        self::assertSame("Trainers' Mail", $result->cards[1]->cardName);
        self::assertSame("Professor's Research", $result->cards[2]->cardName);
    }

    public function testParseInvalidLinesAppearInErrors(): void
    {
        $rawList = <<<'PTCG'
            Pokémon: 1
            1 Pikachu V CEL 25
            this is not a valid card line
            another bad line
            PTCG;

        $result = $this->parser->parse($rawList);

        self::assertFalse($result->isValid());
        self::assertCount(1, $result->cards);
        self::assertCount(2, $result->errors);
        self::assertStringContainsString('unrecognized format', $result->errors[0]);
        self::assertStringContainsString('this is not a valid card line', $result->errors[0]);
    }

    public function testParseEmptyInput(): void
    {
        $result = $this->parser->parse('');

        self::assertFalse($result->isValid());
        self::assertCount(0, $result->cards);
        self::assertCount(0, $result->errors);
        self::assertSame(0, $result->totalCards());
    }

    public function testParseTotalCardsLineIsSkipped(): void
    {
        $rawList = <<<'PTCG'
            Pokémon: 1
            1 Pikachu V CEL 25

            Total Cards: 1
            PTCG;

        $result = $this->parser->parse($rawList);

        self::assertTrue($result->isValid());
        self::assertCount(1, $result->cards);
        self::assertCount(0, $result->errors);
    }

    public function testParseCardBeforeSectionHeaderProducesError(): void
    {
        $rawList = <<<'PTCG'
            1 Pikachu V CEL 25
            Pokémon: 1
            1 Charizard V BRS 17
            PTCG;

        $result = $this->parser->parse($rawList);

        self::assertFalse($result->isValid());
        self::assertCount(1, $result->cards);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('before any section header', $result->errors[0]);
    }
}
