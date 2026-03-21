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

namespace App\Tests\HttpClient;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Replaces the real HTTP client in functional tests to avoid live TCGdex API calls.
 *
 * Returns minimal valid responses for all TCGdex API endpoints so that the
 * enrichment pipeline completes without errors or network timeouts.
 */
class TcgdexMockHttpClient implements HttpClientInterface
{
    private const string TCGDEX_BASE = 'https://api.tcgdex.net/';

    private readonly MockHttpClient $delegate;

    public function __construct()
    {
        $this->delegate = new MockHttpClient(function (string $method, string $url): MockResponse {
            // Non-TCGdex requests: return 404 (should not happen in tests)
            if (!str_starts_with($url, self::TCGDEX_BASE)) {
                return new MockResponse('', ['http_code' => 404]);
            }

            return $this->handleTcgdexRequest($url);
        });
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->delegate->request($method, $url, $options);
    }

    /**
     * @param ResponseInterface|iterable<ResponseInterface> $responses
     */
    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->delegate->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return $this;
    }

    private function handleTcgdexRequest(string $url): MockResponse
    {
        // Set list: /v2/en/sets
        if (preg_match('#/v2/[a-z]{2}/sets$#', $url)) {
            return $this->jsonResponse([
                ['id' => 'swsh9'],
                ['id' => 'sv01'],
                ['id' => 'sv05'],
                ['id' => 'sv06'],
                ['id' => 'sv08'],
                ['id' => 'svp'],
                ['id' => 'sm1'],
                ['id' => 'xy1'],
                ['id' => 'bw1'],
            ]);
        }

        // Set detail: /v2/en/sets/{setId}
        if (preg_match('#/v2/[a-z]{2}/sets/([a-z0-9.]+)$#', $url, $matches)) {
            return $this->buildSetDetailResponse($matches[1]);
        }

        // Card search by name: /v2/en/cards?name=...
        if (str_contains($url, '/cards?name=')) {
            return $this->jsonResponse([]);
        }

        // Card detail: /v2/en/cards/{cardId}
        if (preg_match('#/v2/[a-z]{2}/cards/([a-z0-9._-]+)$#i', $url, $matches)) {
            return $this->buildCardResponse($matches[1]);
        }

        return new MockResponse('', ['http_code' => 404]);
    }

    private function buildSetDetailResponse(string $setId): MockResponse
    {
        /** @var array<string, array{tcgOnline?: string, official?: string, releaseDate: string, officialCount: int}> $knownSets */
        $knownSets = [
            'swsh9' => ['tcgOnline' => 'BRS', 'official' => 'BRS', 'releaseDate' => '2022-02-25', 'officialCount' => 172],
            'sv01' => ['tcgOnline' => 'SVI', 'official' => 'SV', 'releaseDate' => '2023-03-31', 'officialCount' => 198],
            'sv05' => ['tcgOnline' => 'TEF', 'official' => 'TEF', 'releaseDate' => '2024-03-22', 'officialCount' => 167],
            'sv06' => ['tcgOnline' => 'TWM', 'official' => 'TWM', 'releaseDate' => '2024-05-24', 'officialCount' => 167],
            'sv08' => ['tcgOnline' => 'SSP', 'official' => 'SSP', 'releaseDate' => '2024-11-08', 'officialCount' => 191],
            'svp' => ['official' => 'SVP', 'releaseDate' => '2023-03-31', 'officialCount' => 300],
            'sm1' => ['tcgOnline' => 'SUM', 'official' => 'SUM', 'releaseDate' => '2017-02-03', 'officialCount' => 149],
            'xy1' => ['tcgOnline' => 'XY', 'official' => 'XY', 'releaseDate' => '2014-02-05', 'officialCount' => 146],
            'bw1' => ['tcgOnline' => 'BLW', 'official' => 'BLW', 'releaseDate' => '2011-04-25', 'officialCount' => 114],
        ];

        $set = $knownSets[$setId] ?? null;

        if (null === $set) {
            return $this->jsonResponse([
                'id' => $setId,
                'abbreviation' => ['official' => strtoupper($setId)],
                'releaseDate' => '2024-01-01',
                'cardCount' => ['official' => 200, 'total' => 200],
            ]);
        }

        $response = [
            'id' => $setId,
            'abbreviation' => ['official' => $set['official']],
            'releaseDate' => $set['releaseDate'],
            'cardCount' => ['official' => $set['officialCount'], 'total' => $set['officialCount'] + 20],
        ];

        if (isset($set['tcgOnline'])) {
            $response['tcgOnline'] = $set['tcgOnline'];
        }

        return $this->jsonResponse($response);
    }

    private function buildCardResponse(string $cardId): MockResponse
    {
        // Extract set ID and card number from cardId (e.g. "sv05-078" → "sv05", "078")
        $parts = explode('-', $cardId, 2);

        if (\count($parts) < 2) {
            return new MockResponse('', ['http_code' => 404]);
        }

        $setId = $parts[0];
        $localId = $parts[1];

        return $this->jsonResponse([
            'id' => $cardId,
            'localId' => $localId,
            'name' => 'Mock Card '.$localId,
            'category' => 'Pokemon',
            'rarity' => 'Common',
            'image' => 'https://assets.tcgdex.net/en/mock/'.$setId.'/'.$localId,
            'legal' => ['expanded' => true, 'standard' => true],
            'set' => [
                'id' => $setId,
                'cardCount' => ['official' => 200, 'total' => 220],
            ],
        ]);
    }

    /**
     * @param array<mixed> $data
     */
    private function jsonResponse(array $data): MockResponse
    {
        return new MockResponse(
            json_encode($data, \JSON_THROW_ON_ERROR),
            ['response_headers' => ['content-type' => 'application/json']],
        );
    }
}
