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

namespace App\Service\DeckList;

/**
 * Lightweight DTO for displaying a minified card in the deck table view.
 *
 * Mirrors DeckCard's interface for template compatibility.
 *
 * @see docs/features.md F6.8 — Minified deck list export
 */
readonly class MinifiedCardView
{
    public function __construct(
        private string $cardName,
        private int $quantity,
        private string $setCode,
        private string $cardNumber,
        private string $cardType,
        private ?string $trainerSubtype,
        private ?string $imageUrl,
        private string $abilityNames = '',
        private string $attackNames = '',
    ) {
    }

    public function getCardName(): string
    {
        return $this->cardName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getSetCode(): string
    {
        return $this->setCode;
    }

    public function getCardNumber(): string
    {
        return $this->cardNumber;
    }

    public function getCardType(): string
    {
        return $this->cardType;
    }

    public function getTrainerSubtype(): ?string
    {
        return $this->trainerSubtype;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getAbilityNames(): string
    {
        return $this->abilityNames;
    }

    public function getAttackNames(): string
    {
        return $this->attackNames;
    }
}
