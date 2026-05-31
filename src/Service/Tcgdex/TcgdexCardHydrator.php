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
 * the NDJSON export (multilingual) and the TCGdex REST API (one locale per call).
 *
 * This service has no dependencies — it receives raw data arrays and a parent
 * TcgdexSet entity, and returns a fully populated TcgdexCard.
 *
 * The API hydration is locale-aware: a base call (English) sets the
 * locale-independent fields and the English text, then {@see mergeLocaleFields()}
 * folds each additional locale into the JSON columns without disturbing the others.
 *
 * @see docs/features.md F6.13 — Incremental TCGdex database sync
 * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
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
     * Hydrate a new TcgdexCard from a TCGdex REST API v2 response (base locale).
     *
     * Applies the locale-independent fields and folds the response's text into the
     * given locale (English by default — the base discovery locale). Additional
     * locales are layered on afterwards with {@see mergeLocaleFields()}.
     *
     * @param array<string, mixed> $data   raw JSON response from GET /{locale}/cards/{id}
     * @param string               $locale locale the response was fetched in
     *
     * @throws \InvalidArgumentException if id or localId is missing
     */
    public function hydrateFromApiResponse(array $data, TcgdexSet $set, string $locale = 'en'): TcgdexCard
    {
        $id = $this->extractString($data, 'id');
        $localId = $this->extractString($data, 'localId');

        if (null === $id || '' === $id || null === $localId || '' === $localId) {
            throw new \InvalidArgumentException('API response is missing required "id" or "localId" field.');
        }

        $card = new TcgdexCard($id, $set, $localId);
        $this->applyLocaleIndependentFields($card, $data);
        $this->mergeLocaleFields($card, $locale, $data);

        return $card;
    }

    /**
     * Refresh an existing TcgdexCard from a TCGdex API response (base locale).
     *
     * Re-applies the locale-independent fields and merges the response's text into
     * the given locale, preserving every other locale already stored.
     *
     * @param array<string, mixed> $data   raw JSON response from GET /{locale}/cards/{id}
     * @param string               $locale locale the response was fetched in
     */
    public function updateFromApiResponse(TcgdexCard $card, array $data, string $locale = 'en'): void
    {
        $this->applyLocaleIndependentFields($card, $data);
        $this->mergeLocaleFields($card, $locale, $data);
    }

    /**
     * Fold a single-locale API response into the card's multilingual JSON columns.
     *
     * Writes only the given locale's key on each translatable field (name, effect,
     * evolveFrom, ability/attack name + effect), leaving the other locales untouched.
     * Abilities and attacks are matched by list position — the per-locale endpoints
     * return the same entries in the same order, just translated.
     *
     * Also captures the response's "updated" timestamp as the freshness baseline.
     *
     * @param array<string, mixed> $data raw JSON response from GET /{locale}/cards/{id}
     *
     * @see docs/features.md F6.17 — TCGdex multi-locale sync (gap-fill + force update)
     */
    public function mergeLocaleFields(TcgdexCard $card, string $locale, array $data): void
    {
        $card->setName($this->mergeLocalizedString($card->getName(), $locale, $this->extractString($data, 'name')) ?? []);
        $card->setEffect($this->mergeLocalizedString($card->getEffect(), $locale, $this->extractString($data, 'effect')));
        $card->setEvolveFrom($this->mergeLocalizedString($card->getEvolveFrom(), $locale, $this->extractString($data, 'evolveFrom')));

        $card->setAbilities($this->mergeAbilitiesOrAttacks($card->getAbilities(), $data, 'abilities', $locale));
        $card->setAttacks($this->mergeAbilitiesOrAttacks($card->getAttacks(), $data, 'attacks', $locale));

        $this->applyUpdatedTimestamp($card, $data);
    }

    /**
     * Apply every field that does not vary by locale (so any locale's payload is equivalent).
     *
     * @param array<string, mixed> $data
     */
    private function applyLocaleIndependentFields(TcgdexCard $card, array $data): void
    {
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

        // Types
        $types = $data['types'] ?? [];
        /** @var list<string> $typesArray */
        $typesArray = \is_array($types) ? array_values(array_filter($types, 'is_string')) : [];
        $card->setTypes($typesArray);

        // Image base URL
        $card->setImageBaseUrl($this->extractString($data, 'image'));

        // Marketplace IDs from pricing data
        $this->hydrateMarketplaceIds($card, $data);
    }

    /**
     * Merge a flat string into one locale key of a multilingual map, keeping the others.
     *
     * Returns null only when there is nothing to store (no existing map, no new value),
     * so a trainer card with no effect stays null rather than becoming an empty map.
     *
     * @param array<string, mixed>|null $existing
     *
     * @return array<string, mixed>|null
     */
    private function mergeLocalizedString(?array $existing, string $locale, ?string $value): ?array
    {
        if (null === $value) {
            return $existing;
        }

        $existing ??= [];
        $existing[$locale] = $value;

        return $existing;
    }

    /**
     * Merge a single-locale abilities/attacks payload into the stored multilingual list.
     *
     * API format: [{name: "Ability", effect: "Does stuff", type: "Ability"}]
     * Entity format: [{name: {en: "Ability", fr: "Capacité"}, effect: {...}, type: "Ability"}]
     *
     * Entries are matched by position; the locale's name/effect are written onto the
     * matching entry while non-text fields (type, cost, damage) are refreshed from the
     * payload. Other locales already present on the entry are preserved.
     *
     * @param list<array<string, mixed>> $existing
     * @param array<string, mixed>       $data
     *
     * @return list<array<string, mixed>>
     */
    private function mergeAbilitiesOrAttacks(array $existing, array $data, string $key, string $locale): array
    {
        if (!isset($data[$key]) || !\is_array($data[$key])) {
            return $existing;
        }

        foreach (array_values($data[$key]) as $index => $item) {
            if (!\is_array($item)) {
                continue;
            }

            /** @var array<string, mixed> $entry */
            $entry = $existing[$index] ?? [];

            if (isset($item['name']) && \is_string($item['name'])) {
                /** @var array<string, mixed> $name */
                $name = \is_array($entry['name'] ?? null) ? $entry['name'] : [];
                $name[$locale] = $item['name'];
                $entry['name'] = $name;
            }

            if (isset($item['effect']) && \is_string($item['effect'])) {
                /** @var array<string, mixed> $effect */
                $effect = \is_array($entry['effect'] ?? null) ? $entry['effect'] : [];
                $effect[$locale] = $item['effect'];
                $entry['effect'] = $effect;
            }

            // Refresh non-text fields as-is (type, cost, damage) — identical across locales.
            foreach ($item as $field => $value) {
                if ('name' === $field || 'effect' === $field) {
                    continue;
                }

                if (\is_string($field)) {
                    $entry[$field] = $value;
                }
            }

            $existing[$index] = $entry;
        }

        return $existing;
    }

    /**
     * Capture the TCGdex per-card "updated" timestamp as the freshness baseline.
     *
     * @param array<string, mixed> $data
     */
    private function applyUpdatedTimestamp(TcgdexCard $card, array $data): void
    {
        $updated = $this->extractString($data, 'updated');

        if (null === $updated) {
            return;
        }

        try {
            $card->setTcgdexUpdatedAt(new \DateTimeImmutable($updated));
        } catch (\Exception) {
            // Invalid timestamp — leave the baseline unchanged.
        }
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
