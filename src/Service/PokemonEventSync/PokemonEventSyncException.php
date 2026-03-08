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

namespace App\Service\PokemonEventSync;

/**
 * @see docs/features.md F3.18 — Sync from Pokemon event page
 */
class PokemonEventSyncException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 502,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function emptyId(): self
    {
        return new self('Tournament ID is required.', 'missing_id', 400);
    }

    public static function invalidId(string $id): self
    {
        return new self(\sprintf('Invalid tournament ID: "%s".', $id), 'invalid_id', 400);
    }

    public static function notFound(string $id): self
    {
        return new self(\sprintf('Event page not found for tournament ID "%s".', $id), 'not_found', 404);
    }

    public static function fetchFailed(string $id, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Failed to fetch event page for tournament ID "%s".', $id), 'fetch_failed', 502, $previous);
    }

    public static function noJsonLd(string $id): self
    {
        return new self(\sprintf('No JSON-LD data found on event page for tournament ID "%s".', $id), 'no_json_ld', 502);
    }
}
