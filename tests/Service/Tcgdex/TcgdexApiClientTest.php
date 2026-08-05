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

use App\Entity\TcgdexCard as TcgdexCardEntity;
use App\Entity\TcgdexSerie;
use App\Entity\TcgdexSet;
use App\Repository\TcgdexCardRepository;
use App\Repository\TcgdexSetAliasRepository;
use App\Repository\TcgdexSetMappingRepository;
use App\Service\Tcgdex\CardNameMatcher;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Service\Tcgdex\TcgdexCard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
        $client = $this->createClient($httpClient, $repository);
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
        $client = $this->createClient($httpClient, $repository);
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub([]));
        $card = $client->findCard('SMP', '217');

        self::assertNotNull($card);
        self::assertSame('smp-SM217', $card->id);
    }

    public function testGetSetMappingReadsFromRepository(): void
    {
        $repository = $this->createStub(TcgdexSetMappingRepository::class);
        $repository->method('getForwardMapping')->willReturn(['BRS' => 'swsh9']);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $client = $this->createClient($httpClient, $repository);

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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
        $card = $client->findCard('BRS', '79');

        self::assertNotNull($card);
        self::assertSame('swsh9-079', $card->id);
    }

    public function testFindCardReturnsNullForUnknownSetCode(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
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
        $client = $this->createClient($httpClient, $this->createRepositoryStub([]));
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
        $client = $this->createClient($httpClient, $this->createRepositoryStub([]));
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
        $client = $this->createClient($httpClient, $this->createRepositoryStub([]));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['FST' => 'swsh8']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['FST' => 'swsh8']));
        $card = $client->findCard('FST', '113');

        self::assertNotNull($card);
        self::assertSame([], $card->abilities);
        self::assertSame(['Energy Mix', 'Psychic Leap'], $card->attacks);
        self::assertSame([null, 70], $card->attackDamages);
    }

    public function testFindCardParsesPokemonTypes(): void
    {
        // The Pokemon `types` field disambiguates mechanically-identical cards across
        // elemental variants (e.g. Dialga GX Metal vs Dragon). Parser must extract it
        // verbatim so CardIdentityResolver can sort + signature it downstream.
        $httpClient = $this->createCardMockClient([
            'sm6-125' => [
                'status' => 200,
                'body' => [
                    'id' => 'sm6-125',
                    'name' => 'Dialga GX',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/sm/sm6/125',
                    'legal' => ['expanded' => true],
                    'hp' => 180,
                    'types' => ['Metal'],
                    'attacks' => [
                        ['cost' => ['Metal', 'Metal'], 'name' => 'Shred', 'damage' => 100],
                    ],
                ],
            ],
        ]);

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['FLI' => 'sm6']));
        $card = $client->findCard('FLI', '125');

        self::assertNotNull($card);
        self::assertSame(['Metal'], $card->types);
    }

    public function testFindCardParsesDualPokemonTypes(): void
    {
        // Dual-type Pokemon return multiple entries in `types`; the parser keeps source
        // order — sorting happens at signature time in CardIdentityResolver.
        $httpClient = $this->createCardMockClient([
            'swsh8-100' => [
                'status' => 200,
                'body' => [
                    'id' => 'swsh8-100',
                    'name' => 'Test Dual',
                    'category' => 'Pokemon',
                    'image' => 'https://assets.tcgdex.net/en/swsh/swsh8/100',
                    'legal' => ['expanded' => true],
                    'hp' => 120,
                    'types' => ['Fire', 'Water'],
                ],
            ],
        ]);

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['FST' => 'swsh8']));
        $card = $client->findCard('FST', '100');

        self::assertNotNull($card);
        self::assertSame(['Fire', 'Water'], $card->types);
    }

    public function testFindCardReturnsEmptyTypesForTrainer(): void
    {
        // Non-Pokemon payloads omit `types` entirely; parser must default to an empty list
        // so CardIdentity gets the empty-string sentinel for non-Pokemon identities.
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['FST' => 'swsh8']));
        $card = $client->findCard('FST', '225');

        self::assertNotNull($card);
        self::assertSame([], $card->types);
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['FST' => 'swsh8']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub([]));
        $imageUrl = $client->findImageByName('Double Colorless Energy');

        self::assertSame('https://assets.tcgdex.net/en/sm/sm35/069/high.webp', $imageUrl);
    }

    public function testFindImageByNameReturnsNullWhenNoPrintingHasImage(): void
    {
        $httpClient = $this->createSearchMockClient([
            ['id' => 'swsh9-69', 'name' => 'Double Colorless Energy'],
            ['id' => 'xy1-42', 'name' => 'Double Colorless Energy'],
        ]);

        $client = $this->createClient($httpClient, $this->createRepositoryStub([]));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['ASR' => 'swsh10']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub([]));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub([]));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
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

        $client = $this->createClient($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']));
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
        $client = $this->createClient($httpClient, $repository);

        self::assertFalse($client->hasMappings());
    }

    public function testHasMappingsReturnsTrueWhenNotEmpty(): void
    {
        $repository = $this->createStub(TcgdexSetMappingRepository::class);
        $repository->method('isEmpty')->willReturn(false);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $client = $this->createClient($httpClient, $repository);

        self::assertTrue($client->hasMappings());
    }

    public function testFindCardReturnsLocalEntityWithoutCallingHttp(): void
    {
        $entity = $this->createTcgdexCardEntity(
            id: 'swsh9-123',
            localId: '123',
            setId: 'swsh9',
            serieId: 'swsh',
            name: ['en' => 'Arceus VSTAR'],
            category: 'Pokemon',
            hp: 280,
            rarity: 'Ultra Rare',
            isExpandedLegal: true,
            ptcgCode: 'BRS',
            releaseDate: new \DateTimeImmutable('2022-02-25'),
            officialCardCount: 172,
            cardmarketProductId: 12345,
            tcgplayerProductId: 67890,
        );

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findBySetAndLocalId')
            ->willReturnCallback(static function (string $setId, string $localId) use ($entity): ?TcgdexCardEntity {
                if ('swsh9' === $setId && '123' === $localId) {
                    return $entity;
                }

                return null;
            });

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']), $cardRepository);
        $card = $client->findCard('BRS', '123');

        self::assertNotNull($card);
        self::assertSame('swsh9-123', $card->id);
        self::assertSame('Arceus VSTAR', $card->name);
        self::assertSame('Pokemon', $card->category);
        self::assertSame(280, $card->hp);
        self::assertSame('Ultra Rare', $card->rarity);
        self::assertTrue($card->isExpandedLegal);
        self::assertSame('2022-02-25', $card->setReleaseDate);
        self::assertSame('BRS', $card->setCode);
        self::assertSame('123', $card->cardNumber);
        self::assertSame(172, $card->setOfficialCardCount);
        self::assertSame(12345, $card->cardmarketProductId);
        self::assertSame(67890, $card->tcgplayerProductId);
    }

    public function testFindCardForwardsPokemonTypesFromLocalEntity(): void
    {
        // Covers buildDtoFromEntity: when a printing is resolved via the local mirror
        // (no HTTP call), the entity's `types` JSON must flow into the DTO so the
        // resolver sees the correct elemental type for Dialga-GX-style cards.
        $entity = $this->createTcgdexCardEntity(
            id: 'sm6-125',
            localId: '125',
            setId: 'sm6',
            serieId: 'sm',
            name: ['en' => 'Dialga GX'],
            category: 'Pokemon',
            hp: 180,
            isExpandedLegal: true,
            ptcgCode: 'FLI',
            types: ['Metal'],
        );

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findBySetAndLocalId')
            ->willReturnCallback(static function (string $setId, string $localId) use ($entity): ?TcgdexCardEntity {
                if ('sm6' === $setId && '125' === $localId) {
                    return $entity;
                }

                return null;
            });

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub(['FLI' => 'sm6']), $cardRepository);
        $card = $client->findCard('FLI', '125');

        self::assertNotNull($card);
        self::assertSame(['Metal'], $card->types);
    }

    public function testFindCardLocalLookupTriesStrippedCandidate(): void
    {
        // Card "113a" should try "113a" first, then "113" (letter-stripped)
        $entity = $this->createTcgdexCardEntity(
            id: 'swsh9-113',
            localId: '113',
            setId: 'swsh9',
            serieId: 'swsh',
            name: ['en' => 'Mew V'],
            category: 'Pokemon',
        );

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findBySetAndLocalId')
            ->willReturnCallback(static function (string $setId, string $localId) use ($entity): ?TcgdexCardEntity {
                // "113a" not found, but "113" (stripped) is found
                if ('swsh9' === $setId && '113' === $localId) {
                    return $entity;
                }

                return null;
            });

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']), $cardRepository);
        $card = $client->findCard('BRS', '113a');

        self::assertNotNull($card);
        self::assertSame('swsh9-113', $card->id);
        self::assertSame('Mew V', $card->name);
    }

    public function testFindCardLocalLookupTriesZeroPaddedCandidate(): void
    {
        // Card "79" should try "79" first, then "079" (zero-padded)
        $entity = $this->createTcgdexCardEntity(
            id: 'swsh9-079',
            localId: '079',
            setId: 'swsh9',
            serieId: 'swsh',
            name: ['en' => 'Comfey'],
            category: 'Pokemon',
        );

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findBySetAndLocalId')
            ->willReturnCallback(static function (string $setId, string $localId) use ($entity): ?TcgdexCardEntity {
                // "79" not found, but "079" (zero-padded) is found
                if ('swsh9' === $setId && '079' === $localId) {
                    return $entity;
                }

                return null;
            });

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']), $cardRepository);
        $card = $client->findCard('BRS', '79');

        self::assertNotNull($card);
        self::assertSame('swsh9-079', $card->id);
        self::assertSame('Comfey', $card->name);
    }

    public function testFindCardFallsBackToHttpWhenLocalReturnsNull(): void
    {
        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findBySetAndLocalId')->willReturn(null);

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

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']), $cardRepository);
        $card = $client->findCard('BRS', '123');

        self::assertNotNull($card);
        self::assertSame('swsh9-123', $card->id);
    }

    public function testFindAllPrintingsByNameReturnsLocalEntitiesWithoutCallingHttp(): void
    {
        $entity1 = $this->createTcgdexCardEntity(
            id: 'swsh9-079',
            localId: '079',
            setId: 'swsh9',
            serieId: 'swsh',
            name: ['en' => 'Comfey'],
            category: 'Pokemon',
            hp: 70,
            ptcgCode: 'BRS',
        );

        $entity2 = $this->createTcgdexCardEntity(
            id: 'sv02-051',
            localId: '051',
            setId: 'sv02',
            serieId: 'sv',
            name: ['en' => 'Comfey'],
            category: 'Pokemon',
            hp: 60,
            ptcgCode: 'PAL',
        );

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findAllByNameEn')->willReturnCallback(
            static function (string $name) use ($entity1, $entity2): array {
                if ('Comfey' === $name) {
                    return [$entity1, $entity2];
                }

                return [];
            }
        );

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub([]), $cardRepository);
        $result = $client->findAllPrintingsByName('Comfey');

        self::assertCount(2, $result);
        self::assertSame('swsh9-079', $result[0]->id);
        self::assertSame('sv02-051', $result[1]->id);
        self::assertSame('Comfey', $result[0]->name);
        self::assertSame('Comfey', $result[1]->name);
    }

    public function testFindAllPrintingsByNameFallsBackToHttpWhenLocalReturnsEmpty(): void
    {
        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findAllByNameEn')->willReturn([]);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function (string $method, string $url): ResponseInterface {
                $response = $this->createStub(ResponseInterface::class);

                if (str_contains($url, '/cards?name=')) {
                    $response->method('getStatusCode')->willReturn(200);
                    $response->method('toArray')->willReturn([
                        ['id' => 'swsh9-079', 'name' => 'Comfey'],
                    ]);

                    return $response;
                }

                if (str_ends_with($url, '/cards/swsh9-079')) {
                    $response->method('getStatusCode')->willReturn(200);
                    $response->method('toArray')->willReturn([
                        'id' => 'swsh9-079',
                        'name' => 'Comfey',
                        'category' => 'Pokemon',
                        'legal' => ['expanded' => true],
                    ]);

                    return $response;
                }

                $response->method('getStatusCode')->willReturn(404);

                return $response;
            });

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub([]), $cardRepository);
        $result = $client->findAllPrintingsByName('Comfey');

        self::assertCount(1, $result);
        self::assertSame('swsh9-079', $result[0]->id);
    }

    public function testBuildDtoFromEntityMapsTrainerFields(): void
    {
        $entity = $this->createTcgdexCardEntity(
            id: 'swsh9-132',
            localId: '132',
            setId: 'swsh9',
            serieId: 'swsh',
            name: ['en' => "Boss's Orders"],
            category: 'Trainer',
            trainerType: 'Supporter',
            isExpandedLegal: true,
            ptcgCode: 'BRS',
        );

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findBySetAndLocalId')
            ->willReturnCallback(static function (string $setId, string $localId) use ($entity): ?TcgdexCardEntity {
                if ('swsh9' === $setId && '132' === $localId) {
                    return $entity;
                }

                return null;
            });

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']), $cardRepository);
        $card = $client->findCard('BRS', '132');

        self::assertNotNull($card);
        self::assertSame('Trainer', $card->category);
        self::assertSame('Supporter', $card->trainerType);
        self::assertNull($card->hp);
        self::assertSame([], $card->abilities);
        self::assertSame([], $card->attacks);
    }

    public function testBuildDtoFromEntityMapsAbilitiesAndAttacks(): void
    {
        $entity = $this->createTcgdexCardEntity(
            id: 'swsh8-185',
            localId: '185',
            setId: 'swsh8',
            serieId: 'swsh',
            name: ['en' => 'Genesect V'],
            category: 'Pokemon',
            hp: 190,
            isExpandedLegal: true,
            abilities: [
                ['name' => ['en' => 'Fusion Strike System'], 'effect' => ['en' => 'Draw cards...'], 'type' => 'Ability'],
            ],
            attacks: [
                ['name' => ['en' => 'Techno Blast'], 'damage' => 210],
            ],
        );

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findBySetAndLocalId')
            ->willReturnCallback(static function (string $setId, string $localId) use ($entity): ?TcgdexCardEntity {
                if ('swsh8' === $setId && '185' === $localId) {
                    return $entity;
                }

                return null;
            });

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub(['FST' => 'swsh8']), $cardRepository);
        $card = $client->findCard('FST', '185');

        self::assertNotNull($card);
        self::assertSame(['Fusion Strike System'], $card->abilities);
        self::assertSame(['Techno Blast'], $card->attacks);
        self::assertSame(190, $card->hp);
    }

    public function testBuildDtoFromEntityMapsImageUrlFromEntity(): void
    {
        $entity = $this->createTcgdexCardEntity(
            id: 'swsh9-123',
            localId: '123',
            setId: 'swsh9',
            serieId: 'swsh',
            name: ['en' => 'Arceus VSTAR'],
            category: 'Pokemon',
        );

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findBySetAndLocalId')
            ->willReturnCallback(static function (string $setId, string $localId) use ($entity): ?TcgdexCardEntity {
                if ('swsh9' === $setId && '123' === $localId) {
                    return $entity;
                }

                return null;
            });

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']), $cardRepository);
        $card = $client->findCard('BRS', '123');

        self::assertNotNull($card);
        // Image URL is built from entity: https://assets.tcgdex.net/en/{serieId}/{setId}/{localId}/high.webp
        self::assertSame('https://assets.tcgdex.net/en/swsh/swsh9/123/high.webp', $card->imageUrl);
    }

    public function testBuildDtoFromEntityHandlesNullReleaseDate(): void
    {
        $entity = $this->createTcgdexCardEntity(
            id: 'swsh9-123',
            localId: '123',
            setId: 'swsh9',
            serieId: 'swsh',
            name: ['en' => 'Arceus VSTAR'],
            category: 'Pokemon',
            releaseDate: null,
        );

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findBySetAndLocalId')
            ->willReturnCallback(static function (string $setId, string $localId) use ($entity): ?TcgdexCardEntity {
                if ('swsh9' === $setId && '123' === $localId) {
                    return $entity;
                }

                return null;
            });

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $client = $this->createClientWithCardRepository($httpClient, $this->createRepositoryStub(['BRS' => 'swsh9']), $cardRepository);
        $card = $client->findCard('BRS', '123');

        self::assertNotNull($card);
        self::assertNull($card->setReleaseDate);
    }

    public function testFindCardByNameInAliasedSetReturnsNullForUnknownAlias(): void
    {
        $aliasRepository = $this->createStub(TcgdexSetAliasRepository::class);
        $aliasRepository->method('findTcgdexSetIdByAlias')->willReturn(null);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $client = $this->createClientWithAliasRepository($httpClient, $this->createRepositoryStub([]), $aliasRepository);

        $result = $client->findCardByNameInAliasedSet('SM8', 'Pikachu');

        self::assertNull($result);
    }

    public function testFindCardByNameInAliasedSetReturnsNullWhenNoCardMatchesInSet(): void
    {
        $aliasRepository = $this->createStub(TcgdexSetAliasRepository::class);
        $aliasRepository->method('findTcgdexSetIdByAlias')->willReturn('swsh9');

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findByNameEnAndSetId')->willReturn([]);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $client = new TcgdexApiClient(
            $httpClient,
            new ArrayAdapter(),
            $this->createRepositoryStub([]),
            $cardRepository,
            $aliasRepository,
            new CardNameMatcher(),
            new NullLogger(),
        );

        $result = $client->findCardByNameInAliasedSet('SM8', 'Nonexistent Card');

        self::assertNull($result);
    }

    public function testFindCardByNameInAliasedSetReturnsDtoWhenCardFound(): void
    {
        $entity = $this->createTcgdexCardEntity(
            id: 'swsh9-079',
            localId: '079',
            setId: 'swsh9',
            serieId: 'swsh',
            name: ['en' => 'Comfey'],
            category: 'Pokemon',
            hp: 70,
            isExpandedLegal: true,
            ptcgCode: 'BRS',
            releaseDate: new \DateTimeImmutable('2022-02-25'),
            officialCardCount: 172,
        );

        $aliasRepository = $this->createStub(TcgdexSetAliasRepository::class);
        $aliasRepository->method('findTcgdexSetIdByAlias')->willReturn('swsh9');

        $cardRepository = $this->createStub(TcgdexCardRepository::class);
        $cardRepository->method('findByNameEnAndSetId')->willReturn([$entity]);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $client = new TcgdexApiClient(
            $httpClient,
            new ArrayAdapter(),
            $this->createRepositoryStub([]),
            $cardRepository,
            $aliasRepository,
            new CardNameMatcher(),
            new NullLogger(),
        );

        $result = $client->findCardByNameInAliasedSet('S6K', 'Comfey');

        self::assertNotNull($result);
        self::assertSame('swsh9-079', $result->id);
        self::assertSame('Comfey', $result->name);
        self::assertSame('Pokemon', $result->category);
        self::assertSame(70, $result->hp);
        self::assertTrue($result->isExpandedLegal);
        self::assertSame('2022-02-25', $result->setReleaseDate);
        self::assertSame('BRS', $result->setCode);
        self::assertSame('079', $result->cardNumber);
        self::assertSame(172, $result->setOfficialCardCount);
    }

    /**
     * SIT denotes Silver Tempest (swsh12, 245 cards) and, after a mapping
     * rebuild against the wrong upstream snapshot, its 30-card Trainer Gallery
     * (swsh12.5tg) as well. An ordinary Silver Tempest number exists only in
     * the parent, so the number alone must settle it — this is the regression
     * that motivated candidate-based resolution.
     */
    public function testFindCardPicksTheOnlyCandidateSetContainingTheNumber(): void
    {
        $cardRepository = $this->createCardRepositoryStub([
            $this->createTcgdexCardEntity(
                id: 'swsh12-100',
                localId: '100',
                setId: 'swsh12',
                serieId: 'swsh',
                name: ['en' => 'Regidrago V'],
                ptcgCode: 'SIT',
            ),
        ]);

        $client = $this->createClientWithCardRepository(
            $this->createStub(HttpClientInterface::class),
            $this->createRepositoryStubWithCandidates(['SIT' => ['swsh12', 'swsh12.5tg']]),
            $cardRepository,
        );

        $card = $client->findCard('SIT', '100');

        self::assertNotNull($card);
        self::assertSame('swsh12-100', $card->id);
    }

    /**
     * RR is a true collision: Team Rocket Returns (ex7, 2004) and Rising Rivals
     * (pl2, 2009) both print a card at every number from 1 to 111, and they are
     * different cards. Only the name can separate them.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function collidingNameProvider(): iterable
    {
        yield 'name from the older set' => ["Team Rocket's Meowth", 'ex7-10'];
        yield 'name from the newer set' => ['Rampardos', 'pl2-10'];
    }

    #[DataProvider('collidingNameProvider')]
    public function testFindCardUsesCardNameToResolveTrueCollision(string $cardName, string $expectedId): void
    {
        $client = $this->createClientWithCardRepository(
            $this->createStub(HttpClientInterface::class),
            $this->createRepositoryStubWithCandidates(['RR' => ['ex7', 'pl2']]),
            $this->createCollidingRrCardRepository(),
        );

        $card = $client->findCard('RR', '10', $cardName);

        self::assertNotNull($card);
        self::assertSame($expectedId, $card->id);
    }

    /**
     * A French list reaches the enricher before the canonical English name is
     * applied, so the fold must consider every locale the card carries.
     */
    public function testFindCardResolvesCollisionUsingFrenchName(): void
    {
        $client = $this->createClientWithCardRepository(
            $this->createStub(HttpClientInterface::class),
            $this->createRepositoryStubWithCandidates(['RR' => ['ex7', 'pl2']]),
            $this->createCollidingRrCardRepository(),
        );

        $card = $client->findCard('RR', '10', 'Charpenti');

        self::assertNotNull($card);
        self::assertSame('pl2-10', $card->id);
    }

    /**
     * A name that resembles neither candidate carries no signal, so resolution
     * must fall back to the structural rule rather than pick the least-bad
     * match — and must say so in the log.
     */
    public function testFindCardIgnoresNameBelowSimilarityThreshold(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with('warning');

        $client = new TcgdexApiClient(
            $this->createStub(HttpClientInterface::class),
            new ArrayAdapter(),
            $this->createRepositoryStubWithCandidates(['RR' => ['ex7', 'pl2']]),
            $this->createCollidingRrCardRepository(),
            $this->createStub(TcgdexSetAliasRepository::class),
            new CardNameMatcher(),
            $logger,
        );

        $card = $client->findCard('RR', '10', 'Completely Unrelated Card');

        self::assertNotNull($card);
        // Neither set is a subset of the other, so the newer release wins.
        self::assertSame('pl2-10', $card->id);
    }

    /**
     * The gallery subsets ship with no image URL and their computed CDN path
     * 404s, so the gallery preference stays gated off and the parent wins.
     */
    public function testFindCardPrefersParentWhenGalleryHasNoImage(): void
    {
        $client = $this->createClientWithCardRepository(
            $this->createStub(HttpClientInterface::class),
            $this->createRepositoryStubWithCandidates(['ASR' => ['swsh10', 'swsh10.5tg']]),
            $this->createCardRepositoryStub($this->createAstralRadiancePair(galleryImageBaseUrl: null)),
        );

        $card = $client->findCard('ASR', 'TG01', 'Abomasnow');

        self::assertNotNull($card);
        self::assertSame('swsh10-TG01', $card->id);
    }

    /**
     * Once TCGdex backfills images for a gallery subset, a gallery-prefixed
     * number resolves to that subset with no code change.
     */
    public function testFindCardPrefersGalleryOnceItCarriesImages(): void
    {
        $client = $this->createClientWithCardRepository(
            $this->createStub(HttpClientInterface::class),
            $this->createRepositoryStubWithCandidates(['ASR' => ['swsh10', 'swsh10.5tg']]),
            $this->createCardRepositoryStub($this->createAstralRadiancePair(
                galleryImageBaseUrl: 'https://assets.tcgdex.net/en/swsh/swsh10.5tg/TG01',
            )),
        );

        $card = $client->findCard('ASR', 'TG01', 'Abomasnow');

        self::assertNotNull($card);
        self::assertSame('swsh10.5tg-TG01', $card->id);
    }

    /**
     * Production maps the gallery under a suffixed code (ASR-TG) while local
     * maps it under the parent code. Both conventions must resolve identically,
     * which is why a gallery-prefixed number also probes the suffixed sibling.
     */
    public function testFindCardResolvesGalleryUnderEitherMappingConvention(): void
    {
        $client = $this->createClientWithCardRepository(
            $this->createStub(HttpClientInterface::class),
            $this->createRepositoryStubWithCandidates([
                'ASR' => ['swsh10'],
                'ASR-TG' => ['swsh10.5tg'],
            ]),
            $this->createCardRepositoryStub($this->createAstralRadiancePair(
                galleryImageBaseUrl: 'https://assets.tcgdex.net/en/swsh/swsh10.5tg/TG01',
            )),
        );

        $card = $client->findCard('ASR', 'TG01', 'Abomasnow');

        self::assertNotNull($card);
        self::assertSame('swsh10.5tg-TG01', $card->id);
    }

    /**
     * The number-candidate ladder is a decreasing-confidence one, so an exact
     * hit in any candidate set must beat a zero-padded guess in another.
     */
    public function testFindCardPrefersExactNumberOverPaddedNumberInAnotherSet(): void
    {
        $cardRepository = $this->createCardRepositoryStub([
            $this->createTcgdexCardEntity(
                id: 'ex7-079',
                localId: '079',
                setId: 'ex7',
                serieId: 'ex',
                name: ['en' => 'Padded Match'],
                ptcgCode: 'RR',
            ),
            $this->createTcgdexCardEntity(
                id: 'pl2-79',
                localId: '79',
                setId: 'pl2',
                serieId: 'pl',
                name: ['en' => 'Exact Match'],
                ptcgCode: 'RR',
            ),
        ]);

        $client = $this->createClientWithCardRepository(
            $this->createStub(HttpClientInterface::class),
            $this->createRepositoryStubWithCandidates(['RR' => ['ex7', 'pl2']]),
            $cardRepository,
        );

        $card = $client->findCard('RR', '79');

        self::assertNotNull($card);
        self::assertSame('pl2-79', $card->id);
    }

    /**
     * A code that resolves cleanly by number is the common case and must not
     * produce log noise.
     */
    public function testFindCardDoesNotLogWhenNumberDisambiguates(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('log');

        $client = new TcgdexApiClient(
            $this->createStub(HttpClientInterface::class),
            new ArrayAdapter(),
            $this->createRepositoryStubWithCandidates(['RR' => ['ex7', 'pl2']]),
            $this->createCardRepositoryStub([
                $this->createTcgdexCardEntity(
                    id: 'pl2-113',
                    localId: '113',
                    setId: 'pl2',
                    serieId: 'pl',
                    name: ['en' => 'Luxury Ball'],
                    ptcgCode: 'RR',
                ),
            ]),
            $this->createStub(TcgdexSetAliasRepository::class),
            new CardNameMatcher(),
            $logger,
        );

        $card = $client->findCard('RR', '113');

        self::assertNotNull($card);
        self::assertSame('pl2-113', $card->id);
    }

    /**
     * Trainer Gallery cards live in the parent set too, so the HTTP fallback
     * must also narrow across candidates rather than commit to one set.
     */
    public function testFindCardNarrowsCandidatesOverHttp(): void
    {
        $httpClient = $this->createCardMockClient([
            'ex7-113' => ['status' => 404],
            'pl2-113' => [
                'status' => 200,
                'body' => [
                    'id' => 'pl2-113',
                    'name' => 'Luxury Ball',
                    'category' => 'Trainer',
                    'image' => 'https://assets.tcgdex.net/en/pl/pl2/113',
                    'legal' => ['expanded' => true],
                ],
            ],
        ]);

        $client = $this->createClient(
            $httpClient,
            $this->createRepositoryStubWithCandidates(['RR' => ['ex7', 'pl2']]),
        );

        $card = $client->findCard('RR', '113');

        self::assertNotNull($card);
        self::assertSame('pl2-113', $card->id);
    }

    /**
     * Both Astral Radiance printings of TG01 are the same card under the same
     * name — only the image availability differs.
     *
     * @return list<TcgdexCardEntity>
     */
    private function createAstralRadiancePair(?string $galleryImageBaseUrl): array
    {
        $parent = $this->createTcgdexCardEntity(
            id: 'swsh10-TG01',
            localId: 'TG01',
            setId: 'swsh10',
            serieId: 'swsh',
            name: ['en' => 'Abomasnow'],
            ptcgCode: 'ASR',
        );
        $parent->setImageBaseUrl('https://assets.tcgdex.net/en/swsh/swsh10/TG01');

        $gallery = $this->createTcgdexCardEntity(
            id: 'swsh10.5tg-TG01',
            localId: 'TG01',
            setId: 'swsh10.5tg',
            serieId: 'swsh',
            name: ['en' => 'Abomasnow'],
            ptcgCode: 'ASR',
        );
        $gallery->setImageBaseUrl($galleryImageBaseUrl);

        return [$parent, $gallery];
    }

    /**
     * ex7-10 and pl2-10 are unrelated cards printed at the same number.
     */
    private function createCollidingRrCardRepository(): TcgdexCardRepository
    {
        return $this->createCardRepositoryStub([
            $this->createTcgdexCardEntity(
                id: 'ex7-10',
                localId: '10',
                setId: 'ex7',
                serieId: 'ex',
                name: ['en' => "Team Rocket's Meowth"],
                ptcgCode: 'RR',
                releaseDate: new \DateTimeImmutable('2004-11-01'),
            ),
            $this->createTcgdexCardEntity(
                id: 'pl2-10',
                localId: '10',
                setId: 'pl2',
                serieId: 'pl',
                name: ['en' => 'Rampardos', 'fr' => 'Charpenti'],
                ptcgCode: 'RR',
                releaseDate: new \DateTimeImmutable('2009-05-16'),
            ),
        ]);
    }

    /**
     * A card repository stub backed by an in-memory list, keyed the same way
     * findBySetAndLocalId() queries it.
     *
     * @param list<TcgdexCardEntity> $entities
     */
    private function createCardRepositoryStub(array $entities): TcgdexCardRepository
    {
        $indexed = [];

        foreach ($entities as $entity) {
            $indexed[$entity->getSet()->getId().'|'.$entity->getLocalId()] = $entity;
        }

        $repository = $this->createStub(TcgdexCardRepository::class);
        $repository->method('findBySetAndLocalId')->willReturnCallback(
            static fn (string $setId, string $localId): ?TcgdexCardEntity => $indexed[$setId.'|'.$localId] ?? null,
        );

        return $repository;
    }

    private function createClient(
        HttpClientInterface $httpClient,
        TcgdexSetMappingRepository $repository,
        ?LoggerInterface $logger = null,
    ): TcgdexApiClient {
        return new TcgdexApiClient(
            $httpClient,
            new ArrayAdapter(),
            $repository,
            $this->createStub(TcgdexCardRepository::class),
            $this->createStub(TcgdexSetAliasRepository::class),
            new CardNameMatcher(),
            $logger ?? new NullLogger(),
        );
    }

    /**
     * Creates a repository stub with the given forward mapping.
     *
     * Each code resolves to exactly one set, so lookups exercise the
     * unambiguous path. Use createRepositoryStubWithCandidates() for collisions.
     *
     * @param array<string, string> $forwardMapping PTCG code → TCGdex set ID
     */
    private function createRepositoryStub(array $forwardMapping): TcgdexSetMappingRepository
    {
        $repository = $this->createStub(TcgdexSetMappingRepository::class);
        $repository->method('getForwardMapping')->willReturn($forwardMapping);
        $repository->method('getReverseMapping')->willReturn(array_flip($forwardMapping));
        $repository->method('getForwardCandidates')->willReturn(array_map(
            static fn (string $setId): array => [$setId],
            $forwardMapping,
        ));

        return $repository;
    }

    /**
     * Creates a repository stub where a PTCG code may denote several sets.
     *
     * @param array<string, list<string>> $candidates PTCG code → TCGdex set IDs
     */
    private function createRepositoryStubWithCandidates(array $candidates): TcgdexSetMappingRepository
    {
        $repository = $this->createStub(TcgdexSetMappingRepository::class);
        $repository->method('getForwardCandidates')->willReturn($candidates);
        $repository->method('getForwardMapping')->willReturn(array_map(
            static fn (array $setIds): string => $setIds[0],
            $candidates,
        ));
        $repository->method('getReverseMapping')->willReturn([]);

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

    private function createClientWithCardRepository(
        HttpClientInterface $httpClient,
        TcgdexSetMappingRepository $setMappingRepository,
        TcgdexCardRepository $cardRepository,
    ): TcgdexApiClient {
        return new TcgdexApiClient(
            $httpClient,
            new ArrayAdapter(),
            $setMappingRepository,
            $cardRepository,
            $this->createStub(TcgdexSetAliasRepository::class),
            new CardNameMatcher(),
            new NullLogger(),
        );
    }

    private function createClientWithAliasRepository(
        HttpClientInterface $httpClient,
        TcgdexSetMappingRepository $setMappingRepository,
        TcgdexSetAliasRepository $aliasRepository,
    ): TcgdexApiClient {
        return new TcgdexApiClient(
            $httpClient,
            new ArrayAdapter(),
            $setMappingRepository,
            $this->createStub(TcgdexCardRepository::class),
            $aliasRepository,
            new CardNameMatcher(),
            new NullLogger(),
        );
    }

    /**
     * Creates a real TcgdexCard entity with the given properties for local-first tests.
     *
     * @param array<string, mixed>       $name
     * @param list<array<string, mixed>> $abilities
     * @param list<array<string, mixed>> $attacks
     * @param list<string>               $types
     */
    private function createTcgdexCardEntity(
        string $id,
        string $localId,
        string $setId,
        string $serieId,
        array $name = [],
        string $category = '',
        ?int $hp = null,
        ?string $trainerType = null,
        ?string $rarity = null,
        bool $isExpandedLegal = false,
        ?string $ptcgCode = null,
        ?\DateTimeImmutable $releaseDate = null,
        ?int $officialCardCount = null,
        ?int $cardmarketProductId = null,
        ?int $tcgplayerProductId = null,
        array $abilities = [],
        array $attacks = [],
        array $types = [],
    ): TcgdexCardEntity {
        $serie = new TcgdexSerie($serieId);
        $set = new TcgdexSet($setId, $serie);
        $set->setPtcgCode($ptcgCode);
        $set->setReleaseDate($releaseDate);
        $set->setOfficialCardCount($officialCardCount);

        $entity = new TcgdexCardEntity($id, $set, $localId);
        $entity->setName($name);
        $entity->setCategory($category);
        $entity->setHp($hp);
        $entity->setTrainerType($trainerType);
        $entity->setRarity($rarity);
        $entity->setIsExpandedLegal($isExpandedLegal);
        $entity->setCardmarketProductId($cardmarketProductId);
        $entity->setTcgplayerProductId($tcgplayerProductId);
        $entity->setAbilities($abilities);
        $entity->setAttacks($attacks);
        $entity->setTypes($types);

        return $entity;
    }
}
