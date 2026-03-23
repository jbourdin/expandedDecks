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

    /**
     * Serialize grouped MinifiedCardView arrays to JSON for storage.
     *
     * @param array<string, list<self>> $grouped
     */
    public static function serializeGrouped(array $grouped): string
    {
        $data = [];

        foreach ($grouped as $type => $cards) {
            $data[$type] = array_map(static fn (self $card): array => [
                'n' => $card->cardName,
                'q' => $card->quantity,
                's' => $card->setCode,
                'c' => $card->cardNumber,
                't' => $card->cardType,
                'ts' => $card->trainerSubtype,
                'i' => $card->imageUrl,
                'ab' => $card->abilityNames,
                'at' => $card->attackNames,
            ], $cards);
        }

        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * Deserialize JSON back to grouped MinifiedCardView arrays.
     *
     * @return array<string, list<self>>
     */
    public static function deserializeGrouped(string $json): array
    {
        /** @var array<string, list<array{n: string, q: int, s: string, c: string, t: string, ts: ?string, i: ?string, ab?: string, at?: string}>> $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        $grouped = [];

        foreach ($data as $type => $cards) {
            $grouped[$type] = array_map(static fn (array $card): self => new self(
                $card['n'],
                $card['q'],
                $card['s'],
                $card['c'],
                $card['t'],
                $card['ts'],
                $card['i'],
                $card['ab'] ?? '',
                $card['at'] ?? '',
            ), $cards);
        }

        return $grouped;
    }
}
