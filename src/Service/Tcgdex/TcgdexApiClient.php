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
     * Looks up a card by its PTCG set code and card number.
     */
    public function findCard(string $ptcgSetCode, string $cardNumber): ?TcgdexCard
    {
        $mapping = $this->getSetMapping();
        $setId = $mapping[strtoupper($ptcgSetCode)] ?? null;

        if (null === $setId) {
            return null;
        }

        // Try original card number first
        $card = $this->fetchCard($setId, $cardNumber);

        // If not found and number is < 3 digits, retry with zero-padded
        if (null === $card && \strlen($cardNumber) < 3 && ctype_digit($cardNumber)) {
            $paddedNumber = str_pad($cardNumber, 3, '0', \STR_PAD_LEFT);
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
        $url = \sprintf('%s/cards/%s-%s', self::BASE_URL, $setId, $cardNumber);

        $response = $this->httpClient->request('GET', $url);

        if (404 === $response->getStatusCode()) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

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

        return new TcgdexCard(
            id: $id,
            name: $name,
            category: $category,
            trainerType: $trainerType,
            imageUrl: $imageUrl,
            isExpandedLegal: $isExpandedLegal,
        );
    }
}
