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

namespace App\Tests\Command;

use App\Command\EnrichRetryCommand;
use App\Entity\Deck;
use App\Entity\DeckVersion;
use App\Message\EnrichDeckVersionMessage;
use App\Repository\DeckVersionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class EnrichRetryCommandTest extends TestCase
{
    public function testNoVersionsNeedEnrichment(): void
    {
        $repository = $this->createStub(DeckVersionRepository::class);
        $repository->method('findNotEnriched')->willReturn([]);

        $messageBus = $this->createStub(MessageBusInterface::class);

        $command = new EnrichRetryCommand($repository, $messageBus);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No deck versions need enrichment', $tester->getDisplay());
    }

    public function testDispatchesMessagesForPendingVersions(): void
    {
        $deck = $this->createStub(Deck::class);
        $deck->method('getName')->willReturn('Test Deck');

        $version = $this->createStub(DeckVersion::class);
        $version->method('getId')->willReturn(42);
        $version->method('getDeck')->willReturn($deck);
        $version->method('getVersionNumber')->willReturn(1);
        $version->method('getEnrichmentStatus')->willReturn('pending');

        $repository = $this->createStub(DeckVersionRepository::class);
        $repository->method('findNotEnriched')->willReturn([$version]);

        $dispatchedMessages = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatchedMessages): Envelope {
                $dispatchedMessages[] = $message;

                return new Envelope($message);
            });

        $command = new EnrichRetryCommand($repository, $messageBus);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertCount(1, $dispatchedMessages);
        self::assertInstanceOf(EnrichDeckVersionMessage::class, $dispatchedMessages[0]);
        self::assertSame(42, $dispatchedMessages[0]->deckVersionId);
        self::assertStringContainsString('1 enrichment message(s) dispatched', $tester->getDisplay());
    }

    public function testDispatchesForMultipleVersions(): void
    {
        $deck = $this->createStub(Deck::class);
        $deck->method('getName')->willReturn('Deck');

        $version1 = $this->createStub(DeckVersion::class);
        $version1->method('getId')->willReturn(1);
        $version1->method('getDeck')->willReturn($deck);
        $version1->method('getVersionNumber')->willReturn(1);
        $version1->method('getEnrichmentStatus')->willReturn('pending');

        $version2 = $this->createStub(DeckVersion::class);
        $version2->method('getId')->willReturn(2);
        $version2->method('getDeck')->willReturn($deck);
        $version2->method('getVersionNumber')->willReturn(2);
        $version2->method('getEnrichmentStatus')->willReturn('failed');

        $repository = $this->createStub(DeckVersionRepository::class);
        $repository->method('findNotEnriched')->willReturn([$version1, $version2]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $command = new EnrichRetryCommand($repository, $messageBus);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('2 enrichment message(s) dispatched', $tester->getDisplay());
    }
}
