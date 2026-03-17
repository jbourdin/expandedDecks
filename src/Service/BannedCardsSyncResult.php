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

namespace App\Service;

/**
 * @see docs/features.md F6.5 — Banned card list management
 */
readonly class BannedCardsSyncResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public bool $success,
        public int $added,
        public int $removed,
        public int $unchanged,
        public array $warnings = [],
        public ?string $error = null,
    ) {
    }

    public static function failure(string $error): self
    {
        return new self(
            success: false,
            added: 0,
            removed: 0,
            unchanged: 0,
            error: $error,
        );
    }
}
