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

namespace App\Tests\MessageHandler;

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Message\GenerateMinifiedMosaicMessage;
use App\MessageHandler\GenerateMinifiedMosaicHandler;
use App\Repository\CardPrintingRepository;
use App\Repository\DeckVersionRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\DeckList\MinifiedCardViewBuilder;
use App\Service\Mosaic\MosaicGenerator;
use App\Service\Mosaic\MosaicTile;
use App\Service\Mosaic\MosaicUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F6.6b — Minified mosaic
 */
final class GenerateMinifiedMosaicHandlerTest extends TestCase
{
    /**
     * When the DeckVersion is not found, the handler logs a warning and returns early.
     */
    public function testVersionNotFoundLogsWarningAndReturns(): void
    {
        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('find')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $mosaicGenerator = $this->createStub(MosaicGenerator::class);
        $mosaicUrlResolver = $this->createStub(MosaicUrlResolver::class);
        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $handler = new GenerateMinifiedMosaicHandler(
            $mosaicGenerator,
            $mosaicUrlResolver,
            $printingRepository,
            $identityResolver,
            $versionRepository,
            $entityManager,
            $logger,
            $this->createStub(MinifiedCardViewBuilder::class),
        );

        $handler(new GenerateMinifiedMosaicMessage(999));
    }

    /**
     * When the DeckVersion is not enriched (enrichmentStatus !== 'done'), the handler skips.
     */
    public function testVersionNotEnrichedSkips(): void
    {
        $version = new DeckVersion();
        $version->setEnrichmentStatus('pending');

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('find')->willReturn($version);

        $mosaicGenerator = $this->createMock(MosaicGenerator::class);
        $mosaicGenerator->expects(self::never())->method('generateFromTiles');

        $mosaicUrlResolver = $this->createStub(MosaicUrlResolver::class);
        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $identityResolver = $this->createStub(CardIdentityResolver::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $handler = new GenerateMinifiedMosaicHandler(
            $mosaicGenerator,
            $mosaicUrlResolver,
            $printingRepository,
            $identityResolver,
            $versionRepository,
            $entityManager,
            $logger,
            $this->createStub(MinifiedCardViewBuilder::class),
        );

        $handler(new GenerateMinifiedMosaicMessage(1));
    }

    /**
     * Covers the early return when generateFromTiles() returns an empty string (no tiles).
     *
     * @see docs/features.md F6.6b — Minified mosaic
     */
    public function testEmptyStoragePathReturnsEarlyWithoutFlush(): void
    {
        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('find')->willReturn($version);

        $mosaicGenerator = $this->createStub(MosaicGenerator::class);
        $mosaicGenerator->method('generateFromTiles')->willReturn('');

        $mosaicUrlResolver = $this->createStub(MosaicUrlResolver::class);
        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $identityResolver = $this->createStub(CardIdentityResolver::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $logger = $this->createStub(LoggerInterface::class);

        $handler = new GenerateMinifiedMosaicHandler(
            $mosaicGenerator,
            $mosaicUrlResolver,
            $printingRepository,
            $identityResolver,
            $versionRepository,
            $entityManager,
            $logger,
            $this->createStub(MinifiedCardViewBuilder::class),
        );

        $handler(new GenerateMinifiedMosaicMessage(1));

        self::assertNull($version->getMinifiedMosaicImageUrl());
    }

    /**
     * Success path: version is enriched, mosaic is generated, URL is set.
     */
    public function testSuccessPathGeneratesMosaicAndSetsUrl(): void
    {
        $card = new DeckCard();
        $card->setCardName('Pikachu');
        $card->setSetCode('BRS');
        $card->setCardNumber('50');
        $card->setCardType('pokemon');
        $card->setQuantity(2);

        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');
        $version->addCard($card);

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('find')->willReturn($version);

        $mosaicGenerator = $this->createMock(MosaicGenerator::class);
        $mosaicGenerator->expects(self::once())->method('generateFromTiles')->willReturn('mosaics/1-minified.webp');

        $mosaicUrlResolver = $this->createStub(MosaicUrlResolver::class);
        $mosaicUrlResolver->method('resolveForVersion')->willReturn('https://example.com/mosaic/minified.webp');

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $identityResolver = $this->createStub(CardIdentityResolver::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createStub(LoggerInterface::class);

        $handler = new GenerateMinifiedMosaicHandler(
            $mosaicGenerator,
            $mosaicUrlResolver,
            $printingRepository,
            $identityResolver,
            $versionRepository,
            $entityManager,
            $logger,
            $this->createStub(MinifiedCardViewBuilder::class),
        );

        $handler(new GenerateMinifiedMosaicMessage(1));

        self::assertSame('https://example.com/mosaic/minified.webp', $version->getMinifiedMosaicImageUrl());
    }

    /**
     * Tiles built from cards with a CardPrinting must carry the printing reference
     * so the mosaic generator can use the fallback-aware CardImageResolver.
     */
    public function testTilesCarryCardPrintingForFallbackResolution(): void
    {
        $identity = new CardIdentity();
        $identity->setName('Dialga GX');
        $identity->setCategory('pokemon');

        $printing = new CardPrinting();
        $printing->setTcgdexId('sm6-82');
        $printing->setImageUrl('https://broken.example.com/404.webp');
        $printing->setCardIdentity($identity);
        $identity->addPrinting($printing);

        $card = new DeckCard();
        $card->setCardName('Dialga GX');
        $card->setSetCode('FLI');
        $card->setCardNumber('82');
        $card->setCardType('pokemon');
        $card->setQuantity(2);
        $card->setCardPrinting($printing);

        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');
        $version->addCard($card);

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('find')->willReturn($version);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findLowestRarityForIdentity')->willReturn($printing);

        $identityResolver = $this->createStub(CardIdentityResolver::class);

        // Capture the tiles passed to generateFromTiles to verify printing is set
        $capturedTiles = null;
        $mosaicGenerator = $this->createMock(MosaicGenerator::class);
        $mosaicGenerator->expects(self::once())
            ->method('generateFromTiles')
            ->with(
                $version,
                self::callback(static function (array $tiles) use (&$capturedTiles): bool {
                    $capturedTiles = $tiles;

                    return true;
                }),
                'minified',
            )
            ->willReturn('mosaic/1/1_minified.png');

        $mosaicUrlResolver = $this->createStub(MosaicUrlResolver::class);
        $mosaicUrlResolver->method('resolveForVersion')->willReturn('https://example.com/mosaic.webp');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $handler = new GenerateMinifiedMosaicHandler(
            $mosaicGenerator,
            $mosaicUrlResolver,
            $printingRepository,
            $identityResolver,
            $versionRepository,
            $entityManager,
            $logger,
            $this->createStub(MinifiedCardViewBuilder::class),
        );

        $handler(new GenerateMinifiedMosaicMessage(1));

        self::assertNotNull($capturedTiles);
        self::assertCount(1, $capturedTiles);

        /** @var MosaicTile $tile */
        $tile = $capturedTiles[0];
        self::assertSame('Dialga GX', $tile->cardName);
        self::assertSame(2, $tile->quantity);
        self::assertSame($printing, $tile->printing, 'Tile must carry the CardPrinting for fallback-aware image resolution');
    }

    /**
     * The catch-block re-raises after logging — exercises the error
     * branch and the rethrow.
     */
    public function testExceptionInPipelineIsLoggedAndRethrown(): void
    {
        $card = new DeckCard();
        $card->setCardName('Pikachu');
        $card->setSetCode('BRS');
        $card->setCardNumber('50');
        $card->setCardType('pokemon');
        $card->setQuantity(2);

        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');
        $version->addCard($card);

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('find')->willReturn($version);

        $mosaicGenerator = $this->createStub(MosaicGenerator::class);
        $mosaicGenerator->method('generateFromTiles')->willThrowException(new \RuntimeException('disk full'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $handler = new GenerateMinifiedMosaicHandler(
            $mosaicGenerator,
            $this->createStub(MosaicUrlResolver::class),
            $this->createStub(CardPrintingRepository::class),
            $this->createStub(CardIdentityResolver::class),
            $versionRepository,
            $this->createStub(EntityManagerInterface::class),
            $logger,
            $this->createStub(MinifiedCardViewBuilder::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('disk full');

        $handler(new GenerateMinifiedMosaicMessage(1));
    }

    /**
     * MINIFIED_PRINTING_OVERRIDES short-circuits the resolution chain with
     * a static URL and no CardPrinting.
     */
    public function testStaticOverrideShortCircuitsImageResolution(): void
    {
        // GEN|73 is in DeckListParser::MINIFIED_PRINTING_OVERRIDES.
        $card = new DeckCard();
        $card->setCardName('Energy Switch');
        $card->setSetCode('GEN');
        $card->setCardNumber('73');
        $card->setCardType('trainer');
        $card->setQuantity(4);

        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');
        $version->addCard($card);

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('find')->willReturn($version);

        // The override should mean the printingRepository is never asked.
        $printingRepository = $this->createMock(CardPrintingRepository::class);
        $printingRepository->expects(self::never())->method('findLowestRarityForIdentity');

        $capturedTiles = null;
        $mosaicGenerator = $this->createStub(MosaicGenerator::class);
        $mosaicGenerator->method('generateFromTiles')->willReturnCallback(static function ($v, $tiles) use (&$capturedTiles): string {
            $capturedTiles = $tiles;

            return 'mosaic/1/1_minified.webp';
        });

        $mosaicUrlResolver = $this->createStub(MosaicUrlResolver::class);
        $mosaicUrlResolver->method('resolveForVersion')->willReturn('https://example.com/mosaic.webp');

        $handler = new GenerateMinifiedMosaicHandler(
            $mosaicGenerator,
            $mosaicUrlResolver,
            $printingRepository,
            $this->createStub(CardIdentityResolver::class),
            $versionRepository,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(MinifiedCardViewBuilder::class),
        );

        $handler(new GenerateMinifiedMosaicMessage(1));

        self::assertNotNull($capturedTiles);
        self::assertCount(1, $capturedTiles);
        // Static override: tile.printing is null and image URL is from the override map.
        self::assertNull($capturedTiles[0]->printing);
        self::assertNotNull($capturedTiles[0]->imageUrl);
    }

    /**
     * Cards sharing the same name + image URL collapse into one tile with
     * summed quantity. This is the de-dup path in buildMergedTiles.
     */
    public function testTilesWithSameImageAndNameMergeWithSummedQuantity(): void
    {
        // Two cards with the same name + same override image collapse into one.
        $cardA = new DeckCard();
        $cardA->setCardName('Energy Switch');
        $cardA->setSetCode('GEN');
        $cardA->setCardNumber('73');
        $cardA->setCardType('trainer');
        $cardA->setQuantity(2);

        $cardB = new DeckCard();
        $cardB->setCardName('Energy Switch');
        $cardB->setSetCode('GEN');
        $cardB->setCardNumber('73');
        $cardB->setCardType('trainer');
        $cardB->setQuantity(2);

        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');
        $version->addCard($cardA);
        $version->addCard($cardB);

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('find')->willReturn($version);

        $capturedTiles = null;
        $mosaicGenerator = $this->createStub(MosaicGenerator::class);
        $mosaicGenerator->method('generateFromTiles')->willReturnCallback(static function ($v, $tiles) use (&$capturedTiles): string {
            $capturedTiles = $tiles;

            return 'mosaic/1/1_minified.webp';
        });

        $mosaicUrlResolver = $this->createStub(MosaicUrlResolver::class);
        $mosaicUrlResolver->method('resolveForVersion')->willReturn('https://example.com/mosaic.webp');

        $handler = new GenerateMinifiedMosaicHandler(
            $mosaicGenerator,
            $mosaicUrlResolver,
            $this->createStub(CardPrintingRepository::class),
            $this->createStub(CardIdentityResolver::class),
            $versionRepository,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(MinifiedCardViewBuilder::class),
        );

        $handler(new GenerateMinifiedMosaicMessage(1));

        self::assertNotNull($capturedTiles);
        self::assertCount(1, $capturedTiles, 'Two cards with same name + image must merge into one tile.');
        self::assertSame(4, $capturedTiles[0]->quantity, 'Merged tile carries the summed quantity.');
    }

    /**
     * Tile sort order: pokemon (qty desc, name asc) → trainer (no subtype
     * differentiation here) → energy. Sets the subtype-based ordering
     * branch via the cardType key alone since trainerSubtype is computed
     * from CardPrinting -> CardIdentity (out of scope for this assertion).
     */
    public function testTilesAreSortedByTypeThenQuantityThenName(): void
    {
        $cards = [
            $this->buildSimpleCard('Lightning', 'energy', 5, 'BRS', 'E1'),
            $this->buildSimpleCard('Switch', 'trainer', 4, 'BRS', '60'),
            $this->buildSimpleCard('Boss', 'trainer', 2, 'BRS', '50'),
            $this->buildSimpleCard('Charizard', 'pokemon', 2, 'BRS', '20'),
            $this->buildSimpleCard('Bulbasaur', 'pokemon', 4, 'BRS', '10'),
        ];

        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');
        foreach ($cards as $card) {
            $version->addCard($card);
        }

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('find')->willReturn($version);

        $capturedTiles = null;
        $mosaicGenerator = $this->createStub(MosaicGenerator::class);
        $mosaicGenerator->method('generateFromTiles')->willReturnCallback(static function ($v, $tiles) use (&$capturedTiles): string {
            $capturedTiles = $tiles;

            return 'mosaic/1/1_minified.webp';
        });

        $mosaicUrlResolver = $this->createStub(MosaicUrlResolver::class);
        $mosaicUrlResolver->method('resolveForVersion')->willReturn('https://example.com/mosaic.webp');

        $handler = new GenerateMinifiedMosaicHandler(
            $mosaicGenerator,
            $mosaicUrlResolver,
            $this->createStub(CardPrintingRepository::class),
            $this->createStub(CardIdentityResolver::class),
            $versionRepository,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(MinifiedCardViewBuilder::class),
        );

        $handler(new GenerateMinifiedMosaicMessage(1));

        self::assertNotNull($capturedTiles);
        $names = array_map(static fn (MosaicTile $t): string => $t->cardName, $capturedTiles);
        // Type order pokemon → trainer → energy.
        // Within pokemon: qty desc (4 Bulbasaur, 2 Charizard).
        // Within trainer: qty desc (4 Switch, 2 Boss).
        self::assertSame(['Bulbasaur', 'Charizard', 'Switch', 'Boss', 'Lightning'], $names);
    }

    private function buildSimpleCard(string $name, string $type, int $quantity, string $setCode, string $number): DeckCard
    {
        $card = new DeckCard();
        $card->setCardName($name);
        $card->setCardType($type);
        $card->setQuantity($quantity);
        $card->setSetCode($setCode);
        $card->setCardNumber($number);

        return $card;
    }
}
