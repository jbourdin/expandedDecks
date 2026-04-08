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
}
