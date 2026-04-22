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

use App\Entity\TcgdexCard;
use App\Entity\TcgdexSet;

/**
 * Pure data mapper that hydrates TcgdexCard entities from two different sources:
 * the NDJSON export (multilingual) and the TCGdex REST API (English-only).
 *
 * This service has no dependencies — it receives raw data arrays and a parent
 * TcgdexSet entity, and returns a fully populated TcgdexCard.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 */
class TcgdexCardHydrator
{
    /**
     * Hydrate a TcgdexCard from an NDJSON record (multilingual format).
     *
     * The record structure matches the output of the tcgdex-extract.ts script:
     * multilingual names/abilities/attacks as nested JSON objects.
     *
     * @param array<string, mixed> $record
     *
     * @throws \InvalidArgumentException if id or localId is missing
     */
    public function hydrateFromNdjsonRecord(array $record, TcgdexSet $set): TcgdexCard
    {
        $id = $this->extractString($record, 'id');
        $localId = $this->extractString($record, 'localId');

        if (null === $id || '' === $id || null === $localId || '' === $localId) {
            throw new \InvalidArgumentException('NDJSON record is missing required "id" or "localId" field.');
        }

        $card = new TcgdexCard($id, $set, $localId);

        /** @var array<string, mixed> $cardName */
        $cardName = $this->extractArray($record, 'name');
        /** @var list<array<string, mixed>> $abilities */
        $abilities = $this->extractArray($record, 'abilities');
        /** @var list<array<string, mixed>> $attacks */
        $attacks = $this->extractArray($record, 'attacks');

        $card->setName($cardName);
        $card->setCategory($this->extractString($record, 'category') ?? '');
        $card->setHp($this->extractInt($record, 'hp'));
        $card->setTrainerType($this->extractString($record, 'trainerType'));
        $card->setEnergyType($this->extractString($record, 'energyType'));
        $card->setRarity($this->extractString($record, 'rarity'));
        $card->setIsExpandedLegal((bool) ($record['isExpandedLegal'] ?? false));
        $card->setAbilities($abilities);
        $card->setAttacks($attacks);
        $card->setStage($this->extractString($record, 'stage'));
        $card->setRetreat($this->extractInt($record, 'retreat'));
        $card->setRegulationMark($this->extractString($record, 'regulationMark'));
        $card->setIllustrator($this->extractString($record, 'illustrator'));
        $card->setCardmarketProductId($this->extractInt($record, 'cardmarketProductId'));
        $card->setTcgplayerProductId($this->extractInt($record, 'tcgplayerProductId'));

        $effect = $record['effect'] ?? null;
        /** @var array<string, mixed>|null $effectArray */
        $effectArray = \is_array($effect) ? $effect : null;
        $card->setEffect($effectArray);

        $evolveFrom = $record['evolveFrom'] ?? null;
        /** @var array<string, mixed>|null $evolveFromArray */
        $evolveFromArray = \is_array($evolveFrom) ? $evolveFrom : null;
        $card->setEvolveFrom($evolveFromArray);

        $types = $record['types'] ?? [];
        /** @var list<string> $typesArray */
        $typesArray = \is_array($types) ? array_values(array_filter($types, 'is_string')) : [];
        $card->setTypes($typesArray);

        return $card;
    }

    /**
     * Hydrate a TcgdexCard from a TCGdex REST API v2 response (English-only).
     *
     * The API returns flat strings for names, abilities, and attacks (not multilingual).
     * This method wraps them into the multilingual format (["en" => "..."]) so the
     * entity's getLocalizedName('en') and generated name_en column work identically.
     *
     * @param array<string, mixed> $data raw JSON response from GET /cards/{id}
     *
     * @throws \InvalidArgumentException if id or localId is missing
     */
    public function hydrateFromApiResponse(array $data, TcgdexSet $set): TcgdexCard
    {
        $id = $this->extractString($data, 'id');
        $localId = $this->extractString($data, 'localId');

        if (null === $id || '' === $id || null === $localId || '' === $localId) {
            throw new \InvalidArgumentException('API response is missing required "id" or "localId" field.');
        }

        $card = new TcgdexCard($id, $set, $localId);
        $this->applyApiFields($card, $data);

        return $card;
    }

    /**
     * Update an existing TcgdexCard entity from a TCGdex API response.
     *
     * Used in "full" sync mode to refresh all card fields from the API.
     *
     * @param array<string, mixed> $data raw JSON response from GET /cards/{id}
     */
    public function updateFromApiResponse(TcgdexCard $card, array $data): void
    {
        $this->applyApiFields($card, $data);
    }

    /**
     * Apply all API response fields to a TcgdexCard entity (shared by create and update).
     *
     * @param array<string, mixed> $data
     */
    private function applyApiFields(TcgdexCard $card, array $data): void
    {
        $card->setName($this->wrapEnglish($this->extractString($data, 'name')) ?? []);
        $card->setCategory($this->extractString($data, 'category') ?? '');
        $card->setHp($this->extractInt($data, 'hp'));
        $card->setTrainerType($this->extractString($data, 'trainerType'));
        $card->setEnergyType($this->extractString($data, 'energyType'));
        $card->setRarity($this->extractString($data, 'rarity'));
        $card->setStage($this->extractString($data, 'stage'));
        $card->setRetreat($this->extractInt($data, 'retreat'));
        $card->setRegulationMark($this->extractString($data, 'regulationMark'));
        $card->setIllustrator($this->extractString($data, 'illustrator'));

        // Legal status
        /** @var array<string, mixed> $legal */
        $legal = isset($data['legal']) && \is_array($data['legal']) ? $data['legal'] : [];
        $card->setIsExpandedLegal(isset($legal['expanded']) && true === $legal['expanded']);

        // Abilities: API returns [{name: "...", effect: "...", type: "..."}] (flat strings)
        $card->setAbilities($this->wrapAbilitiesOrAttacks($data, 'abilities'));

        // Attacks: API returns [{name: "...", effect: "...", cost: [...], damage: "..."}] (flat strings)
        $card->setAttacks($this->wrapAbilitiesOrAttacks($data, 'attacks'));

        // Effect (trainer/energy text)
        $card->setEffect($this->wrapEnglish($this->extractString($data, 'effect')));

        // Evolve from
        $card->setEvolveFrom($this->wrapEnglish($this->extractString($data, 'evolveFrom')));

        // Types
        $types = $data['types'] ?? [];
        /** @var list<string> $typesArray */
        $typesArray = \is_array($types) ? array_values(array_filter($types, 'is_string')) : [];
        $card->setTypes($typesArray);

        // Image base URL
        $imageBase = $this->extractString($data, 'image');
        $card->setImageBaseUrl($imageBase);

        // Marketplace IDs from pricing data
        $this->hydrateMarketplaceIds($card, $data);
    }

    /**
     * Wrap a flat English string into multilingual format: "Foo" → ["en" => "Foo"].
     *
     * @return array<string, string>|null
     */
    private function wrapEnglish(?string $value): ?array
    {
        if (null === $value) {
            return null;
        }

        return ['en' => $value];
    }

    /**
     * Wrap API abilities or attacks (flat strings) into multilingual format.
     *
     * API format: [{name: "Ability", effect: "Does stuff", type: "Ability"}]
     * Entity format: [{name: {en: "Ability"}, effect: {en: "Does stuff"}, type: "Ability"}]
     *
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function wrapAbilitiesOrAttacks(array $data, string $key): array
    {
        if (!isset($data[$key]) || !\is_array($data[$key])) {
            return [];
        }

        $result = [];

        foreach ($data[$key] as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $result[] = $this->wrapSingleAbilityOrAttack($item);
        }

        return $result;
    }

    /**
     * Wrap a single API ability/attack item into multilingual format.
     *
     * @param array<string|int, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function wrapSingleAbilityOrAttack(array $item): array
    {
        /** @var array<string, mixed> $wrapped */
        $wrapped = [];

        // Wrap name and effect into multilingual format
        if (isset($item['name']) && \is_string($item['name'])) {
            $wrapped['name'] = ['en' => $item['name']];
        }

        if (isset($item['effect']) && \is_string($item['effect'])) {
            $wrapped['effect'] = ['en' => $item['effect']];
        }

        // Preserve non-string fields as-is (type, cost, damage)
        foreach ($item as $field => $value) {
            if ('name' === $field || 'effect' === $field) {
                continue;
            }

            if (\is_string($field)) {
                $wrapped[$field] = $value;
            }
        }

        return $wrapped;
    }

    /**
     * Extract marketplace IDs from the API pricing data.
     *
     * @param array<string, mixed> $data
     */
    private function hydrateMarketplaceIds(TcgdexCard $card, array $data): void
    {
        if (!isset($data['pricing']) || !\is_array($data['pricing'])) {
            return;
        }

        /** @var array<string, mixed> $pricing */
        $pricing = $data['pricing'];

        if (isset($pricing['cardmarket']) && \is_array($pricing['cardmarket'])) {
            $idProduct = $pricing['cardmarket']['idProduct'] ?? null;

            if (\is_int($idProduct)) {
                $card->setCardmarketProductId($idProduct);
            }
        }

        // TCGPlayer stores productId per variant — take the first one found
        if (isset($pricing['tcgplayer']) && \is_array($pricing['tcgplayer'])) {
            foreach ($pricing['tcgplayer'] as $value) {
                if (\is_array($value) && isset($value['productId']) && \is_int($value['productId'])) {
                    $card->setTcgplayerProductId($value['productId']);

                    break;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractString(array $data, string $key): ?string
    {
        return isset($data[$key]) && \is_string($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractInt(array $data, string $key): ?int
    {
        return isset($data[$key]) && \is_int($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string|int, mixed>
     */
    private function extractArray(array $data, string $key): array
    {
        return isset($data[$key]) && \is_array($data[$key]) ? $data[$key] : [];
    }
}
