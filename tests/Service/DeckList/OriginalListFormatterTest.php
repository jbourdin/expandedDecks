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

namespace App\Tests\Service\DeckList;

use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Service\DeckList\OriginalListFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.7 — Export deck list as PTCGL text
 */
class OriginalListFormatterTest extends TestCase
{
    private OriginalListFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new OriginalListFormatter();
    }

    public function testFormatsSinglePokemon(): void
    {
        $version = $this->createVersion([
            ['name' => 'Pikachu V', 'quantity' => 2, 'set' => 'VIV', 'number' => '43', 'type' => 'pokemon', 'subtype' => null],
        ]);

        $result = $this->formatter->format($version);

        self::assertSame("Pokémon: 2\n2 Pikachu V VIV 43\n\nTotal Cards: 2", $result);
    }

    public function testSortsByTypeOrder(): void
    {
        $version = $this->createVersion([
            ['name' => 'Grass Energy', 'quantity' => 4, 'set' => 'SVE', 'number' => '1', 'type' => 'energy', 'subtype' => null],
            ['name' => 'Boss\'s Orders', 'quantity' => 2, 'set' => 'BRS', 'number' => '132', 'type' => 'trainer', 'subtype' => 'Supporter'],
            ['name' => 'Pikachu V', 'quantity' => 3, 'set' => 'VIV', 'number' => '43', 'type' => 'pokemon', 'subtype' => null],
        ]);

        $result = $this->formatter->format($version);
        $lines = explode("\n", $result);

        self::assertSame('Pokémon: 3', $lines[0]);
        self::assertSame('3 Pikachu V VIV 43', $lines[1]);
        self::assertStringStartsWith('Trainer:', $lines[3]);
        self::assertStringStartsWith('Energy:', $lines[6]);
    }

    public function testSortsTrainersBySubtype(): void
    {
        $version = $this->createVersion([
            ['name' => 'Path to the Peak', 'quantity' => 1, 'set' => 'CRE', 'number' => '148', 'type' => 'trainer', 'subtype' => 'Stadium'],
            ['name' => 'Quick Ball', 'quantity' => 4, 'set' => 'FST', 'number' => '237', 'type' => 'trainer', 'subtype' => 'Item'],
            ['name' => 'Boss\'s Orders', 'quantity' => 2, 'set' => 'BRS', 'number' => '132', 'type' => 'trainer', 'subtype' => 'Supporter'],
            ['name' => 'Choice Belt', 'quantity' => 1, 'set' => 'BRS', 'number' => '135', 'type' => 'trainer', 'subtype' => 'Tool'],
        ]);

        $result = $this->formatter->format($version);
        $lines = explode("\n", $result);

        // After section header, order: Supporter → Item → Tool → Stadium
        self::assertStringContainsString('Boss\'s Orders', $lines[1]);
        self::assertStringContainsString('Quick Ball', $lines[2]);
        self::assertStringContainsString('Choice Belt', $lines[3]);
        self::assertStringContainsString('Path to the Peak', $lines[4]);
    }

    public function testSortsByQuantityDescendingThenNameAscending(): void
    {
        $version = $this->createVersion([
            ['name' => 'Zebstrika', 'quantity' => 1, 'set' => 'LOT', 'number' => '82', 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Arceus V', 'quantity' => 1, 'set' => 'BRS', 'number' => '122', 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Pikachu V', 'quantity' => 4, 'set' => 'VIV', 'number' => '43', 'type' => 'pokemon', 'subtype' => null],
        ]);

        $result = $this->formatter->format($version);
        $lines = explode("\n", $result);

        self::assertSame('4 Pikachu V VIV 43', $lines[1]);
        self::assertSame('1 Arceus V BRS 122', $lines[2]);
        self::assertSame('1 Zebstrika LOT 82', $lines[3]);
    }

    public function testTotalCardsCountsSumOfQuantities(): void
    {
        $version = $this->createVersion([
            ['name' => 'Pikachu V', 'quantity' => 4, 'set' => 'VIV', 'number' => '43', 'type' => 'pokemon', 'subtype' => null],
            ['name' => 'Quick Ball', 'quantity' => 4, 'set' => 'FST', 'number' => '237', 'type' => 'trainer', 'subtype' => 'Item'],
            ['name' => 'Grass Energy', 'quantity' => 8, 'set' => 'SVE', 'number' => '1', 'type' => 'energy', 'subtype' => null],
        ]);

        $result = $this->formatter->format($version);

        self::assertStringEndsWith('Total Cards: 16', $result);
    }

    public function testEmptyDeckProducesMinimalOutput(): void
    {
        $version = $this->createVersion([]);

        $result = $this->formatter->format($version);

        self::assertSame("\nTotal Cards: 0", $result);
    }

    /**
     * @param list<array{name: string, quantity: int, set: string, number: string, type: string, subtype: ?string}> $cards
     */
    private function createVersion(array $cards): DeckVersion
    {
        $deckCards = [];

        foreach ($cards as $card) {
            $deckCard = $this->createStub(DeckCard::class);
            $deckCard->method('getCardName')->willReturn($card['name']);
            $deckCard->method('getQuantity')->willReturn($card['quantity']);
            $deckCard->method('getSetCode')->willReturn($card['set']);
            $deckCard->method('getCardNumber')->willReturn($card['number']);
            $deckCard->method('getCardType')->willReturn($card['type']);
            $deckCard->method('getTrainerSubtype')->willReturn($card['subtype']);
            $deckCards[] = $deckCard;
        }

        $version = $this->createStub(DeckVersion::class);
        $version->method('getCards')->willReturn(new ArrayCollection($deckCards));

        return $version;
    }
}
