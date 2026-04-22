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

use App\Command\SyncTcgdexCommand;
use App\Service\Tcgdex\TcgdexSyncStatusService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
final class SyncTcgdexCommandTest extends TestCase
{
    private function createCommand(int $queueDepth = 0, ?\DateTimeImmutable $lastSync = null): SyncTcgdexCommand
    {
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $syncStatus = $this->createStub(TcgdexSyncStatusService::class);
        $syncStatus->method('getQueueDepth')->willReturn($queueDepth);
        $syncStatus->method('getLastSyncTimestamp')->willReturn($lastSync);

        return new SyncTcgdexCommand($messageBus, $syncStatus);
    }

    public function testDefaultInsertModeDispatches(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('insert', $tester->getDisplay());
    }

    public function testUpdateModeDispatches(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--mode' => 'update']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('update', $tester->getDisplay());
    }

    public function testFullModeRequiresForce(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--mode' => 'full']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('--force', $tester->getDisplay());
    }

    public function testFullModeWithForceDispatches(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--mode' => 'full', '--force' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('full', $tester->getDisplay());
    }

    public function testInvalidModeReturnsError(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--mode' => 'banana']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('Invalid sync mode', $tester->getDisplay());
    }

    public function testWarnsWhenSyncAlreadyInProgress(): void
    {
        $tester = new CommandTester($this->createCommand(queueDepth: 15));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('15 messages pending', $tester->getDisplay());
    }

    public function testDisplaysLastSyncTimestamp(): void
    {
        $lastSync = new \DateTimeImmutable('2026-04-22 14:30:00');
        $tester = new CommandTester($this->createCommand(lastSync: $lastSync));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('2026-04-22 14:30:00', $tester->getDisplay());
    }
}
