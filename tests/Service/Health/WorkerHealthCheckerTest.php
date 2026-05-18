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

namespace App\Tests\Service\Health;

use App\Service\Health\WorkerHealthChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F14.8 — Worker liveness check on /health/ready
 */
final class WorkerHealthCheckerTest extends TestCase
{
    /**
     * @param array{exitCode: int|null, output: string, errorMessage: string|null} $runnerResult
     */
    private static function makeChecker(string $appEnv, array $runnerResult): WorkerHealthChecker
    {
        return new WorkerHealthChecker($appEnv, static fn (): array => $runnerResult);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function healthyStateProvider(): iterable
    {
        yield 'RUNNING' => [
            "worker-messenger                 RUNNING   pid 1234, uptime 0:01:23\n",
            'RUNNING',
        ];
        yield 'STARTING (cold start or post-time-limit respawn)' => [
            "worker-messenger                 STARTING\n",
            'STARTING',
        ];
    }

    #[DataProvider('healthyStateProvider')]
    public function testHealthyStatesReturnOk(string $output, string $expectedState): void
    {
        $checker = self::makeChecker('prod', ['exitCode' => 0, 'output' => $output, 'errorMessage' => null]);

        $result = $checker->check();

        self::assertSame('ok', $result['status']);
        self::assertSame($expectedState, $result['state']);
        self::assertArrayNotHasKey('error', $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unhealthyStateProvider(): iterable
    {
        yield 'STOPPED (worker has not been started)' => ['worker-messenger                 STOPPED   Not started'];
        yield 'BACKOFF (crash-looping on bootstrap)' => ['worker-messenger                 BACKOFF   Exited too quickly'];
        yield 'EXITED (process ran to completion without autorestart)' => ['worker-messenger                 EXITED    Apr 12 10:00 AM'];
        yield 'FATAL (autorestart gave up after startretries)' => ['worker-messenger                 FATAL     Exited too quickly (process log may have details)'];
        yield 'STOPPING (pod shutdown — readiness must drop)' => ['worker-messenger                 STOPPING'];
        yield 'UNKNOWN (supervisor RPC error)' => ['worker-messenger                 UNKNOWN'];
    }

    #[DataProvider('unhealthyStateProvider')]
    public function testUnhealthyStateFailsInProd(string $output): void
    {
        $checker = self::makeChecker('prod', ['exitCode' => 0, 'output' => $output, 'errorMessage' => null]);

        $result = $checker->check();

        self::assertSame('fail', $result['status']);
        self::assertArrayHasKey('state', $result);
        self::assertArrayHasKey('error', $result);
    }

    #[DataProvider('unhealthyStateProvider')]
    public function testUnhealthyStateSkippedOutsideProd(string $output): void
    {
        $checker = self::makeChecker('dev', ['exitCode' => 0, 'output' => $output, 'errorMessage' => null]);

        $result = $checker->check();

        self::assertSame('skipped', $result['status']);
    }

    public function testSupervisorctlUnavailableFailsInProd(): void
    {
        $checker = self::makeChecker('prod', [
            'exitCode' => null,
            'output' => '',
            'errorMessage' => 'Executable not found',
        ]);

        $result = $checker->check();

        self::assertSame('fail', $result['status']);
        self::assertStringContainsString('supervisorctl unavailable', $result['error']);
    }

    public function testSupervisorctlUnavailableSkippedInTest(): void
    {
        $checker = self::makeChecker('test', [
            'exitCode' => null,
            'output' => '',
            'errorMessage' => 'Executable not found',
        ]);

        $result = $checker->check();

        self::assertSame('skipped', $result['status']);
    }

    public function testUnparseableOutputFails(): void
    {
        $checker = self::makeChecker('prod', [
            'exitCode' => 1,
            'output' => 'worker-messenger: ERROR (no such process)',
            'errorMessage' => null,
        ]);

        $result = $checker->check();

        self::assertSame('fail', $result['status']);
        self::assertStringContainsString('cannot parse supervisorctl output', $result['error']);
    }

    public function testParseStateExtractsFirstUppercaseToken(): void
    {
        $output = <<<TXT
            meilisearch                      RUNNING   pid 11, uptime 0:00:42
            frankenphp                       RUNNING   pid 12, uptime 0:00:42
            worker-messenger                 STARTING
            TXT;

        self::assertSame('STARTING', WorkerHealthChecker::parseState($output));
    }

    public function testParseStateReturnsNullForEmptyOutput(): void
    {
        self::assertNull(WorkerHealthChecker::parseState(''));
    }
}
