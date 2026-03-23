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
use App\Message\GenerateMinifiedListMessage;
use App\Message\GenerateMinifiedMosaicMessage;
use App\MessageHandler\GenerateMinifiedListHandler;
use App\Repository\DeckVersionRepository;
use App\Service\DeckList\MinifiedCardView;
use App\Service\DeckList\MinifiedCardViewBuilder;
use App\Service\DeckList\MinifiedListGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @see docs/features.md F6.8 — Minified deck list export
 */
final class GenerateMinifiedListHandlerTest extends TestCase
{
    private MinifiedListGenerator $listGenerator;
    private MinifiedCardViewBuilder $cardViewBuilder;
    private DeckVersionRepository $versionRepository;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->listGenerator = $this->createStub(MinifiedListGenerator::class);
        $this->cardViewBuilder = $this->createStub(MinifiedCardViewBuilder::class);
        $this->versionRepository = $this->createStub(DeckVersionRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function createHandler(
        ?EntityManagerInterface $entityManager = null,
        ?MessageBusInterface $messageBus = null,
        ?LoggerInterface $logger = null,
    ): GenerateMinifiedListHandler {
        return new GenerateMinifiedListHandler(
            $this->listGenerator,
            $this->cardViewBuilder,
            $this->versionRepository,
            $entityManager ?? $this->entityManager,
            $messageBus ?? $this->messageBus,
            $logger ?? $this->logger,
        );
    }

    public function testVersionNotFoundLogsWarning(): void
    {
        $this->versionRepository->method('find')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('DeckVersion #{id} not found for minified list generation.', ['id' => 42]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        ($this->createHandler($entityManager, logger: $logger))(new GenerateMinifiedListMessage(42));
    }

    public function testVersionNotEnrichedSkipsGeneration(): void
    {
        $version = new DeckVersion();
        $version->setEnrichmentStatus('pending');
        $this->versionRepository->method('find')->willReturn($version);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'DeckVersion #{id} not fully enriched, skipping minified list.',
                ['id' => 7],
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        ($this->createHandler($entityManager, logger: $logger))(new GenerateMinifiedListMessage(7));
    }

    public function testSuccessPathGeneratesListAndCardViewsAndDispatchesMosaic(): void
    {
        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');
        $this->versionRepository->method('find')->willReturn($version);

        $this->listGenerator->method('generate')->willReturn('Pokémon: 4\n4 Pikachu SVI 25');

        $groupedCardViews = [
            'pokemon' => [
                new MinifiedCardView('Pikachu', 4, 'SVI', '25', 'pokemon', null, null),
            ],
        ];
        $this->cardViewBuilder->method('buildGrouped')->willReturn($groupedCardViews);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (mixed $message): bool {
                return $message instanceof GenerateMinifiedMosaicMessage
                    && 5 === $message->deckVersionId;
            }))
            ->willReturn(new Envelope(new GenerateMinifiedMosaicMessage(5)));

        ($this->createHandler($entityManager, $messageBus))(new GenerateMinifiedListMessage(5));

        self::assertSame('Pokémon: 4\n4 Pikachu SVI 25', $version->getMinifiedList());
        self::assertNotNull($version->getMinifiedCardViews());

        // Verify that the stored card views can be deserialized back
        $deserialized = MinifiedCardView::deserializeGrouped($version->getMinifiedCardViews());
        self::assertCount(1, $deserialized['pokemon']);
        self::assertSame('Pikachu', $deserialized['pokemon'][0]->getCardName());
    }

    public function testGenerationFailureLogsErrorAndRethrows(): void
    {
        $version = new DeckVersion();
        $version->setEnrichmentStatus('done');
        $this->versionRepository->method('find')->willReturn($version);

        $exception = new \RuntimeException('Generation failed');
        $this->listGenerator->method('generate')->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Minified list generation failed for DeckVersion #{id}: {error}',
                self::callback(static fn (array $context): bool => 'Generation failed' === $context['error']),
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Generation failed');

        ($this->createHandler(logger: $logger))(new GenerateMinifiedListMessage(5));
    }

    public function testVersionWithInProgressEnrichmentIsSkipped(): void
    {
        $version = new DeckVersion();
        $version->setEnrichmentStatus('in_progress');
        $this->versionRepository->method('find')->willReturn($version);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        ($this->createHandler($entityManager))(new GenerateMinifiedListMessage(10));
    }
}
