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
use App\Service\DeckList\CardmarketExpansionNameMapper;
use App\Service\DeckList\CardmarketWishlistFormatter;
use App\Service\DeckList\MinifiedCardView;
use App\Service\DeckList\MinifiedCardViewBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.11 — Export deck list for Cardmarket wishlist
 */
class CardmarketWishlistFormatterTest extends TestCase
{
    private CardmarketWishlistFormatter $formatter;
    private MinifiedCardViewBuilder $viewBuilder;

    protected function setUp(): void
    {
        $this->viewBuilder = $this->createStub(MinifiedCardViewBuilder::class);
        $mapper = new CardmarketExpansionNameMapper();
        $this->formatter = new CardmarketWishlistFormatter($this->viewBuilder, $mapper);
    }

    public function testFormatProducesCorrectOutput(): void
    {
        $version = $this->createVersionWithCards();

        $this->viewBuilder->method('buildGrouped')->willReturn([
            'pokemon' => [
                new MinifiedCardView('Arceus VSTAR', 3, 'BRS', '123', 'pokemon', null, null),
                new MinifiedCardView('Comfey', 2, 'LOR', '79', 'pokemon', null, null),
            ],
            'trainer' => [
                new MinifiedCardView('Battle VIP Pass', 4, 'FST', '225', 'trainer', 'item', null),
            ],
        ]);

        $result = $this->formatter->format($version);

        self::assertSame(
            "3x Arceus VSTAR Brilliant Stars\n2x Comfey Lost Origin\n4x Battle VIP Pass Fusion Strike",
            $result,
        );
    }

    public function testBasicEnergiesAreExcluded(): void
    {
        $version = $this->createVersionWithCards();

        $this->viewBuilder->method('buildGrouped')->willReturn([
            'pokemon' => [
                new MinifiedCardView('Comfey', 2, 'LOR', '79', 'pokemon', null, null),
            ],
            'energy' => [
                new MinifiedCardView('Fire Energy', 11, 'MEE', '2', 'energy', null, null),
                new MinifiedCardView('Psychic Energy', 4, 'MEE', '5', 'energy', null, null),
            ],
        ]);

        $result = $this->formatter->format($version);

        self::assertSame('2x Comfey Lost Origin', $result);
    }

    public function testSpecialEnergiesAreIncluded(): void
    {
        $version = $this->createVersionWithCards();

        $this->viewBuilder->method('buildGrouped')->willReturn([
            'energy' => [
                new MinifiedCardView('Double Turbo Energy', 4, 'BRS', '151', 'energy', null, null),
                new MinifiedCardView('Fire Energy', 8, 'MEE', '2', 'energy', null, null),
            ],
        ]);

        $result = $this->formatter->format($version);

        self::assertSame('4x Double Turbo Energy Brilliant Stars', $result);
    }

    public function testReturnsNullWhenNoCards(): void
    {
        $version = $this->createStub(DeckVersion::class);
        $version->method('getCards')->willReturn(new ArrayCollection());

        self::assertNull($this->formatter->format($version));
    }

    public function testReturnsNullWhenBuilderReturnsEmpty(): void
    {
        $version = $this->createVersionWithCards();
        $this->viewBuilder->method('buildGrouped')->willReturn([]);

        self::assertNull($this->formatter->format($version));
    }

    public function testUnknownSetCodeFallsBackToRawCode(): void
    {
        $version = $this->createVersionWithCards();

        $this->viewBuilder->method('buildGrouped')->willReturn([
            'pokemon' => [
                new MinifiedCardView('Some Card', 1, 'ZZZZ', '1', 'pokemon', null, null),
            ],
        ]);

        $result = $this->formatter->format($version);

        self::assertSame('1x Some Card ZZZZ', $result);
    }

    public function testReturnsNullWhenOnlyBasicEnergies(): void
    {
        $version = $this->createVersionWithCards();

        $this->viewBuilder->method('buildGrouped')->willReturn([
            'energy' => [
                new MinifiedCardView('Grass Energy', 8, 'MEE', '1', 'energy', null, null),
                new MinifiedCardView('Water Energy', 4, 'MEE', '3', 'energy', null, null),
            ],
        ]);

        self::assertNull($this->formatter->format($version));
    }

    private function createVersionWithCards(): DeckVersion
    {
        $version = $this->createStub(DeckVersion::class);
        $card = $this->createStub(DeckCard::class);
        $version->method('getCards')->willReturn(new ArrayCollection([$card]));

        return $version;
    }
}
