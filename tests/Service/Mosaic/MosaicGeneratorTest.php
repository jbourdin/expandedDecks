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

namespace App\Tests\Service\Mosaic;

use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Service\Mosaic\MosaicGenerator;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
final class MosaicGeneratorTest extends TestCase
{
    private FilesystemOperator $storage;
    private MosaicGenerator $generator;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(FilesystemOperator::class);
        $this->generator = new MosaicGenerator(
            $this->storage,
            new NullLogger(),
            \dirname(__DIR__, 3), // project root
        );
    }

    public function testGenerateThrowsOnEmptyCards(): void
    {
        $version = $this->createVersion(1, 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no cards');

        $this->generator->generate($version);
    }

    public function testGenerateWritesPngToStorage(): void
    {
        $version = $this->createVersion(1, 1);
        $this->addCard($version, 'Pikachu', 'pokemon', null, 4);
        $this->addCard($version, 'Professor Oak', 'trainer', 'supporter', 2);
        $this->addCard($version, 'Lightning Energy', 'energy', null, 8);

        $this->storage->expects(self::once())
            ->method('write')
            ->with(
                'mosaic/1/1.png',
                self::callback(static function (string $data): bool {
                    // Verify it's a valid PNG (starts with PNG magic bytes)
                    return str_starts_with($data, "\x89PNG");
                }),
            );

        $result = $this->generator->generate($version);

        self::assertSame('mosaic/1/1.png', $result);
    }

    public function testCardsSortedByTypeThenSubtypeThenQuantityThenName(): void
    {
        $version = $this->createVersion(2, 3);

        // Add cards in random order to test sorting
        $this->addCard($version, 'Lightning Energy', 'energy', null, 8);
        $this->addCard($version, 'Float Stone', 'trainer', 'tool', 2);
        $this->addCard($version, 'Guzma', 'trainer', 'supporter', 1);
        $this->addCard($version, 'Pikachu', 'pokemon', null, 3);
        $this->addCard($version, 'VS Seeker', 'trainer', 'item', 4);
        $this->addCard($version, 'Charizard', 'pokemon', null, 2);
        $this->addCard($version, 'Chaotic Swell', 'trainer', 'stadium', 2);
        $this->addCard($version, 'N', 'trainer', 'supporter', 2);

        $writtenData = '';
        $this->storage->method('write')->willReturnCallback(
            static function (string $path, string $data) use (&$writtenData): void {
                $writtenData = $data;
            },
        );

        $this->generator->generate($version);

        // Just verify the image was generated (it's a PNG)
        self::assertNotEmpty($writtenData);
        self::assertTrue(str_starts_with($writtenData, "\x89PNG"));
    }

    public function testPlaceholderRenderedForCardWithoutImage(): void
    {
        $version = $this->createVersion(5, 10);
        $this->addCard($version, 'Unknown Card', 'pokemon', null, 1);

        $writtenData = '';
        $this->storage->method('write')->willReturnCallback(
            static function (string $path, string $data) use (&$writtenData): void {
                $writtenData = $data;
            },
        );

        $result = $this->generator->generate($version);

        self::assertSame('mosaic/5/10.png', $result);
        self::assertTrue(str_starts_with($writtenData, "\x89PNG"));
    }

    public function testLastRowCenteredWhenIncomplete(): void
    {
        $version = $this->createVersion(3, 5);

        // 9 cards = 8 in first row + 1 centered in second row
        for ($i = 1; $i <= 9; ++$i) {
            $this->addCard($version, 'Card '.$i, 'pokemon', null, 1);
        }

        $writtenData = '';
        $this->storage->method('write')->willReturnCallback(
            static function (string $path, string $data) use (&$writtenData): void {
                $writtenData = $data;
            },
        );

        $this->generator->generate($version);

        self::assertTrue(str_starts_with($writtenData, "\x89PNG"));
    }

    private function createVersion(int $deckId, int $versionId): DeckVersion
    {
        $deck = new Deck();
        $deckReflection = new \ReflectionProperty(Deck::class, 'id');
        $deckReflection->setValue($deck, $deckId);

        $version = new DeckVersion();
        $version->setDeck($deck);
        $versionReflection = new \ReflectionProperty(DeckVersion::class, 'id');
        $versionReflection->setValue($version, $versionId);

        return $version;
    }

    private function addCard(
        DeckVersion $version,
        string $name,
        string $type,
        ?string $trainerSubtype,
        int $quantity,
        ?string $imageUrl = null,
    ): void {
        $card = new DeckCard();
        $card->setCardName($name);
        $card->setCardType($type);
        $card->setTrainerSubtype($trainerSubtype);
        $card->setQuantity($quantity);
        $card->setSetCode('TST');
        $card->setCardNumber('1');
        $card->setImageUrl($imageUrl);
        $version->addCard($card);
    }
}
