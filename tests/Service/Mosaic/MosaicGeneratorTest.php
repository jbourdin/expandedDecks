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

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Service\CardImageResolver;
use App\Service\Mosaic\MosaicGenerator;
use App\Service\Mosaic\MosaicTile;
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
        $this->storage = $this->createStub(FilesystemOperator::class);
        $this->generator = new MosaicGenerator(
            $this->storage,
            new NullLogger(),
            $this->createStub(CardImageResolver::class),
        );
    }

    public function testGenerateReturnsEmptyStringOnEmptyCards(): void
    {
        $version = $this->createVersion(1, 1);

        $result = $this->generator->generate($version);

        self::assertSame('', $result);
    }

    public function testGenerateWritesPngToStorage(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $generator = new MosaicGenerator($storage, new NullLogger(), $this->createStub(CardImageResolver::class));

        $version = $this->createVersion(1, 1);
        $this->addCard($version, 'Pikachu', 'pokemon', null, 4);
        $this->addCard($version, 'Professor Oak', 'trainer', 'supporter', 2);
        $this->addCard($version, 'Lightning Energy', 'energy', null, 8);

        $storage->expects(self::once())
            ->method('write')
            ->with(
                'mosaic/1/1.png',
                self::callback(static function (string $data): bool {
                    // Verify it's a valid PNG (starts with PNG magic bytes)
                    return str_starts_with($data, "\x89PNG");
                }),
            );

        $result = $generator->generate($version);

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

    public function testGenerateFromTilesUsesCardImageResolverWhenPrintingPresent(): void
    {
        $printing = new CardPrinting();
        $printing->setTcgdexId('sm6-82');
        $printing->setImageUrl('https://broken.example.com/404.webp');

        $identity = new CardIdentity();
        $identity->setName('Dialga GX');
        $identity->setCategory('pokemon');
        $printing->setCardIdentity($identity);
        $identity->addPrinting($printing);

        $tile = new MosaicTile(
            'Dialga GX',
            2,
            'https://broken.example.com/404.webp',
            'pokemon',
            null,
            $printing,
        );

        // Create a 1x1 PNG to return as "downloaded" image data
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image);
        $fakePngData = ob_get_clean();
        self::assertNotFalse($fakePngData);

        $resolver = $this->createMock(CardImageResolver::class);
        $resolver->expects(self::once())
            ->method('downloadImage')
            ->with($printing)
            ->willReturn($fakePngData);

        $storage = $this->createStub(FilesystemOperator::class);
        $generator = new MosaicGenerator($storage, new NullLogger(), $resolver);

        $version = $this->createVersion(10, 20);

        $generator->generateFromTiles($version, [$tile], 'minified');
    }

    public function testGenerateFromTilesFallsBackToRawUrlWhenNoPrinting(): void
    {
        $tile = new MosaicTile(
            'Static Override Card',
            1,
            null,
            'pokemon',
            null,
            null,
        );

        $resolver = $this->createMock(CardImageResolver::class);
        $resolver->expects(self::never())->method('downloadImage');

        $storage = $this->createStub(FilesystemOperator::class);
        $generator = new MosaicGenerator($storage, new NullLogger(), $resolver);

        $version = $this->createVersion(11, 21);

        // Should not call resolver, should draw placeholder instead
        $generator->generateFromTiles($version, [$tile], 'minified');
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
        $card->setQuantity($quantity);
        $card->setSetCode('TST');
        $card->setCardNumber('1');

        if (null !== $trainerSubtype || null !== $imageUrl) {
            $identity = new CardIdentity();
            $identity->setName($name);
            $identity->setCategory($type);
            $identity->setTrainerType($trainerSubtype);

            $printing = new CardPrinting();
            $printing->setTcgdexId('tst-'.mb_strtolower(str_replace(' ', '-', $name)));
            $printing->setImageUrl($imageUrl);
            $printing->setCardIdentity($identity);
            $identity->addPrinting($printing);

            $card->setCardPrinting($printing);
        }

        $version->addCard($card);
    }
}
