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

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches and parses event data from the official Pokemon event page.
 *
 * Extracts structured data from JSON-LD markup and HTML content.
 *
 * @see docs/features.md F3.18 — Sync from Pokemon event page
 */
class PokemonEventSyncService
{
    private const string BASE_URL = 'https://www.pokemon.com/us/play-pokemon/pokemon-events';

    /**
     * Maps event type keywords to TournamentStructure enum values.
     *
     * @var array<string, string>
     */
    private const array STRUCTURE_MAP = [
        'League Cup' => 'swiss_top_cut',
        'League Challenge' => 'swiss',
        'League' => 'swiss',
        'Prerelease' => 'swiss',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Fetches event data from the Pokemon event page, cached for 15 minutes.
     *
     * @throws PokemonEventSyncException
     */
    public function fetchEventData(string $tournamentId): PokemonEventData
    {
        $tournamentId = trim($tournamentId);

        if ('' === $tournamentId) {
            throw PokemonEventSyncException::emptyId();
        }

        if (!preg_match('/^[a-zA-Z0-9-]+$/', $tournamentId)) {
            throw PokemonEventSyncException::invalidId($tournamentId);
        }

        $cacheKey = 'pokemon_event.'.md5($tournamentId);

        /** @var PokemonEventData $data */
        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($tournamentId): PokemonEventData {
            $item->expiresAfter(900); // 15 minutes

            return $this->doFetch($tournamentId);
        });

        return $data;
    }

    /**
     * @throws PokemonEventSyncException
     */
    private function doFetch(string $tournamentId): PokemonEventData
    {
        $url = self::BASE_URL.'/'.$tournamentId.'/';
        $html = $this->fetchHtml($url, $tournamentId);

        $jsonLd = $this->extractJsonLd($html, $tournamentId);
        $structure = $this->parseStructureFromHtml($html, $jsonLd);
        $organizer = $this->parseOrganizerFromHtml($html);

        return $this->buildEventData($jsonLd, $structure, $organizer);
    }

    /**
     * @throws PokemonEventSyncException
     */
    private function fetchHtml(string $url, string $tournamentId): string
    {
        try {
            $response = $this->httpClient->request('GET', $url);
            $statusCode = $response->getStatusCode();
        } catch (\Throwable $e) {
            throw PokemonEventSyncException::fetchFailed($tournamentId, $e);
        }

        if (404 === $statusCode) {
            throw PokemonEventSyncException::notFound($tournamentId);
        }

        if ($statusCode >= 400) {
            throw PokemonEventSyncException::fetchFailed($tournamentId);
        }

        try {
            return $response->getContent();
        } catch (\Throwable $e) {
            throw PokemonEventSyncException::fetchFailed($tournamentId, $e);
        }
    }

    /**
     * Extracts JSON-LD schema.org/Event data from the HTML page.
     *
     * @return array<string, mixed>
     *
     * @throws PokemonEventSyncException
     */
    private function extractJsonLd(string $html, string $tournamentId): array
    {
        if (!preg_match('/<script[^>]*application\/ld\+json[^>]*>(.*?)<\/script>/si', $html, $matches)) {
            throw PokemonEventSyncException::noJsonLd($tournamentId);
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($matches[1], true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw PokemonEventSyncException::noJsonLd($tournamentId);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $jsonLd
     */
    private function parseStructureFromHtml(string $html, array $jsonLd): string
    {
        $name = isset($jsonLd['name']) && \is_string($jsonLd['name']) ? $jsonLd['name'] : '';
        $searchText = $name.' '.$html;

        foreach (self::STRUCTURE_MAP as $keyword => $structure) {
            if (str_contains($searchText, $keyword)) {
                return $structure;
            }
        }

        return 'swiss';
    }

    private function parseOrganizerFromHtml(string $html): ?string
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML($html, \LIBXML_NOERROR);
        $xpath = new \DOMXPath($doc);

        $nodes = $xpath->query("//*[contains(@class, 'organizer')]//text()");

        if (false !== $nodes && $nodes->length > 0) {
            $text = '';
            foreach ($nodes as $node) {
                $text .= $node->nodeValue;
            }
            $text = trim($text);

            if ('' !== $text) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $jsonLd
     */
    private function buildEventData(
        array $jsonLd,
        ?string $structure,
        ?string $organizer,
    ): PokemonEventData {
        $name = isset($jsonLd['name']) && \is_string($jsonLd['name']) ? self::decodeUnicode($jsonLd['name']) : null;
        $startDate = $this->extractStartDate($jsonLd);
        $location = $this->extractLocation($jsonLd);
        [$feeAmount, $feeCurrency] = $this->extractEntryFee($jsonLd);

        return new PokemonEventData(
            name: $name,
            startDate: $startDate,
            location: $location,
            entryFeeAmount: $feeAmount,
            entryFeeCurrency: $feeCurrency,
            tournamentStructure: $structure,
            organizer: null !== $organizer ? self::decodeUnicode($organizer) : null,
        );
    }

    /**
     * @param array<string, mixed> $jsonLd
     */
    private function extractStartDate(array $jsonLd): ?string
    {
        if (!isset($jsonLd['startDate']) || !\is_string($jsonLd['startDate'])) {
            return null;
        }

        // Return date portion only (YYYY-MM-DD)
        $date = $jsonLd['startDate'];

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date, $matches)) {
            return $matches[0];
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $jsonLd
     */
    private function extractLocation(array $jsonLd): ?string
    {
        if (!isset($jsonLd['location']) || !\is_array($jsonLd['location'])) {
            return null;
        }

        /** @var array<string, mixed> $loc */
        $loc = $jsonLd['location'];
        $parts = [];

        if (isset($loc['name']) && \is_string($loc['name']) && '' !== $loc['name']) {
            $parts[] = $loc['name'];
        }

        if (isset($loc['address']) && \is_array($loc['address'])) {
            /** @var array<string, mixed> $addr */
            $addr = $loc['address'];

            $addrParts = [];
            foreach (['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'] as $field) {
                if (isset($addr[$field]) && \is_string($addr[$field]) && '' !== $addr[$field]) {
                    $addrParts[] = $addr[$field];
                }
            }

            if ([] !== $addrParts) {
                $parts[] = implode(', ', $addrParts);
            }
        }

        $result = [] !== $parts ? implode(' — ', $parts) : null;

        return null !== $result ? self::decodeUnicode($result) : null;
    }

    /**
     * Extracts entry fee from JSON-LD offers (price × 100 → cents).
     *
     * @param array<string, mixed> $jsonLd
     *
     * @return array{0: ?int, 1: ?string}
     */
    private function extractEntryFee(array $jsonLd): array
    {
        if (!isset($jsonLd['offers']) || !\is_array($jsonLd['offers'])) {
            return [null, null];
        }

        /** @var array<string, mixed> $offers */
        $offers = $jsonLd['offers'];

        $price = isset($offers['price']) && (is_numeric($offers['price'])) ? (string) $offers['price'] : null;
        $currency = isset($offers['priceCurrency']) && \is_string($offers['priceCurrency'])
            ? $offers['priceCurrency']
            : null;

        if (null === $price) {
            return [null, $currency];
        }

        // Convert to cents
        $priceFloat = (float) $price;
        $amountCents = (int) round($priceFloat * 100);

        return [0 === $amountCents ? null : $amountCents, $currency];
    }

    /**
     * Decodes unicode escape sequences (\uXXXX) that may remain in JSON-LD strings.
     */
    private static function decodeUnicode(string $value): string
    {
        /** @var string $decoded */
        $decoded = json_decode('"'.str_replace('"', '\\"', $value).'"') ?? $value;

        return $decoded;
    }
}
