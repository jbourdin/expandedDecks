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
 * Data extracted from a Pokemon event page.
 *
 * @see docs/features.md F3.18 — Sync from Pokemon event page
 */
readonly class PokemonEventData
{
    public function __construct(
        public ?string $name = null,
        public ?string $startDate = null,
        public ?string $location = null,
        public ?int $entryFeeAmount = null,
        public ?string $entryFeeCurrency = null,
        public ?string $format = null,
        public ?string $organizer = null,
        public ?string $registrationLink = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'startDate' => $this->startDate,
            'location' => $this->location,
            'entryFeeAmount' => $this->entryFeeAmount,
            'entryFeeCurrency' => $this->entryFeeCurrency,
            'format' => $this->format,
            'organizer' => $this->organizer,
            'registrationLink' => $this->registrationLink,
        ];
    }
}
