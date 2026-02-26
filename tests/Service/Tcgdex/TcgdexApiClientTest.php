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

namespace App\Tests\Service\Tcgdex;

use App\Service\Tcgdex\TcgdexApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class TcgdexApiClientTest extends TestCase
{
    /**
     * Map of set details used by the mock HTTP client.
     *
     * @var array<string, array<string, mixed>>
     */
    private const array DEFAULT_SETS = [
        'swsh9' => ['id' => 'swsh9', 'tcgOnline' => 'BRS', 'abbreviation' => ['official' => 'BRS']],
        'swsh11' => ['id' => 'swsh11', 'tcgOnline' => 'LOR', 'abbreviation' => ['official' => 'LOR']],
        'sv06' => ['id' => 'sv06', 'abbreviation' => ['official' => 'TWM']], // SV era: no tcgOnline
    ];

    public function testGetSetMappingBuildsFromApiAndIncludesPromoOverrides(): void
    {
        $client = new TcgdexApiClient($this->createSetMockClient(self::DEFAULT_SETS), new ArrayAdapter());
        $mapping = $client->getSetMapping();

        // tcgOnline mappings (older sets)
        self::assertSame('swsh9', $mapping['BRS']);
        self::assertSame('swsh11', $mapping['LOR']);

        // abbreviation.official mapping (SV era, no tcgOnline)
        self::assertSame('sv06', $mapping['TWM']);

        // Promo overrides
        self::assertSame('svp', $mapping['PR-SV']);
        self::assertSame('swshp', $mapping['PR-SW']);
    }

    public function testGetSetMappingIsCached(): void
    {
        $callCount = 0;
        $sets = ['swsh9' => ['id' => 'swsh9', 'tcgOnline' => 'BRS']];
        $httpClient = $this->createSetMockClient($sets, $callCount);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter());
        $client->getSetMapping();
        $firstCallCount = $callCount;

        $client->getSetMapping();

        // Second call should not make any new HTTP requests (cached)
        self::assertSame($firstCallCount, $callCount);
    }

    public function testFindCardReturnsCardOnSuccess(): void
    {
        $httpClient = $this->createFullMockClient(
            ['swsh9' => ['id' => 'swsh9', 'tcgOnline' => 'BRS']],
            [
                'swsh9-123' => [
                    'status' => 200,
                    'body' => [
                        'id' => 'swsh9-123',
                        'name' => 'Arceus VSTAR',
                        'category' => 'Pokemon',
                        'image' => 'https://assets.tcgdex.net/en/swsh/swsh9/123',
                        'legal' => ['expanded' => true],
                    ],
                ],
            ],
        );

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter());
        $card = $client->findCard('BRS', '123');

        self::assertNotNull($card);
        self::assertSame('swsh9-123', $card->id);
        self::assertSame('Arceus VSTAR', $card->name);
        self::assertSame('Pokemon', $card->category);
        self::assertSame('https://assets.tcgdex.net/en/swsh/swsh9/123/high.webp', $card->imageUrl);
        self::assertTrue($card->isExpandedLegal);
        self::assertNull($card->trainerType);
    }

    public function testFindCardReturnsNullOn404(): void
    {
        $httpClient = $this->createFullMockClient(
            ['swsh9' => ['id' => 'swsh9', 'tcgOnline' => 'BRS']],
            ['swsh9-999' => ['status' => 404]],
        );

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter());
        $card = $client->findCard('BRS', '999');

        self::assertNull($card);
    }

    public function testFindCardRetriesWithPaddedNumber(): void
    {
        $httpClient = $this->createFullMockClient(
            ['swsh9' => ['id' => 'swsh9', 'tcgOnline' => 'BRS']],
            [
                'swsh9-79' => ['status' => 404],
                'swsh9-079' => [
                    'status' => 200,
                    'body' => [
                        'id' => 'swsh9-079',
                        'name' => 'Comfey',
                        'category' => 'Pokemon',
                        'image' => 'https://assets.tcgdex.net/en/swsh/swsh9/079',
                        'legal' => ['expanded' => true],
                    ],
                ],
            ],
        );

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter());
        $card = $client->findCard('BRS', '79');

        self::assertNotNull($card);
        self::assertSame('swsh9-079', $card->id);
    }

    public function testFindCardReturnsNullForUnknownSetCode(): void
    {
        $httpClient = $this->createSetMockClient(
            ['swsh9' => ['id' => 'swsh9', 'tcgOnline' => 'BRS']],
        );

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter());
        $card = $client->findCard('UNKNOWN', '1');

        self::assertNull($card);
    }

    public function testFindCardResolvesPromoCode(): void
    {
        // No standard sets — only promo overrides
        $httpClient = $this->createFullMockClient(
            [],
            [
                'svp-67' => [
                    'status' => 200,
                    'body' => [
                        'id' => 'svp-67',
                        'name' => 'Roaring Moon ex',
                        'category' => 'Pokemon',
                        'image' => 'https://assets.tcgdex.net/en/sv/svp/067',
                        'legal' => ['expanded' => true],
                    ],
                ],
            ],
        );

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter());
        $card = $client->findCard('PR-SV', '67');

        self::assertNotNull($card);
        self::assertSame('svp-67', $card->id);
    }

    public function testFindCardIncludesTrainerType(): void
    {
        $httpClient = $this->createFullMockClient(
            ['swsh9' => ['id' => 'swsh9', 'tcgOnline' => 'BRS']],
            [
                'swsh9-132' => [
                    'status' => 200,
                    'body' => [
                        'id' => 'swsh9-132',
                        'name' => "Boss's Orders",
                        'category' => 'Trainer',
                        'trainerType' => 'Supporter',
                        'image' => 'https://assets.tcgdex.net/en/swsh/swsh9/132',
                        'legal' => ['expanded' => true],
                    ],
                ],
            ],
        );

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter());
        $card = $client->findCard('BRS', '132');

        self::assertNotNull($card);
        self::assertSame('Supporter', $card->trainerType);
    }

    public function testFindImageByNameReturnsFirstAvailableImage(): void
    {
        $httpClient = $this->createSearchMockClient([
            ['id' => 'swsh9-69', 'name' => 'Double Colorless Energy'],
            ['id' => 'sm3.5-69', 'name' => 'Double Colorless Energy', 'image' => 'https://assets.tcgdex.net/en/sm/sm35/069'],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter());
        $imageUrl = $client->findImageByName('Double Colorless Energy');

        self::assertSame('https://assets.tcgdex.net/en/sm/sm35/069/high.webp', $imageUrl);
    }

    public function testFindImageByNameReturnsNullWhenNoPrintingHasImage(): void
    {
        $httpClient = $this->createSearchMockClient([
            ['id' => 'swsh9-69', 'name' => 'Double Colorless Energy'],
            ['id' => 'xy1-42', 'name' => 'Double Colorless Energy'],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter());
        $imageUrl = $client->findImageByName('Double Colorless Energy');

        self::assertNull($imageUrl);
    }

    /**
     * Creates a mock client that only handles set list + set detail requests.
     *
     * @param array<string, array<string, mixed>> $sets
     */
    private function createSetMockClient(array $sets, ?int &$callCount = null): HttpClientInterface
    {
        $callCount = 0;
        $listData = array_map(static fn (array $set): array => ['id' => $set['id']], $sets);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($sets, $listData, &$callCount): ResponseInterface {
                ++$callCount;

                $response = $this->createMock(ResponseInterface::class);

                if (str_ends_with($url, '/sets')) {
                    $response->method('toArray')->willReturn(array_values($listData));

                    return $response;
                }

                // /sets/{id} detail
                foreach ($sets as $setId => $detail) {
                    if (str_ends_with($url, '/sets/'.$setId)) {
                        $response->method('toArray')->willReturn($detail);

                        return $response;
                    }
                }

                $response->method('toArray')->willReturn([]);

                return $response;
            });

        return $httpClient;
    }

    /**
     * Creates a mock client that handles set mapping AND card lookup requests.
     *
     * @param array<string, array<string, mixed>>                            $sets
     * @param array<string, array{status: int, body?: array<string, mixed>}> $cards keyed by "{setId}-{number}"
     */
    private function createFullMockClient(array $sets, array $cards): HttpClientInterface
    {
        $listData = array_map(static fn (array $set): array => ['id' => $set['id']], $sets);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($sets, $listData, $cards): ResponseInterface {
                $response = $this->createMock(ResponseInterface::class);

                // /sets (list)
                if (str_ends_with($url, '/sets')) {
                    $response->method('toArray')->willReturn(array_values($listData));

                    return $response;
                }

                // /sets/{id} (detail)
                foreach ($sets as $setId => $detail) {
                    if (str_ends_with($url, '/sets/'.$setId)) {
                        $response->method('toArray')->willReturn($detail);

                        return $response;
                    }
                }

                // /cards/{setId}-{number}
                foreach ($cards as $cardKey => $cardData) {
                    if (str_ends_with($url, '/cards/'.$cardKey)) {
                        $response->method('getStatusCode')->willReturn($cardData['status']);

                        if (isset($cardData['body'])) {
                            $response->method('toArray')->willReturn($cardData['body']);
                        }

                        return $response;
                    }
                }

                // Default: 404
                $response->method('getStatusCode')->willReturn(404);

                return $response;
            });

        return $httpClient;
    }

    /**
     * Creates a mock client that handles /cards?name= search requests.
     *
     * @param list<array<string, mixed>> $searchResults
     */
    private function createSearchMockClient(array $searchResults): HttpClientInterface
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($searchResults): ResponseInterface {
                $response = $this->createMock(ResponseInterface::class);

                if (str_contains($url, '/cards?name=')) {
                    $response->method('getStatusCode')->willReturn(200);
                    $response->method('toArray')->willReturn($searchResults);

                    return $response;
                }

                $response->method('getStatusCode')->willReturn(404);

                return $response;
            });

        return $httpClient;
    }
}
