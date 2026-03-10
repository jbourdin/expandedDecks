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
use App\Message\EnrichDeckVersionMessage;
use App\MessageHandler\EnrichDeckVersionHandler;
use App\Repository\DeckVersionRepository;
use App\Service\Tcgdex\CardEnricher;
use App\Service\Tcgdex\CardEnrichmentReport;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class EnrichDeckVersionHandlerTest extends TestCase
{
    private CardEnricher $cardEnricher;
    private DeckVersionRepository $versionRepository;
    private LoggerInterface $logger;
    private EnrichDeckVersionHandler $handler;

    protected function setUp(): void
    {
        $this->cardEnricher = $this->createStub(CardEnricher::class);
        $this->versionRepository = $this->createStub(DeckVersionRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new EnrichDeckVersionHandler(
            $this->cardEnricher,
            $this->versionRepository,
            $this->logger,
        );
    }

    public function testDeckVersionNotFoundLogsWarningAndReturnsEarly(): void
    {
        $this->versionRepository->method('find')->willReturn(null);

        $this->logger->expects(self::once())
            ->method('warning')
            ->with('DeckVersion #{id} not found for enrichment.', ['id' => 42]);

        $this->logger->expects(self::never())->method('info');

        ($this->handler)(new EnrichDeckVersionMessage(42));
    }

    public function testEnrichmentSucceedsWithAllCardsFound(): void
    {
        $version = new DeckVersion();
        $this->versionRepository->method('find')->willReturn($version);

        $report = new CardEnrichmentReport(
            enrichedCount: 10,
            notFoundCount: 0,
            notFoundCards: [],
            legalityWarnings: [],
        );
        $this->cardEnricher->method('enrichVersion')->willReturn($report);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Enriched DeckVersion #{id}: {enriched} enriched, {notFound} not found.',
                ['id' => 7, 'enriched' => 10, 'notFound' => 0],
            );

        $this->logger->expects(self::never())->method('warning');

        ($this->handler)(new EnrichDeckVersionMessage(7));
    }

    public function testReportWithNotFoundCardsLogsWarningWithCardNames(): void
    {
        $version = new DeckVersion();
        $this->versionRepository->method('find')->willReturn($version);

        $report = new CardEnrichmentReport(
            enrichedCount: 8,
            notFoundCount: 2,
            notFoundCards: ['Pikachu (BRS 25)', 'Charizard (SIT 99)'],
            legalityWarnings: [],
        );
        $this->cardEnricher->method('enrichVersion')->willReturn($report);

        $expectedWarningCalls = [
            [
                'Not found in TCGdex for DeckVersion #{id}: {cards}',
                ['id' => 5, 'cards' => 'Pikachu (BRS 25); Charizard (SIT 99)'],
            ],
        ];

        $warningCallIndex = 0;
        $this->logger->expects(self::once())
            ->method('warning')
            ->willReturnCallback(static function (string $message, array $context) use ($expectedWarningCalls, &$warningCallIndex): void {
                self::assertSame($expectedWarningCalls[$warningCallIndex][0], $message);
                self::assertSame($expectedWarningCalls[$warningCallIndex][1], $context);
                ++$warningCallIndex;
            });

        ($this->handler)(new EnrichDeckVersionMessage(5));
    }

    public function testReportWithLegalityWarningsLogsWarning(): void
    {
        $version = new DeckVersion();
        $this->versionRepository->method('find')->willReturn($version);

        $report = new CardEnrichmentReport(
            enrichedCount: 10,
            notFoundCount: 0,
            notFoundCards: [],
            legalityWarnings: ['"Lysandre" (FLF 90) is not marked as Expanded-legal in TCGdex.'],
        );
        $this->cardEnricher->method('enrichVersion')->willReturn($report);

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                'Legality warnings for DeckVersion #{id}: {warnings}',
                ['id' => 3, 'warnings' => '"Lysandre" (FLF 90) is not marked as Expanded-legal in TCGdex.'],
            );

        ($this->handler)(new EnrichDeckVersionMessage(3));
    }

    public function testReportWithBothNotFoundAndLegalityWarningsLogsBothWarnings(): void
    {
        $version = new DeckVersion();
        $this->versionRepository->method('find')->willReturn($version);

        $report = new CardEnrichmentReport(
            enrichedCount: 7,
            notFoundCount: 1,
            notFoundCards: ['Unknown Card (XY 99)'],
            legalityWarnings: ['"Banned Card" (BW 10) is not marked as Expanded-legal in TCGdex.'],
        );
        $this->cardEnricher->method('enrichVersion')->willReturn($report);

        $warningMessages = [];
        $this->logger->expects(self::exactly(2))
            ->method('warning')
            ->willReturnCallback(static function (string $message) use (&$warningMessages): void {
                $warningMessages[] = $message;
            });

        ($this->handler)(new EnrichDeckVersionMessage(9));

        self::assertSame('Not found in TCGdex for DeckVersion #{id}: {cards}', $warningMessages[0]);
        self::assertSame('Legality warnings for DeckVersion #{id}: {warnings}', $warningMessages[1]);
    }
}
