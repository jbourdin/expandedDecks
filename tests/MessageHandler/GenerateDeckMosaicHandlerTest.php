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

use App\Entity\DeckVersion;
use App\Message\GenerateDeckMosaicMessage;
use App\MessageHandler\GenerateDeckMosaicHandler;
use App\Repository\DeckVersionRepository;
use App\Service\Mosaic\MosaicGenerator;
use App\Service\Mosaic\MosaicUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
final class GenerateDeckMosaicHandlerTest extends TestCase
{
    private MosaicGenerator $generator;
    private MosaicUrlResolver $urlResolver;
    private DeckVersionRepository $versionRepo;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->generator = $this->createStub(MosaicGenerator::class);
        $this->urlResolver = $this->createStub(MosaicUrlResolver::class);
        $this->versionRepo = $this->createStub(DeckVersionRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function createHandler(?EntityManagerInterface $entityManager = null, ?LoggerInterface $logger = null): GenerateDeckMosaicHandler
    {
        return new GenerateDeckMosaicHandler(
            $this->generator,
            $this->urlResolver,
            $this->versionRepo,
            $entityManager ?? $this->entityManager,
            $logger ?? $this->logger,
        );
    }

    public function testVersionNotFoundLogsWarning(): void
    {
        $this->versionRepo->method('find')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('DeckVersion #{id} not found for mosaic generation.', ['id' => 99]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        ($this->createHandler($entityManager, $logger))(new GenerateDeckMosaicMessage(99));
    }

    public function testVersionNotEnrichedSkipsMosaic(): void
    {
        $version = new DeckVersion();
        $version->setEnrichmentStatus('pending');
        $this->versionRepo->method('find')->willReturn($version);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'DeckVersion #{id} not fully enriched (status: {status}), skipping mosaic.',
                self::callback(static fn (array $context): bool => 'pending' === $context['status']),
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        ($this->createHandler($entityManager, $logger))(new GenerateDeckMosaicMessage(5));
    }

    public function testSuccessfulGenerationSetsUrlAndFlushes(): void
    {
        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');
        $this->versionRepo->method('find')->willReturn($version);

        $this->generator->method('generate')->willReturn('mosaic/1/5.png');
        $this->urlResolver->method('resolveForVersion')->willReturn('/mosaic/AB3K7N/5.png');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        ($this->createHandler($entityManager))(new GenerateDeckMosaicMessage(5));

        self::assertSame('/mosaic/AB3K7N/5.png', $version->getMosaicImageUrl());
    }

    public function testGenerationFailureLogsErrorAndRethrows(): void
    {
        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');
        $this->versionRepo->method('find')->willReturn($version);

        $exception = new \RuntimeException('GD failed');
        $this->generator->method('generate')->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Mosaic generation failed for DeckVersion #{id}: {error}',
                self::callback(static fn (array $context): bool => 'GD failed' === $context['error']),
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GD failed');

        ($this->createHandler(logger: $logger))(new GenerateDeckMosaicMessage(5));
    }
}
