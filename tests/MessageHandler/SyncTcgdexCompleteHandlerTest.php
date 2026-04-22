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
use App\Message\BuildSetMappingsMessage;
use App\Message\EnrichDeckVersionMessage;
use App\Message\SyncTcgdexCompleteMessage;
use App\MessageHandler\SyncTcgdexCompleteHandler;
use App\Repository\DeckVersionRepository;
use App\Service\Tcgdex\TcgdexSyncStatusService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
final class SyncTcgdexCompleteHandlerTest extends TestCase
{
    /** @var list<object> */
    private array $dispatchedMessages = [];

    public function testDispatchesMappingsAndReenrichment(): void
    {
        $version = $this->createStub(DeckVersion::class);
        $version->method('getId')->willReturn(42);

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('findNotEnriched')->willReturn([$version]);

        $syncStatus = $this->createMock(TcgdexSyncStatusService::class);
        $syncStatus->expects(self::once())->method('recordSyncCompleted');

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatchedMessages[] = $message;

            return new Envelope($message);
        });

        $handler = new SyncTcgdexCompleteHandler(
            $versionRepository,
            $syncStatus,
            $bus,
            $this->createStub(LoggerInterface::class),
        );

        $handler(new SyncTcgdexCompleteMessage());

        $mappingMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof BuildSetMappingsMessage);
        self::assertCount(1, $mappingMessages);

        $enrichMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof EnrichDeckVersionMessage);
        self::assertCount(1, $enrichMessages);

        $enrichMessages = array_values($enrichMessages);
        self::assertSame(42, $enrichMessages[0]->deckVersionId);
    }

    public function testSkipsVersionsWithNullId(): void
    {
        $version = $this->createStub(DeckVersion::class);
        $version->method('getId')->willReturn(null);

        $versionRepository = $this->createStub(DeckVersionRepository::class);
        $versionRepository->method('findNotEnriched')->willReturn([$version]);

        $syncStatus = $this->createStub(TcgdexSyncStatusService::class);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatchedMessages[] = $message;

            return new Envelope($message);
        });

        $handler = new SyncTcgdexCompleteHandler(
            $versionRepository,
            $syncStatus,
            $bus,
            $this->createStub(LoggerInterface::class),
        );

        $handler(new SyncTcgdexCompleteMessage());

        $enrichMessages = array_filter($this->dispatchedMessages, static fn (object $message): bool => $message instanceof EnrichDeckVersionMessage);
        self::assertCount(0, $enrichMessages);
    }
}
