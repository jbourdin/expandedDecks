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

use App\Entity\DeckVersion;
use App\Service\DeckList\CardmarketWishlistFormatter;
use App\Service\DeckList\MinifiedCardView;
use App\Service\DeckList\MinifiedCardViewBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.11 — Export deck list for Cardmarket wishlist
 */
class CardmarketWishlistFormatterTest extends TestCase
{
    private CardmarketWishlistFormatter $formatter;

    protected function setUp(): void
    {
        $viewBuilder = $this->createStub(MinifiedCardViewBuilder::class);
        $this->formatter = new CardmarketWishlistFormatter($viewBuilder);
    }

    public function testPokemonWithAbilitiesAndAttacks(): void
    {
        $version = $this->createVersionWithCardViews([
            'pokemon' => [
                new MinifiedCardView('Genesect V', 4, 'FST', '185', 'pokemon', null, null, 'Fusion Strike System', 'Techno Blast'),
                new MinifiedCardView('Mew V', 4, 'FST', '113', 'pokemon', null, null, '', 'Psychic Leap,Energy Mix'),
            ],
        ]);

        $result = $this->formatter->format($version);

        self::assertSame(
            "4x Genesect V Fusion Strike System Techno Blast\n4x Mew V Psychic Leap Energy Mix",
            $result,
        );
    }

    public function testTrainersUseNameOnly(): void
    {
        $version = $this->createVersionWithCardViews([
            'trainer' => [
                new MinifiedCardView('Battle VIP Pass', 4, 'FST', '225', 'trainer', 'item', null),
                new MinifiedCardView('VS Seeker', 3, 'PHF', '109', 'trainer', 'item', null),
            ],
        ]);

        $result = $this->formatter->format($version);

        self::assertSame("4x Battle VIP Pass\n3x VS Seeker", $result);
    }

    public function testBasicEnergiesAreExcluded(): void
    {
        $version = $this->createVersionWithCardViews([
            'pokemon' => [
                new MinifiedCardView('Comfey', 2, 'LOR', '79', 'pokemon', null, null, '', 'Flower Selecting'),
            ],
            'energy' => [
                new MinifiedCardView('Fire Energy', 11, 'MEE', '2', 'energy', null, null),
                new MinifiedCardView('Psychic Energy', 4, 'MEE', '5', 'energy', null, null),
            ],
        ]);

        $result = $this->formatter->format($version);

        self::assertSame('2x Comfey Flower Selecting', $result);
    }

    public function testSpecialEnergiesAreIncluded(): void
    {
        $version = $this->createVersionWithCardViews([
            'energy' => [
                new MinifiedCardView('Double Turbo Energy', 4, 'BRS', '151', 'energy', null, null),
                new MinifiedCardView('Fire Energy', 8, 'MEE', '2', 'energy', null, null),
            ],
        ]);

        $result = $this->formatter->format($version);

        self::assertSame('4x Double Turbo Energy', $result);
    }

    public function testReturnsNullWhenNoCardViews(): void
    {
        $version = $this->createStub(DeckVersion::class);
        $version->method('getMinifiedCardViews')->willReturn(null);

        self::assertNull($this->formatter->format($version));
    }

    public function testReturnsNullWhenCardViewsEmpty(): void
    {
        $version = $this->createVersionWithCardViews([]);

        self::assertNull($this->formatter->format($version));
    }

    public function testReturnsNullWhenOnlyBasicEnergies(): void
    {
        $version = $this->createVersionWithCardViews([
            'energy' => [
                new MinifiedCardView('Grass Energy', 8, 'MEE', '1', 'energy', null, null),
                new MinifiedCardView('Water Energy', 4, 'MEE', '3', 'energy', null, null),
            ],
        ]);

        self::assertNull($this->formatter->format($version));
    }

    public function testPokemonWithoutSignaturesFallsBackToNameOnly(): void
    {
        $version = $this->createVersionWithCardViews([
            'pokemon' => [
                new MinifiedCardView('Unknown Pokemon', 1, 'XYZ', '1', 'pokemon', null, null),
            ],
        ]);

        self::assertSame('1x Unknown Pokemon', $this->formatter->format($version));
    }

    /**
     * @param array<string, list<MinifiedCardView>> $grouped
     */
    private function createVersionWithCardViews(array $grouped): DeckVersion
    {
        $version = $this->createStub(DeckVersion::class);
        $version->method('getMinifiedCardViews')->willReturn(MinifiedCardView::serializeGrouped($grouped));

        return $version;
    }
}
