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

namespace App\Service\Tcgdex;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Rate limiter for TCGdex API calls during incremental sync.
 *
 * Enforces a minimum delay between API calls and enters a cooldown period
 * after consecutive failures. State is stored in a filesystem-backed cache
 * pool shared across all worker processes.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
class TcgdexApiThrottle
{
    private const string CACHE_KEY_LAST_CALL = 'tcgdex_sync.last_call_at';
    private const string CACHE_KEY_FAILURES = 'tcgdex_sync.consecutive_failures';
    private const string CACHE_KEY_COOLDOWN = 'tcgdex_sync.cooldown_until';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly int $minimumDelayMilliseconds = 200,
        private readonly int $failureThreshold = 3,
        private readonly int $cooldownDurationSeconds = 300,
    ) {
    }

    /**
     * Wait until the API can be called again.
     *
     * If a cooldown is active, sleeps until it expires. Then enforces the
     * minimum delay since the last API call. Updates the last-call timestamp.
     */
    public function waitIfNeeded(): void
    {
        // Wait out cooldown if active
        $cooldownUntil = $this->getCacheFloat(self::CACHE_KEY_COOLDOWN);

        if (null !== $cooldownUntil) {
            $remaining = $cooldownUntil - microtime(true);

            if ($remaining > 0) {
                $this->logger->info('TCGdex throttle: cooldown active, sleeping {seconds}s', [
                    'seconds' => round($remaining, 1),
                ]);
                $this->sleepMicroseconds((int) ($remaining * 1_000_000));
            }
        }

        // Enforce minimum delay since last call
        $lastCallAt = $this->getCacheFloat(self::CACHE_KEY_LAST_CALL);

        if (null !== $lastCallAt) {
            $elapsed = microtime(true) - $lastCallAt;
            $minimumDelay = $this->minimumDelayMilliseconds / 1000.0;
            $remaining = $minimumDelay - $elapsed;

            if ($remaining > 0) {
                $this->sleepMicroseconds((int) ($remaining * 1_000_000));
            }
        }

        // Record this call's timestamp
        $this->setCacheFloat(self::CACHE_KEY_LAST_CALL, microtime(true));
    }

    /**
     * Report a successful API call. Resets the consecutive failure counter.
     */
    public function reportSuccess(): void
    {
        $this->setCacheInt(self::CACHE_KEY_FAILURES, 0);
    }

    /**
     * Report a failed API call. Increments the failure counter and activates
     * cooldown if the threshold is reached.
     */
    public function reportFailure(): void
    {
        $failures = $this->getConsecutiveFailures() + 1;
        $this->setCacheInt(self::CACHE_KEY_FAILURES, $failures);

        if ($failures >= $this->failureThreshold) {
            $cooldownUntil = microtime(true) + $this->cooldownDurationSeconds;
            $this->setCacheFloat(self::CACHE_KEY_COOLDOWN, $cooldownUntil);

            $this->logger->warning('TCGdex throttle: {failures} consecutive failures, entering {seconds}s cooldown', [
                'failures' => $failures,
                'seconds' => $this->cooldownDurationSeconds,
            ]);
        }
    }

    /**
     * Whether the throttle is currently in cooldown mode.
     */
    public function isInCooldown(): bool
    {
        $cooldownUntil = $this->getCacheFloat(self::CACHE_KEY_COOLDOWN);

        return null !== $cooldownUntil && $cooldownUntil > microtime(true);
    }

    /**
     * Number of consecutive API failures since the last success.
     */
    public function getConsecutiveFailures(): int
    {
        return $this->getCacheInt(self::CACHE_KEY_FAILURES);
    }

    /**
     * @internal visible for testing — allows subclass to override sleep behavior
     */
    protected function sleepMicroseconds(int $microseconds): void
    {
        usleep($microseconds);
    }

    private function getCacheFloat(string $key): ?float
    {
        /** @var float|null $value */
        $value = $this->cache->get($key, static function (ItemInterface $item): null {
            $item->expiresAfter(600);

            return null;
        });

        return $value;
    }

    private function setCacheFloat(string $key, float $value): void
    {
        $this->cache->delete($key);
        $this->cache->get($key, static function (ItemInterface $item) use ($value): float {
            $item->expiresAfter(600);

            return $value;
        });
    }

    private function getCacheInt(string $key): int
    {
        /** @var int|null $value */
        $value = $this->cache->get($key, static function (ItemInterface $item): int {
            $item->expiresAfter(600);

            return 0;
        });

        return $value ?? 0;
    }

    private function setCacheInt(string $key, int $value): void
    {
        $this->cache->delete($key);
        $this->cache->get($key, static function (ItemInterface $item) use ($value): int {
            $item->expiresAfter(600);

            return $value;
        });
    }
}
