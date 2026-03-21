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

    /**
     * Known TCGdex set IDs with their PTCG codes.
     * Used to generate the set list and set detail responses.
     *
     * @var array<string, array{tcgOnline?: string, official: string, releaseDate: string, officialCount: int}>
     */
    private const array KNOWN_SETS = [
        'bw1' => ['tcgOnline' => 'BLW', 'official' => 'BLW', 'releaseDate' => '2011-04-25', 'officialCount' => 114],
        'bw3' => ['tcgOnline' => 'NVI', 'official' => 'NVI', 'releaseDate' => '2011-11-16', 'officialCount' => 101],
        'bw6' => ['tcgOnline' => 'PLF', 'official' => 'PLF', 'releaseDate' => '2013-05-08', 'officialCount' => 116],
        'bw7' => ['tcgOnline' => 'PLB', 'official' => 'PLB', 'releaseDate' => '2013-08-14', 'officialCount' => 101],
        'xy1' => ['tcgOnline' => 'XY', 'official' => 'XY', 'releaseDate' => '2014-02-05', 'officialCount' => 146],
        'xy4' => ['tcgOnline' => 'PHF', 'official' => 'PHF', 'releaseDate' => '2014-11-05', 'officialCount' => 119],
        'xy7' => ['tcgOnline' => 'AOR', 'official' => 'AOR', 'releaseDate' => '2015-08-12', 'officialCount' => 100],
        'xy8' => ['tcgOnline' => 'BKT', 'official' => 'BKT', 'releaseDate' => '2015-11-04', 'officialCount' => 162],
        'xy9' => ['tcgOnline' => 'BKP', 'official' => 'BKP', 'releaseDate' => '2016-02-03', 'officialCount' => 122],
        'xy10' => ['tcgOnline' => 'FCO', 'official' => 'FCO', 'releaseDate' => '2016-05-02', 'officialCount' => 124],
        'g1' => ['tcgOnline' => 'GEN', 'official' => 'GEN', 'releaseDate' => '2016-02-22', 'officialCount' => 83],
        'sm1' => ['tcgOnline' => 'SUM', 'official' => 'SUM', 'releaseDate' => '2017-02-03', 'officialCount' => 149],
        'sm2' => ['tcgOnline' => 'GRI', 'official' => 'GRI', 'releaseDate' => '2017-05-05', 'officialCount' => 145],
        'sm3' => ['tcgOnline' => 'BUS', 'official' => 'BUS', 'releaseDate' => '2017-08-05', 'officialCount' => 147],
        'sm4' => ['tcgOnline' => 'CIN', 'official' => 'CIN', 'releaseDate' => '2017-11-03', 'officialCount' => 111],
        'sm5' => ['tcgOnline' => 'UPR', 'official' => 'UPR', 'releaseDate' => '2018-02-02', 'officialCount' => 156],
        'sm6' => ['tcgOnline' => 'FLI', 'official' => 'FLI', 'releaseDate' => '2018-05-04', 'officialCount' => 131],
        'sm8' => ['tcgOnline' => 'LOT', 'official' => 'LOT', 'releaseDate' => '2018-11-02', 'officialCount' => 214],
        'sm9' => ['tcgOnline' => 'JTG', 'official' => 'JTG', 'releaseDate' => '2019-02-01', 'officialCount' => 181],
        'sm10' => ['tcgOnline' => 'UNB', 'official' => 'UNB', 'releaseDate' => '2019-05-03', 'officialCount' => 214],
        'sm12' => ['tcgOnline' => 'CEC', 'official' => 'CEC', 'releaseDate' => '2019-11-01', 'officialCount' => 236],
        'smp' => ['official' => 'SMP', 'releaseDate' => '2017-02-03', 'officialCount' => 300],
        'swsh1' => ['tcgOnline' => 'SSH', 'official' => 'SSH', 'releaseDate' => '2020-02-07', 'officialCount' => 202],
        'swsh3' => ['tcgOnline' => 'DAA', 'official' => 'DAA', 'releaseDate' => '2020-08-14', 'officialCount' => 189],
        'swsh4' => ['tcgOnline' => 'VIV', 'official' => 'VIV', 'releaseDate' => '2020-11-13', 'officialCount' => 185],
        'swsh5' => ['tcgOnline' => 'BST', 'official' => 'BST', 'releaseDate' => '2021-03-19', 'officialCount' => 163],
        'swsh6' => ['tcgOnline' => 'CRE', 'official' => 'CRE', 'releaseDate' => '2021-06-18', 'officialCount' => 198],
        'swsh7' => ['tcgOnline' => 'EVS', 'official' => 'EVS', 'releaseDate' => '2021-08-27', 'officialCount' => 203],
        'swsh8' => ['tcgOnline' => 'FST', 'official' => 'FST', 'releaseDate' => '2021-11-12', 'officialCount' => 264],
        'swsh9' => ['tcgOnline' => 'BRS', 'official' => 'BRS', 'releaseDate' => '2022-02-25', 'officialCount' => 172],
        'swsh10' => ['tcgOnline' => 'ASR', 'official' => 'ASR', 'releaseDate' => '2022-05-27', 'officialCount' => 189],
        'swsh11' => ['tcgOnline' => 'LOR', 'official' => 'LOR', 'releaseDate' => '2022-09-09', 'officialCount' => 196],
        'swsh12.5' => ['tcgOnline' => 'CRZ', 'official' => 'CRZ', 'releaseDate' => '2023-01-20', 'officialCount' => 159],
        'svp' => ['official' => 'SVP', 'releaseDate' => '2023-03-31', 'officialCount' => 300],
        'sv01' => ['tcgOnline' => 'SVI', 'official' => 'SV', 'releaseDate' => '2023-03-31', 'officialCount' => 198],
        'sv02' => ['tcgOnline' => 'PAL', 'official' => 'PAL', 'releaseDate' => '2023-06-09', 'officialCount' => 193],
        'sv03' => ['tcgOnline' => 'OBF', 'official' => 'OBF', 'releaseDate' => '2023-08-11', 'officialCount' => 197],
        'sv04' => ['tcgOnline' => 'PAR', 'official' => 'PAR', 'releaseDate' => '2023-11-03', 'officialCount' => 182],
        'sv04.5' => ['tcgOnline' => 'PAF', 'official' => 'PAF', 'releaseDate' => '2024-01-26', 'officialCount' => 91],
        'sv05' => ['tcgOnline' => 'TEF', 'official' => 'TEF', 'releaseDate' => '2024-03-22', 'officialCount' => 167],
        'sv06' => ['tcgOnline' => 'TWM', 'official' => 'TWM', 'releaseDate' => '2024-05-24', 'officialCount' => 167],
        'sv06.5' => ['tcgOnline' => 'SFA', 'official' => 'SFA', 'releaseDate' => '2024-08-02', 'officialCount' => 64],
        'sv07' => ['tcgOnline' => 'SCR', 'official' => 'SCR', 'releaseDate' => '2024-09-13', 'officialCount' => 167],
        'sv08' => ['tcgOnline' => 'SSP', 'official' => 'SSP', 'releaseDate' => '2024-11-08', 'officialCount' => 191],
        'sv08.5' => ['tcgOnline' => 'PRE', 'official' => 'PRE', 'releaseDate' => '2025-01-17', 'officialCount' => 87],
    ];

    private function handleTcgdexRequest(string $url): MockResponse
    {
        // Set list: /v2/en/sets
        if (preg_match('#/v2/[a-z]{2}/sets$#', $url)) {
            $sets = array_map(
                static fn (string $id): array => ['id' => $id],
                array_keys(self::KNOWN_SETS),
            );

            return $this->jsonResponse($sets);
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
        $set = self::KNOWN_SETS[$setId] ?? null;

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
