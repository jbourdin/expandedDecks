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

use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Message\GenerateMinifiedMosaicMessage;
use App\MessageHandler\GenerateMinifiedMosaicHandler;
use App\Repository\CardPrintingRepository;
use App\Repository\DeckVersionRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\Mosaic\MosaicGenerator;
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
        );

        $handler(new GenerateMinifiedMosaicMessage(1));
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
        $mosaicGenerator->expects(self::once())->method('generateFromTiles');

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
        );

        $handler(new GenerateMinifiedMosaicMessage(1));

        self::assertSame('https://example.com/mosaic/minified.webp', $version->getMinifiedMosaicImageUrl());
    }
}
