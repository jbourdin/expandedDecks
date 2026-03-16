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

use App\Command\BannedCardsSyncCommand;
use App\Service\BannedCardsSyncResult;
use App\Service\BannedCardsSyncService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @see docs/features.md F6.5 — Banned card list management
 */
class BannedCardsSyncCommandTest extends TestCase
{
    public function testCommandOutputsResultOnSuccess(): void
    {
        $service = $this->createStub(BannedCardsSyncService::class);
        $service->method('sync')->willReturn(new BannedCardsSyncResult(
            success: true,
            added: 4,
            removed: 1,
            unchanged: 10,
        ));

        $command = new BannedCardsSyncCommand($service);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('4 added', $tester->getDisplay());
        self::assertStringContainsString('1 removed', $tester->getDisplay());
        self::assertStringContainsString('10 unchanged', $tester->getDisplay());
    }

    public function testCommandDisplaysWarnings(): void
    {
        $service = $this->createStub(BannedCardsSyncService::class);
        $service->method('sync')->willReturn(new BannedCardsSyncResult(
            success: true,
            added: 0,
            removed: 0,
            unchanged: 0,
            warnings: ['Unknown set "New Set" for card "TestCard". Add it to SET_NAME_TO_CODE.'],
        ));

        $command = new BannedCardsSyncCommand($service);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Unknown set', $tester->getDisplay());
    }

    public function testCommandReturnsFailureOnError(): void
    {
        $service = $this->createStub(BannedCardsSyncService::class);
        $service->method('sync')->willReturn(
            BannedCardsSyncResult::failure('No banned cards found — the page structure may have changed.'),
        );

        $command = new BannedCardsSyncCommand($service);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('page structure may have changed', $tester->getDisplay());
    }
}
