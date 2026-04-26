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

namespace App\Enum;

/**
 * @see docs/features.md F2.23 — Standard format personal decks
 */
enum DeckFormat: string
{
    case Expanded = 'expanded';
    case Standard = 'standard';

    /**
     * Whether decks of this format appear in search indexes and public catalog.
     */
    public function isSearchable(): bool
    {
        return self::Expanded === $this;
    }

    /**
     * Whether decks of this format can be borrowed or lent.
     */
    public function isLendable(): bool
    {
        return self::Expanded === $this;
    }

    /**
     * Whether decks of this format can be registered for events.
     */
    public function isEventRegisterable(): bool
    {
        return self::Expanded === $this;
    }
}
