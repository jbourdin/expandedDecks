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
use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
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
                    self::assertStringContainsString('tcgdex_id = NULL', $sql);
                    self::assertStringContainsString('image_url = NULL', $sql);
                    self::assertStringContainsString('trainer_subtype = NULL', $sql);
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
}
