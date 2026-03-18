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

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client for the TCGdex REST API (v2).
 *
 * Translates PTCG set codes to TCGdex set IDs and fetches card data.
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class TcgdexApiClient
{
    private const string BASE_URL = 'https://api.tcgdex.net/v2/en';

    /**
     * PTCG codes that don't match TCGdex's abbreviation.official or tcgOnline.
     *
     * Promos use a "PR-XX" pattern in PTCG but a flat code in TCGdex.
     * SVI is used by PTCG Live for Scarlet & Violet base, but TCGdex
     * uses "SV" as the official abbreviation.
     */
    private const array STATIC_OVERRIDES = [
        'PR-SV' => 'svp',
        'PR-SW' => 'swshp',
        'PR-SM' => 'smp',
        'PR-XY' => 'xyp',
        'PR-BW' => 'bwp',
        'SVI' => 'sv01',
    ];

    /**
     * TCGdex prefixes card numbers with an era tag for promo sets.
     *
     * PTCG lists "Karen XYP 177" but TCGdex stores it as xyp-XY177.
     * SV promos use plain numbers (svp-001) and need no prefix.
     */
    private const array PROMO_CARD_NUMBER_PREFIXES = [
        'swshp' => 'SWSH',
        'smp' => 'SM',
        'xyp' => 'XY',
        'bwp' => 'BW',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Builds a PTCG code → TCGdex set ID mapping from set metadata.
     *
     * @return array<string, string>
     */
    public function getSetMapping(): array
    {
        /** @var array<string, string> $mapping */
        $mapping = $this->cache->get('tcgdex.set_mapping', function (ItemInterface $item): array {
            $item->expiresAfter(86400); // 24 hours

            return $this->buildSetMapping();
        });

        return $mapping;
    }

    /**
     * Reverse mapping: TCGdex set ID → PTCG code.
     *
     * @return array<string, string>
     */
    public function getReverseSetMapping(): array
    {
        /** @var array<string, string> $reverse */
        $reverse = $this->cache->get('tcgdex.reverse_set_mapping', function (ItemInterface $item): array {
            $item->expiresAfter(86400);

            return array_flip($this->getSetMapping());
        });

        return $reverse;
    }

    /**
     * Looks up a card by its PTCG set code and card number.
     */
    public function findCard(string $ptcgSetCode, string $cardNumber): ?TcgdexCard
    {
        $mapping = $this->getSetMapping();

        $normalizedSetCode = strtoupper($ptcgSetCode);
        $normalizedNumber = $cardNumber;

        // Trainer Gallery: "ASR-TG" → set "ASR", number "TG30"
        if (str_ends_with($normalizedSetCode, '-TG')) {
            $normalizedSetCode = substr($normalizedSetCode, 0, -3);
            $normalizedNumber = 'TG'.$cardNumber;
        }

        // Strip trailing letter suffixes from card numbers (e.g. "113a" → "113")
        $normalizedNumber = preg_replace('/[a-z]+$/i', '', $normalizedNumber) ?? $normalizedNumber;

        $setId = $mapping[$normalizedSetCode] ?? null;

        if (null === $setId) {
            return null;
        }

        // Promo sets use era-prefixed card numbers in TCGdex (e.g. XY177, SWSH001)
        $prefix = self::PROMO_CARD_NUMBER_PREFIXES[$setId] ?? null;
        $lookupNumber = null !== $prefix ? $prefix.$normalizedNumber : $normalizedNumber;

        // Try the resolved card number first
        $card = $this->fetchCard($setId, $lookupNumber);

        // If not found and number is < 3 digits, retry with zero-padded
        if (null === $card && \strlen($lookupNumber) < 3 && ctype_digit($lookupNumber)) {
            $paddedNumber = str_pad($lookupNumber, 3, '0', \STR_PAD_LEFT);
            $card = $this->fetchCard($setId, $paddedNumber);
        }

        return $card;
    }

    /**
     * Searches for a card by name across all printings and returns the first available image URL.
     *
     * Used as a fallback when the primary card entry has no image field.
     */
    public function findImageByName(string $cardName): ?string
    {
        $url = self::BASE_URL.'/cards?name='.urlencode($cardName);
        $response = $this->httpClient->request('GET', $url);

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        /** @var list<array<string, mixed>> $results */
        $results = $response->toArray();

        foreach ($results as $result) {
            // TCGdex name search is a "contains" match — filter to exact name
            $resultName = isset($result['name']) && \is_string($result['name']) ? $result['name'] : '';

            if ($resultName !== $cardName) {
                continue;
            }

            if (isset($result['image']) && \is_string($result['image'])) {
                return $result['image'].'/high.webp';
            }
        }

        return null;
    }

    /**
     * Fetches all set IDs, then each set's detail concurrently to read tcgOnline.
     *
     * The /sets list endpoint only returns summaries (no tcgOnline),
     * so we must hit /sets/{id} for each set to get the PTCG online code.
     *
     * @return array<string, string>
     */
    private function buildSetMapping(): array
    {
        // 1. Get all set IDs from the summary list
        $listResponse = $this->httpClient->request('GET', self::BASE_URL.'/sets');
        /** @var list<array{id: string}> $sets */
        $sets = $listResponse->toArray();

        // 2. Fire all detail requests concurrently
        /** @var array<string, ResponseInterface> $responses */
        $responses = [];

        foreach ($sets as $set) {
            $setId = $set['id'];
            $responses[$setId] = $this->httpClient->request('GET', self::BASE_URL.'/sets/'.$setId);
        }

        // 3. Collect PTCG codes as responses stream back
        //    tcgOnline is present on older sets (BW–SWSH era).
        //    abbreviation.official is present on all sets (SV era and earlier).
        $mapping = self::STATIC_OVERRIDES;

        foreach ($responses as $setId => $response) {
            /** @var array<string, mixed> $detail */
            $detail = $response->toArray();

            if (isset($detail['tcgOnline']) && \is_string($detail['tcgOnline']) && '' !== $detail['tcgOnline']) {
                $mapping[strtoupper($detail['tcgOnline'])] = $setId;
            }

            /** @var array<string, mixed> $abbreviation */
            $abbreviation = isset($detail['abbreviation']) && \is_array($detail['abbreviation']) ? $detail['abbreviation'] : [];

            if (isset($abbreviation['official']) && \is_string($abbreviation['official']) && '' !== $abbreviation['official']) {
                $code = strtoupper($abbreviation['official']);

                // Don't overwrite an existing mapping (tcgOnline takes precedence)
                if (!isset($mapping[$code])) {
                    $mapping[$code] = $setId;
                }
            }
        }

        return $mapping;
    }

    private function fetchCard(string $setId, string $cardNumber): ?TcgdexCard
    {
        return $this->fetchCardById(\sprintf('%s-%s', $setId, $cardNumber));
    }

    /**
     * Fetches all printings of a card by name from TCGdex, returning full card details.
     *
     * @see docs/features.md F6.10 — Card identity and printing model
     *
     * @return list<TcgdexCard>
     */
    public function findAllPrintingsByName(string $cardName): array
    {
        $url = self::BASE_URL.'/cards?name='.urlencode($cardName);
        $response = $this->httpClient->request('GET', $url);

        if (200 !== $response->getStatusCode()) {
            return [];
        }

        /** @var list<array<string, mixed>> $results */
        $results = $response->toArray();

        $cards = [];

        foreach ($results as $result) {
            $cardId = isset($result['id']) && \is_string($result['id']) ? $result['id'] : null;

            if (null === $cardId) {
                continue;
            }

            $card = $this->fetchCardById($cardId);

            if (null !== $card) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    private function fetchCardById(string $cardId): ?TcgdexCard
    {
        $url = \sprintf('%s/cards/%s', self::BASE_URL, $cardId);
        $response = $this->httpClient->request('GET', $url);

        if (404 === $response->getStatusCode()) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return $this->parseCardData($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseCardData(array $data): TcgdexCard
    {
        $imageBase = isset($data['image']) && \is_string($data['image']) ? $data['image'] : null;
        $imageUrl = null !== $imageBase ? $imageBase.'/high.webp' : null;

        /** @var array<string, mixed> $legal */
        $legal = isset($data['legal']) && \is_array($data['legal']) ? $data['legal'] : [];
        $isExpandedLegal = isset($legal['expanded']) && true === $legal['expanded'];

        $trainerType = isset($data['trainerType']) && \is_string($data['trainerType']) ? $data['trainerType'] : null;

        /** @var string $id */
        $id = $data['id'] ?? '';
        /** @var string $name */
        $name = $data['name'] ?? '';
        /** @var string $category */
        $category = $data['category'] ?? '';

        $hp = isset($data['hp']) && \is_int($data['hp']) ? $data['hp'] : null;
        $rarity = isset($data['rarity']) && \is_string($data['rarity']) ? $data['rarity'] : null;

        /** @var list<string> $attacks */
        $attacks = [];

        if (isset($data['attacks']) && \is_array($data['attacks'])) {
            foreach ($data['attacks'] as $attack) {
                if (\is_array($attack) && isset($attack['name']) && \is_string($attack['name'])) {
                    $attacks[] = $attack['name'];
                }
            }
        }

        $setReleaseDate = null;
        $setId = null;

        if (isset($data['set']) && \is_array($data['set'])) {
            if (isset($data['set']['releaseDate']) && \is_string($data['set']['releaseDate'])) {
                $setReleaseDate = $data['set']['releaseDate'];
            }

            if (isset($data['set']['id']) && \is_string($data['set']['id'])) {
                $setId = $data['set']['id'];
            }
        }

        // If set release date is missing, fetch it from the set endpoint
        if (null === $setReleaseDate && null !== $setId) {
            $setReleaseDate = $this->getSetReleaseDate($setId);
        }

        $localId = isset($data['localId']) && \is_string($data['localId']) ? $data['localId'] : null;

        // Resolve PTCG set code from TCGdex set ID via reverse mapping
        $setCode = null;

        if (null !== $setId) {
            $reverseMapping = $this->getReverseSetMapping();
            $setCode = $reverseMapping[$setId] ?? null;
        }

        // Parse pricing: prefer Cardmarket avg, fall back to TCGPlayer mid
        $priceInCents = $this->parsePriceInCents($data);

        return new TcgdexCard(
            id: $id,
            name: $name,
            category: $category,
            trainerType: $trainerType,
            imageUrl: $imageUrl,
            isExpandedLegal: $isExpandedLegal,
            hp: $hp,
            attacks: $attacks,
            rarity: $rarity,
            setReleaseDate: $setReleaseDate,
            setCode: $setCode,
            cardNumber: $localId,
            priceInCents: $priceInCents,
        );
    }

    /**
     * Fetch the release date for a set, cached.
     */
    private function getSetReleaseDate(string $setId): ?string
    {
        /** @var array<string, ?string> $cache */
        $cache = $this->cache->get('tcgdex.set_release_dates', static fn (): array => []);

        if (isset($cache[$setId])) {
            return $cache[$setId];
        }

        $url = self::BASE_URL.'/sets/'.$setId;
        $response = $this->httpClient->request('GET', $url);

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        /** @var array<string, mixed> $detail */
        $detail = $response->toArray();

        return isset($detail['releaseDate']) && \is_string($detail['releaseDate']) ? $detail['releaseDate'] : null;
    }

    /**
     * Extract the average price in euro cents from TCGdex pricing data.
     *
     * Prefers Cardmarket avg (EUR), falls back to TCGPlayer normal midPrice (USD, approximate).
     *
     * @param array<string, mixed> $data
     */
    private function parsePriceInCents(array $data): ?int
    {
        if (!isset($data['pricing']) || !\is_array($data['pricing'])) {
            return null;
        }

        /** @var array<string, mixed> $pricing */
        $pricing = $data['pricing'];

        // Cardmarket (EUR)
        if (isset($pricing['cardmarket']) && \is_array($pricing['cardmarket'])) {
            $avg = $pricing['cardmarket']['avg'] ?? null;

            if (\is_float($avg) || \is_int($avg)) {
                return (int) round($avg * 100);
            }
        }

        // TCGPlayer fallback (USD, approximate)
        if (isset($pricing['tcgplayer']) && \is_array($pricing['tcgplayer'])) {
            $normal = $pricing['tcgplayer']['normal'] ?? null;

            if (\is_array($normal) && isset($normal['midPrice']) && (\is_float($normal['midPrice']) || \is_int($normal['midPrice']))) {
                return (int) round($normal['midPrice'] * 100);
            }
        }

        return null;
    }
}
