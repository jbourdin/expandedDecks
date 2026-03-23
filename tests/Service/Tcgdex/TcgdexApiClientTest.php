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

use App\Repository\TcgdexSetMappingRepository;
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

    public function testGetSetMappingMergesRepositoryWithStaticOverrides(): void
    {
        $repository = $this->createStub(TcgdexSetMappingRepository::class);
        $repository->method('getForwardMapping')->willReturn([
            'BRS' => 'swsh9',
            'LOR' => 'swsh11',
            'TWM' => 'sv06',
        ]);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $repository);
        $mapping = $client->getSetMapping();

        // Repository mappings
        self::assertSame('swsh9', $mapping['BRS']);
        self::assertSame('swsh11', $mapping['LOR']);
        self::assertSame('sv06', $mapping['TWM']);

        // Static promo overrides
        self::assertSame('svp', $mapping['PR-SV']);
        self::assertSame('swshp', $mapping['PR-SW']);
    }

    public function testGetSetMappingIncludesPtcgoShortPromoCodes(): void
    {
        $repository = $this->createStub(TcgdexSetMappingRepository::class);
        $repository->method('getForwardMapping')->willReturn([]);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $repository);
        $mapping = $client->getSetMapping();

        // PTCGO short codes map to the same TCGdex IDs as the PR-XX codes
        self::assertSame('svp', $mapping['SVP']);
        self::assertSame('swshp', $mapping['SWP']);
        self::assertSame('smp', $mapping['SMP']);
        self::assertSame('xyp', $mapping['XYP']);
        self::assertSame('bwp', $mapping['BWP']);

        // Verify consistency: short codes and PR-XX codes resolve to the same TCGdex ID
        self::assertSame($mapping['PR-SV'], $mapping['SVP']);
        self::assertSame($mapping['PR-SW'], $mapping['SWP']);
        self::assertSame($mapping['PR-SM'], $mapping['SMP']);
        self::assertSame($mapping['PR-XY'], $mapping['XYP']);
        self::assertSame($mapping['PR-BW'], $mapping['BWP']);
    }

    public function testFindCardResolvesPtcgoShortPromoCode(): void
    {
        // SMP 217 (Trevenant & Dusknoir-GX) should resolve to smp-SM217
        $httpClient = $this->createCardMockClient([
            'smp-SM217' => [
                'status' => 200,
                'body' => [
                    'id' => 'smp-SM217',
                    'name' => 'Trevenant & Dusknoir GX',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/sm/smp/SM217',
                    'legal' => ['expanded' => true],
                ],
            ],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub([]));
        $card = $client->findCard('SMP', '217');

        self::assertNotNull($card);
        self::assertSame('smp-SM217', $card->id);
    }

    public function testGetSetMappingReadsFromRepository(): void
    {
        $repository = $this->createStub(TcgdexSetMappingRepository::class);
        $repository->method('getForwardMapping')->willReturn(['BRS' => 'swsh9']);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $repository);

        $mapping = $client->getSetMapping();

        self::assertSame('swsh9', $mapping['BRS']);
    }

    public function testFindCardReturnsCardOnSuccess(): void
    {
        $httpClient = $this->createCardMockClient([
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
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
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
        $httpClient = $this->createCardMockClient(['swsh9-999' => ['status' => 404]]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('BRS', '999');

        self::assertNull($card);
    }

    public function testFindCardRetriesWithPaddedNumber(): void
    {
        $httpClient = $this->createCardMockClient([
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
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('BRS', '79');

        self::assertNotNull($card);
        self::assertSame('swsh9-079', $card->id);
    }

    public function testFindCardReturnsNullForUnknownSetCode(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('UNKNOWN', '1');

        self::assertNull($card);
    }

    public function testFindCardResolvesPromoCode(): void
    {
        // SV promos use plain card numbers (no era prefix)
        $httpClient = $this->createCardMockClient([
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
        ]);

        // PR-SV is a static override, no repository mapping needed
        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub([]));
        $card = $client->findCard('PR-SV', '67');

        self::assertNotNull($card);
        self::assertSame('svp-67', $card->id);
    }

    public function testFindCardPrefixesCardNumberForXyPromos(): void
    {
        // XY promos: PTCG "XYP 177" → TCGdex "xyp-XY177"
        $httpClient = $this->createCardMockClient([
            'xyp-XY177' => [
                'status' => 200,
                'body' => [
                    'id' => 'xyp-XY177',
                    'name' => 'Karen',
                    'category' => 'Trainer',
                    'trainerType' => 'Supporter',
                    'image' => 'https://assets.tcgdex.net/en/xy/xyp/XY177',
                    'legal' => ['expanded' => true],
                ],
            ],
        ]);

        // PR-XY is a static override
        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub([]));
        $card = $client->findCard('PR-XY', '177');

        self::assertNotNull($card);
        self::assertSame('xyp-XY177', $card->id);
        self::assertSame('Karen', $card->name);
        self::assertSame('Supporter', $card->trainerType);
    }

    public function testFindCardPrefixesCardNumberForSwshPromos(): void
    {
        // SWSH promos: PTCG "PR-SW 001" → TCGdex "swshp-SWSH001"
        $httpClient = $this->createCardMockClient([
            'swshp-SWSH001' => [
                'status' => 200,
                'body' => [
                    'id' => 'swshp-SWSH001',
                    'name' => 'Grookey',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/swsh/swshp/SWSH001',
                    'legal' => ['expanded' => true],
                ],
            ],
        ]);

        // PR-SW is a static override
        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub([]));
        $card = $client->findCard('PR-SW', '001');

        self::assertNotNull($card);
        self::assertSame('swshp-SWSH001', $card->id);
    }

    public function testFindCardIncludesTrainerType(): void
    {
        $httpClient = $this->createCardMockClient([
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
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('BRS', '132');

        self::assertNotNull($card);
        self::assertSame('Supporter', $card->trainerType);
    }

    public function testFindCardParsesAbilitiesAndAttacks(): void
    {
        $httpClient = $this->createCardMockClient([
            'swsh8-185' => [
                'status' => 200,
                'body' => [
                    'id' => 'swsh8-185',
                    'name' => 'Genesect V',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/swsh/swsh8/185',
                    'legal' => ['expanded' => true],
                    'hp' => 190,
                    'abilities' => [
                        ['type' => 'Ability', 'name' => 'Fusion Strike System', 'effect' => 'Draw cards...'],
                    ],
                    'attacks' => [
                        ['cost' => ['Metal', 'Metal', 'Colorless'], 'name' => 'Techno Blast', 'damage' => 210, 'effect' => 'Cannot attack next turn.'],
                    ],
                ],
            ],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['FST' => 'swsh8']));
        $card = $client->findCard('FST', '185');

        self::assertNotNull($card);
        self::assertSame(['Fusion Strike System'], $card->abilities);
        self::assertSame(['Techno Blast'], $card->attacks);
    }

    public function testFindCardParsesMultipleAttacksNoAbilities(): void
    {
        $httpClient = $this->createCardMockClient([
            'swsh8-113' => [
                'status' => 200,
                'body' => [
                    'id' => 'swsh8-113',
                    'name' => 'Mew V',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/swsh/swsh8/113',
                    'legal' => ['expanded' => true],
                    'hp' => 180,
                    'attacks' => [
                        ['cost' => ['Colorless'], 'name' => 'Energy Mix', 'effect' => '...'],
                        ['cost' => ['Psychic', 'Colorless'], 'name' => 'Psychic Leap', 'damage' => 70, 'effect' => '...'],
                    ],
                ],
            ],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['FST' => 'swsh8']));
        $card = $client->findCard('FST', '113');

        self::assertNotNull($card);
        self::assertSame([], $card->abilities);
        self::assertSame(['Energy Mix', 'Psychic Leap'], $card->attacks);
    }

    public function testFindCardReturnsEmptyAbilitiesAndAttacksForTrainer(): void
    {
        $httpClient = $this->createCardMockClient([
            'swsh8-225' => [
                'status' => 200,
                'body' => [
                    'id' => 'swsh8-225',
                    'name' => 'Battle VIP Pass',
                    'category' => 'Trainer',
                    'trainerType' => 'Item',
                    'image' => 'https://assets.tcgdex.net/en/swsh/swsh8/225',
                    'legal' => ['expanded' => true],
                ],
            ],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['FST' => 'swsh8']));
        $card = $client->findCard('FST', '225');

        self::assertNotNull($card);
        self::assertSame([], $card->abilities);
        self::assertSame([], $card->attacks);
    }

    public function testFindImageByNameReturnsFirstAvailableImage(): void
    {
        $httpClient = $this->createSearchMockClient([
            ['id' => 'swsh9-69', 'name' => 'Double Colorless Energy'],
            ['id' => 'sm3.5-69', 'name' => 'Double Colorless Energy', 'image' => 'https://assets.tcgdex.net/en/sm/sm35/069'],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub([]));
        $imageUrl = $client->findImageByName('Double Colorless Energy');

        self::assertSame('https://assets.tcgdex.net/en/sm/sm35/069/high.webp', $imageUrl);
    }

    public function testFindImageByNameReturnsNullWhenNoPrintingHasImage(): void
    {
        $httpClient = $this->createSearchMockClient([
            ['id' => 'swsh9-69', 'name' => 'Double Colorless Energy'],
            ['id' => 'xy1-42', 'name' => 'Double Colorless Energy'],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub([]));
        $imageUrl = $client->findImageByName('Double Colorless Energy');

        self::assertNull($imageUrl);
    }

    public function testFindCardHandlesTrainerGallerySuffix(): void
    {
        // ASR-TG 30 → set ASR, number TG30
        $httpClient = $this->createCardMockClient([
            'swsh10-TG30' => [
                'status' => 200,
                'body' => [
                    'id' => 'swsh10-TG30',
                    'name' => 'Shadow Rider Calyrex VMAX',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/swsh/swsh10/TG30',
                    'legal' => ['expanded' => true],
                ],
            ],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['ASR' => 'swsh10']));
        $card = $client->findCard('ASR-TG', '30');

        self::assertNotNull($card);
        self::assertSame('swsh10-TG30', $card->id);
    }

    public function testFindCardStripsLetterSuffix(): void
    {
        // Card number "113a" should retry with "113" if "113a" not found
        $httpClient = $this->createCardMockClient([
            'swsh9-113a' => ['status' => 404],
            'swsh9-113' => [
                'status' => 200,
                'body' => [
                    'id' => 'swsh9-113',
                    'name' => 'Mew V',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/swsh/swsh9/113',
                    'legal' => ['expanded' => true],
                ],
            ],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('BRS', '113a');

        self::assertNotNull($card);
        self::assertSame('swsh9-113', $card->id);
    }

    public function testFindAllPrintingsByNameReturnsEmptyOnNon200(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $httpClient->method('request')->willReturn($response);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub([]));
        $result = $client->findAllPrintingsByName('NonexistentCard');

        self::assertSame([], $result);
    }

    public function testFindAllPrintingsByNameSkipsResultsWithoutId(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function (string $method, string $url): ResponseInterface {
                $response = $this->createStub(ResponseInterface::class);

                if (str_contains($url, '/cards?name=')) {
                    $response->method('getStatusCode')->willReturn(200);
                    $response->method('toArray')->willReturn([
                        ['name' => 'Test Card'], // no 'id' key
                    ]);

                    return $response;
                }

                $response->method('getStatusCode')->willReturn(404);

                return $response;
            });

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub([]));
        $result = $client->findAllPrintingsByName('Test Card');

        self::assertSame([], $result);
    }

    public function testFindCardParsesCardmarketPricing(): void
    {
        $httpClient = $this->createCardMockClient([
            'swsh9-123' => [
                'status' => 200,
                'body' => [
                    'id' => 'swsh9-123',
                    'name' => 'Arceus VSTAR',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/swsh/swsh9/123',
                    'legal' => ['expanded' => true],
                    'pricing' => [
                        'cardmarket' => ['avg' => 5.42, 'idProduct' => 12345],
                    ],
                ],
            ],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('BRS', '123');

        self::assertNotNull($card);
        self::assertSame(542, $card->priceInCents);
        self::assertSame(12345, $card->cardmarketProductId);
    }

    public function testFindCardParsesTcgplayerPricingFallback(): void
    {
        $httpClient = $this->createCardMockClient([
            'swsh9-123' => [
                'status' => 200,
                'body' => [
                    'id' => 'swsh9-123',
                    'name' => 'Arceus VSTAR',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/swsh/swsh9/123',
                    'legal' => ['expanded' => true],
                    'pricing' => [
                        'tcgplayer' => [
                            'normal' => ['midPrice' => 3.99, 'productId' => 67890],
                        ],
                    ],
                ],
            ],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('BRS', '123');

        self::assertNotNull($card);
        self::assertSame(399, $card->priceInCents);
        self::assertSame(67890, $card->tcgplayerProductId);
    }

    public function testFindCardParsesSetMetadata(): void
    {
        $httpClient = $this->createCardMockClient([
            'swsh9-123' => [
                'status' => 200,
                'body' => [
                    'id' => 'swsh9-123',
                    'localId' => '123',
                    'name' => 'Arceus VSTAR',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/swsh/swsh9/123',
                    'legal' => ['expanded' => true],
                    'hp' => 280,
                    'rarity' => 'Ultra Rare',
                    'set' => [
                        'id' => 'swsh9',
                        'releaseDate' => '2022-02-25',
                        'cardCount' => ['official' => 172],
                    ],
                ],
            ],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('BRS', '123');

        self::assertNotNull($card);
        self::assertSame(280, $card->hp);
        self::assertSame('Ultra Rare', $card->rarity);
        self::assertSame('2022-02-25', $card->setReleaseDate);
        self::assertSame('BRS', $card->setCode);
        self::assertSame('123', $card->cardNumber);
        self::assertSame(172, $card->setOfficialCardCount);
    }

    public function testFindCardFetchesReleaseDateFromSetEndpointWhenMissing(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function (string $method, string $url): ResponseInterface {
                $response = $this->createStub(ResponseInterface::class);

                if (str_ends_with($url, '/cards/swsh9-123')) {
                    $response->method('getStatusCode')->willReturn(200);
                    $response->method('toArray')->willReturn([
                        'id' => 'swsh9-123',
                        'name' => 'Arceus VSTAR',
                        'category' => 'Pokemon',
                        'legal' => ['expanded' => true],
                        'set' => ['id' => 'swsh9'], // no releaseDate
                    ]);

                    return $response;
                }

                if (str_ends_with($url, '/sets/swsh9')) {
                    $response->method('getStatusCode')->willReturn(200);
                    $response->method('toArray')->willReturn(['releaseDate' => '2022-02-25']);

                    return $response;
                }

                $response->method('getStatusCode')->willReturn(404);

                return $response;
            });

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('BRS', '123');

        self::assertNotNull($card);
        self::assertSame('2022-02-25', $card->setReleaseDate);
    }

    public function testFindCardReturnsNullPricingWhenNoPricingData(): void
    {
        $httpClient = $this->createCardMockClient([
            'swsh9-123' => [
                'status' => 200,
                'body' => [
                    'id' => 'swsh9-123',
                    'name' => 'Arceus VSTAR',
                    'category' => 'Pokemon',
                    'legal' => ['expanded' => true],
                    // no pricing key
                ],
            ],
        ]);

        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('BRS', '123');

        self::assertNotNull($card);
        self::assertNull($card->priceInCents);
        self::assertNull($card->cardmarketProductId);
        self::assertNull($card->tcgplayerProductId);
    }

    public function testHasMappingsReturnsFalseWhenEmpty(): void
    {
        $repository = $this->createStub(TcgdexSetMappingRepository::class);
        $repository->method('isEmpty')->willReturn(true);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $repository);

        self::assertFalse($client->hasMappings());
    }

    public function testHasMappingsReturnsTrueWhenNotEmpty(): void
    {
        $repository = $this->createStub(TcgdexSetMappingRepository::class);
        $repository->method('isEmpty')->willReturn(false);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $client = new TcgdexApiClient($httpClient, new ArrayAdapter(), $repository);

        self::assertTrue($client->hasMappings());
    }

    /**
     * Creates a repository stub with the given forward mapping.
     *
     * @param array<string, string> $forwardMapping PTCG code → TCGdex set ID
     */
    private function createRepositoryStub(array $forwardMapping): TcgdexSetMappingRepository
    {
        $repository = $this->createStub(TcgdexSetMappingRepository::class);
        $repository->method('getForwardMapping')->willReturn($forwardMapping);
        $repository->method('getReverseMapping')->willReturn(array_flip($forwardMapping));

        return $repository;
    }

    /**
     * Creates a mock client that handles card lookup requests only.
     *
     * @param array<string, array{status: int, body?: array<string, mixed>}> $cards keyed by "{setId}-{number}"
     */
    private function createCardMockClient(array $cards): HttpClientInterface
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($cards): ResponseInterface {
                $response = $this->createStub(ResponseInterface::class);

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
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($searchResults): ResponseInterface {
                $response = $this->createStub(ResponseInterface::class);

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
