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

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Provides sync status information for the admin dashboard and CLI.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
class TcgdexSyncStatusService
{
    private const string CACHE_KEY_LAST_COMPLETED = 'tcgdex_sync.last_completed_at';

    /** @var list<string> */
    private const array SYNC_QUEUE_NAMES = [
        'tcgdex_sync_series',
        'tcgdex_sync_serie',
        'tcgdex_sync_set',
        'tcgdex_sync_card',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Count pending messages across all TCGdex sync queues.
     */
    public function getQueueDepth(): int
    {
        $placeholders = implode(', ', array_fill(0, \count(self::SYNC_QUEUE_NAMES), '?'));
        $sql = \sprintf('SELECT COUNT(*) FROM messenger_messages WHERE queue_name IN (%s)', $placeholders);

        /** @var int|string $count */
        $count = $this->connection->fetchOne($sql, self::SYNC_QUEUE_NAMES);

        return (int) $count;
    }

    /**
     * Get the timestamp of the last completed sync, or null if never synced.
     */
    public function getLastSyncTimestamp(): ?\DateTimeImmutable
    {
        /** @var string|null $timestamp */
        $timestamp = $this->cache->get(self::CACHE_KEY_LAST_COMPLETED, static function (ItemInterface $item): null {
            $item->expiresAfter(86400 * 30);

            return null;
        });

        if (null === $timestamp) {
            return null;
        }

        try {
            return new \DateTimeImmutable($timestamp);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Record the current time as last completed sync.
     */
    public function recordSyncCompleted(): void
    {
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->cache->delete(self::CACHE_KEY_LAST_COMPLETED);
        $this->cache->get(self::CACHE_KEY_LAST_COMPLETED, static function (ItemInterface $item) use ($now): string {
            $item->expiresAfter(86400 * 30);

            return $now;
        });
    }
}
