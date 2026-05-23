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
use App\Message\BackfillDeckCardSortOrderMessage;
use App\MessageHandler\BackfillDeckCardSortOrderHandler;
use App\Repository\DeckVersionRepository;
use App\Service\Deck\DeckCardSortBackfillService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F2.28 — Preserve imported list order
 */
class BackfillDeckCardSortOrderHandlerTest extends TestCase
{
    private DeckVersionRepository $versionRepository;
    private DeckCardSortBackfillService $backfillService;
    private LoggerInterface $logger;
    private BackfillDeckCardSortOrderHandler $handler;

    protected function setUp(): void
    {
        $this->versionRepository = $this->createStub(DeckVersionRepository::class);
        $this->backfillService = $this->createStub(DeckCardSortBackfillService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new BackfillDeckCardSortOrderHandler(
            $this->versionRepository,
            $this->backfillService,
            $this->logger,
        );
    }

    public function testVersionNotFoundLogsWarningAndReturnsEarly(): void
    {
        $this->versionRepository->method('find')->willReturn(null);

        $this->logger->expects(self::once())
            ->method('warning')
            ->with('DeckVersion #{id} not found for sort-order backfill.', ['id' => 42]);

        $this->logger->expects(self::never())->method('info');

        ($this->handler)(new BackfillDeckCardSortOrderMessage(42));
    }

    public function testSkippedReportProducesNoLog(): void
    {
        $version = new DeckVersion();
        $this->versionRepository->method('find')->willReturn($version);
        $this->backfillService->method('backfillVersion')->willReturn([
            'changed' => 0,
            'missing' => 0,
            'skipped' => true,
        ]);

        $this->logger->expects(self::never())->method('info');
        $this->logger->expects(self::never())->method('warning');

        ($this->handler)(new BackfillDeckCardSortOrderMessage(7));
    }

    public function testMissingCardsAreLoggedAtInfo(): void
    {
        $version = new DeckVersion();
        $this->versionRepository->method('find')->willReturn($version);
        $this->backfillService->method('backfillVersion')->willReturn([
            'changed' => 12,
            'missing' => 3,
            'skipped' => false,
        ]);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                self::stringContains('{changed} updated, {missing} DB cards not found'),
                ['id' => 7, 'changed' => 12, 'missing' => 3],
            );

        ($this->handler)(new BackfillDeckCardSortOrderMessage(7));
    }

    public function testCleanRunProducesNoInfoLog(): void
    {
        // changed > 0 but missing == 0 → no log emitted (only missing > 0 logs).
        $version = new DeckVersion();
        $this->versionRepository->method('find')->willReturn($version);
        $this->backfillService->method('backfillVersion')->willReturn([
            'changed' => 30,
            'missing' => 0,
            'skipped' => false,
        ]);

        $this->logger->expects(self::never())->method('info');

        ($this->handler)(new BackfillDeckCardSortOrderMessage(7));
    }
}
