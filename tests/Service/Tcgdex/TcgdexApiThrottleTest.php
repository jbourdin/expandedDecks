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

namespace App\Tests\Service\Tcgdex;

use App\Service\Tcgdex\TcgdexApiThrottle;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
class TcgdexApiThrottleTest extends TestCase
{
    private ArrayAdapter $cache;
    private LoggerInterface $logger;

    /** @var list<int> Microseconds slept during test, captured by the test double */
    private array $sleeps = [];

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->sleeps = [];
    }

    public function testWaitIfNeededEnforcesMinimumDelay(): void
    {
        $throttle = $this->createThrottle(minimumDelayMilliseconds: 100);

        // First call: no delay (no previous call)
        $throttle->waitIfNeeded();
        self::assertSame([], $this->sleeps);

        // Second call immediately: should sleep for ~100ms
        $throttle->waitIfNeeded();
        self::assertCount(1, $this->sleeps);
        self::assertGreaterThan(0, $this->sleeps[0]);
    }

    public function testReportSuccessResetsFailureCounter(): void
    {
        $throttle = $this->createThrottle();

        $throttle->reportFailure();
        $throttle->reportFailure();
        self::assertSame(2, $throttle->getConsecutiveFailures());

        $throttle->reportSuccess();
        self::assertSame(0, $throttle->getConsecutiveFailures());
    }

    public function testReportFailureIncrementsCounter(): void
    {
        $throttle = $this->createThrottle();

        self::assertSame(0, $throttle->getConsecutiveFailures());

        $throttle->reportFailure();
        self::assertSame(1, $throttle->getConsecutiveFailures());

        $throttle->reportFailure();
        self::assertSame(2, $throttle->getConsecutiveFailures());
    }

    public function testCooldownActivatesAfterThreshold(): void
    {
        $throttle = $this->createThrottle(failureThreshold: 3);

        $throttle->reportFailure();
        $throttle->reportFailure();
        self::assertFalse($throttle->isInCooldown());

        // Third failure triggers cooldown
        $throttle->reportFailure();
        self::assertTrue($throttle->isInCooldown());
    }

    public function testCooldownNotActiveBeforeThreshold(): void
    {
        $throttle = $this->createThrottle(failureThreshold: 5);

        $throttle->reportFailure();
        $throttle->reportFailure();
        $throttle->reportFailure();
        $throttle->reportFailure();
        self::assertFalse($throttle->isInCooldown());
    }

    public function testWaitIfNeededSleepsDuringCooldown(): void
    {
        $throttle = $this->createThrottle(failureThreshold: 1, cooldownDurationSeconds: 60);

        // Trigger cooldown
        $throttle->reportFailure();
        self::assertTrue($throttle->isInCooldown());

        // waitIfNeeded should sleep
        $throttle->waitIfNeeded();
        self::assertNotEmpty($this->sleeps);
        // First sleep should be roughly 60s worth of microseconds
        self::assertGreaterThan(50_000_000, $this->sleeps[0]);
    }

    public function testSuccessAfterCooldownResetsBoth(): void
    {
        $throttle = $this->createThrottle(failureThreshold: 2, cooldownDurationSeconds: 0);

        $throttle->reportFailure();
        $throttle->reportFailure();

        // Cooldown duration is 0 so it should already have expired
        $throttle->reportSuccess();
        self::assertSame(0, $throttle->getConsecutiveFailures());
        self::assertFalse($throttle->isInCooldown());
    }

    public function testIsInCooldownReturnsFalseWhenNoCooldown(): void
    {
        $throttle = $this->createThrottle();
        self::assertFalse($throttle->isInCooldown());
    }

    public function testGetConsecutiveFailuresReturnsZeroInitially(): void
    {
        $throttle = $this->createThrottle();
        self::assertSame(0, $throttle->getConsecutiveFailures());
    }

    public function testMultipleFailuresBeyondThresholdKeepCooldown(): void
    {
        $throttle = $this->createThrottle(failureThreshold: 2, cooldownDurationSeconds: 300);

        $throttle->reportFailure();
        $throttle->reportFailure();
        self::assertTrue($throttle->isInCooldown());
        self::assertSame(2, $throttle->getConsecutiveFailures());

        // Additional failures keep counting and stay in cooldown
        $throttle->reportFailure();
        self::assertTrue($throttle->isInCooldown());
        self::assertSame(3, $throttle->getConsecutiveFailures());
    }

    private function createThrottle(
        int $minimumDelayMilliseconds = 200,
        int $failureThreshold = 3,
        int $cooldownDurationSeconds = 300,
    ): TcgdexApiThrottle {
        $test = $this;

        return new class($this->cache, $this->logger, $minimumDelayMilliseconds, $failureThreshold, $cooldownDurationSeconds, $test) extends TcgdexApiThrottle {
            /** @var TcgdexApiThrottleTest */
            private object $testInstance;

            public function __construct(
                \Symfony\Contracts\Cache\CacheInterface $cache,
                LoggerInterface $logger,
                int $minimumDelayMilliseconds,
                int $failureThreshold,
                int $cooldownDurationSeconds,
                object $testInstance,
            ) {
                parent::__construct($cache, $logger, $minimumDelayMilliseconds, $failureThreshold, $cooldownDurationSeconds);
                $this->testInstance = $testInstance;
            }

            protected function sleepMicroseconds(int $microseconds): void
            {
                $this->testInstance->recordSleep($microseconds);
            }
        };
    }

    /**
     * @internal called by test double to record sleep calls without actually sleeping
     */
    public function recordSleep(int $microseconds): void
    {
        $this->sleeps[] = $microseconds;
    }
}
