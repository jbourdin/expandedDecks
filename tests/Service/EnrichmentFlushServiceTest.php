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

namespace App\Tests\Service;

use App\Service\EnrichmentFlushService;
use Doctrine\DBAL\Connection;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class EnrichmentFlushServiceTest extends TestCase
{
    public function testFlushClearsDeckCardEnrichmentFields(): void
    {
        $connection = $this->createMock(Connection::class);
        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('listContents')->willReturn(new DirectoryListing(new \EmptyIterator()));

        $connection->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql): int {
                static $callIndex = 0;

                if (0 === $callIndex++) {
                    self::assertStringContainsString('deck_card', $sql);
                    self::assertStringContainsString('card_printing_id = NULL', $sql);
                }

                return 0;
            });

        $service = new EnrichmentFlushService($connection, $storage, new NullLogger());
        $service->flush();
    }

    public function testFlushResetsDeckVersionFieldsIncludingMinifiedCardViews(): void
    {
        $connection = $this->createMock(Connection::class);
        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('listContents')->willReturn(new DirectoryListing(new \EmptyIterator()));

        $executedStatements = [];
        $connection->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;

                return 0;
            });

        $service = new EnrichmentFlushService($connection, $storage, new NullLogger());
        $service->flush();

        // Second statement is the DeckVersion reset
        $deckVersionSql = $executedStatements[1];
        self::assertStringContainsString('deck_version', $deckVersionSql);
        self::assertStringContainsString("enrichment_status = 'pending'", $deckVersionSql);
        self::assertStringContainsString('mosaic_image_url = NULL', $deckVersionSql);
        self::assertStringContainsString('minified_list = NULL', $deckVersionSql);
        self::assertStringContainsString('minified_card_views = NULL', $deckVersionSql);
        self::assertStringContainsString('minified_mosaic_image_url = NULL', $deckVersionSql);
    }

    public function testFlushDeletesCardPrintingAndCardIdentity(): void
    {
        $connection = $this->createMock(Connection::class);
        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('listContents')->willReturn(new DirectoryListing(new \EmptyIterator()));

        $executedStatements = [];
        $connection->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;

                return 0;
            });

        $service = new EnrichmentFlushService($connection, $storage, new NullLogger());
        $service->flush();

        self::assertSame('DELETE FROM card_printing', $executedStatements[2]);
        self::assertSame('DELETE FROM card_identity', $executedStatements[3]);
    }

    public function testFlushExecutesStatementsInCorrectOrder(): void
    {
        $connection = $this->createMock(Connection::class);
        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('listContents')->willReturn(new DirectoryListing(new \EmptyIterator()));

        $executedStatements = [];
        $connection->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;

                return 0;
            });

        $service = new EnrichmentFlushService($connection, $storage, new NullLogger());
        $service->flush();

        // Order matters: cards → versions → printings (FK) → identities
        self::assertStringContainsString('deck_card', $executedStatements[0]);
        self::assertStringContainsString('deck_version', $executedStatements[1]);
        self::assertStringContainsString('card_printing', $executedStatements[2]);
        self::assertStringContainsString('card_identity', $executedStatements[3]);
    }

    public function testFlushDeletesEveryFileInMosaicStorageAndSkipsDirectories(): void
    {
        $connection = $this->createStub(Connection::class);

        $listing = new DirectoryListing(new \ArrayIterator([
            new FileAttributes('mosaic/abc/deck.png'),
            new DirectoryAttributes('mosaic/abc'),
            new FileAttributes('mosaic/abc/deck-mini.png'),
        ]));

        $storage = $this->createMock(FilesystemOperator::class);
        // Assert the listing is scoped to the `mosaic` prefix recursively, while configuring the return value.
        $storage->expects(self::once())
            ->method('listContents')
            ->with('mosaic', true)
            ->willReturn($listing);

        // Only the two FileAttributes entries should trigger a delete.
        $deleted = [];
        $storage->expects(self::exactly(2))
            ->method('delete')
            ->willReturnCallback(static function (string $path) use (&$deleted): void {
                $deleted[] = $path;
            });

        $service = new EnrichmentFlushService($connection, $storage, new NullLogger());
        $service->flush();

        self::assertSame(['mosaic/abc/deck.png', 'mosaic/abc/deck-mini.png'], $deleted);
    }

    public function testFlushLogsErrorWhenMosaicStorageThrows(): void
    {
        $connection = $this->createStub(Connection::class);

        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('listContents')->willThrowException(new class('boom') extends \RuntimeException implements FilesystemException {});

        // The flush should still succeed (DB statements have already run); the
        // filesystem failure is caught and logged so a transient S3 outage
        // doesn't roll back the data wipe.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Failed to clear mosaic storage: {error}', ['error' => 'boom']);

        $service = new EnrichmentFlushService($connection, $storage, $logger);
        $service->flush();
    }
}
