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

namespace App\DTO;

use App\Entity\BannedCard;

/**
 * One functional banned card aggregated across all of its banned printings.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
final readonly class BannedCardGroup
{
    /**
     * @param list<BannedCard> $printings ordered by setCode + cardNumber
     */
    public function __construct(
        public string $cardName,
        public BannedCard $representative,
        public ?string $imageUrl,
        public array $printings,
        public ?\DateTimeImmutable $effectiveDate,
        public ?string $sourceUrl,
        public ?string $explanation,
    ) {
    }
}
