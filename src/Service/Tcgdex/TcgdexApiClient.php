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

use App\Entity\TcgdexCard as TcgdexCardEntity;
use App\Repository\TcgdexCardRepository;
use App\Repository\TcgdexSetAliasRepository;
use App\Repository\TcgdexSetMappingRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for TCGdex card data — local-first with HTTP API fallback.
 *
 * Resolution order: local tcgdex_* tables → TCGdex REST API (v2).
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
        // PTCGO (older client) uses short promo codes without the "PR-" prefix
        'SVP' => 'svp',
        'SWP' => 'swshp',
        'SMP' => 'smp',
        'XYP' => 'xyp',
        'BWP' => 'bwp',
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
        private readonly HttpClientInterface $tcgdexClient,
        private readonly CacheInterface $cache,
        private readonly TcgdexSetMappingRepository $setMappingRepository,
        private readonly TcgdexCardRepository $tcgdexCardRepository,
        private readonly TcgdexSetAliasRepository $setAliasRepository,
    ) {
    }

    /**
     * Returns the PTCG code → TCGdex set ID mapping from the database.
     *
     * Returns an empty array if the mappings have not been built yet.
     *
     * @return array<string, string>
     */
    public function getSetMapping(): array
    {
        return array_merge(
            self::STATIC_OVERRIDES,
            $this->setMappingRepository->getForwardMapping(),
        );
    }

    /**
     * Returns the TCGdex set ID → PTCG code reverse mapping from the database.
     *
     * Returns an empty array if the mappings have not been built yet.
     *
     * @return array<string, string>
     */
    public function getReverseSetMapping(): array
    {
        return array_merge(
            array_flip(self::STATIC_OVERRIDES),
            $this->setMappingRepository->getReverseMapping(),
        );
    }

    public function hasMappings(): bool
    {
        return !$this->setMappingRepository->isEmpty();
    }

    /**
     * Looks up a card by its PTCG set code and card number.
     *
     * Resolution order: local tcgdex_* tables first, then HTTP API fallback.
     */
    public function findCard(string $ptcgSetCode, string $cardNumber): ?TcgdexCard
    {
        $normalizedSetCode = strtoupper($ptcgSetCode);
        $normalizedNumber = $cardNumber;

        // Trainer Gallery: "ASR-TG" → set "ASR", number "TG30"
        if (str_ends_with($normalizedSetCode, '-TG')) {
            $normalizedSetCode = substr($normalizedSetCode, 0, -3);
            $normalizedNumber = 'TG'.$cardNumber;
        }

        // Resolve PTCG set code → TCGdex set ID
        $mapping = $this->getSetMapping();
        $setId = $mapping[$normalizedSetCode] ?? null;

        // Fallback: check alias table (Japanese/legacy set codes → international equivalent)
        if (null === $setId) {
            $setId = $this->setAliasRepository->findTcgdexSetIdByAlias($normalizedSetCode);
        }

        if (null === $setId) {
            return null;
        }

        // Promo sets use era-prefixed card numbers in TCGdex (e.g. XY177, SWSH001)
        $prefix = self::PROMO_CARD_NUMBER_PREFIXES[$setId] ?? null;
        $lookupNumber = null !== $prefix ? $prefix.$normalizedNumber : $normalizedNumber;

        // Build candidate local IDs for the fallback chain
        $candidates = [$lookupNumber];

        $strippedNumber = preg_replace('/[a-z]+$/i', '', $lookupNumber) ?? $lookupNumber;

        if ($strippedNumber !== $lookupNumber) {
            $candidates[] = $strippedNumber;
        }

        if (\strlen($strippedNumber) < 3 && ctype_digit($strippedNumber)) {
            $candidates[] = str_pad($strippedNumber, 3, '0', \STR_PAD_LEFT);
        }

        // Layer 1: Local database lookup
        foreach ($candidates as $candidateLocalId) {
            $entity = $this->tcgdexCardRepository->findBySetAndLocalId($setId, $candidateLocalId);

            if (null !== $entity) {
                return $this->buildDtoFromEntity($entity);
            }
        }

        // Layer 2: HTTP API fallback
        foreach ($candidates as $candidateLocalId) {
            $card = $this->fetchCard($setId, $candidateLocalId);

            if (null !== $card) {
                return $card;
            }
        }

        return null;
    }

    /**
     * Searches for a card by name across all printings and returns the first available image URL.
     *
     * Used as a fallback when the primary card entry has no image field.
     */
    public function findImageByName(string $cardName): ?string
    {
        $url = self::BASE_URL.'/cards?name='.urlencode($cardName);
        $response = $this->tcgdexClient->request('GET', $url);

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
     * Build a TcgdexCard DTO from a local TcgdexCard entity.
     */
    private function buildDtoFromEntity(TcgdexCardEntity $entity): TcgdexCard
    {
        $set = $entity->getSet();

        return new TcgdexCard(
            id: $entity->getId(),
            name: $entity->getLocalizedName('en') ?? '',
            category: $entity->getCategory(),
            trainerType: $entity->getTrainerType(),
            imageUrl: $entity->getImageUrl(),
            isExpandedLegal: $entity->isExpandedLegal(),
            hp: $entity->getHp(),
            abilities: $entity->getAbilityNamesEn(),
            attacks: $entity->getAttackNamesEn(),
            rarity: $entity->getRarity(),
            setReleaseDate: $set->getReleaseDate()?->format('Y-m-d'),
            setCode: $set->getPtcgCode(),
            cardNumber: $entity->getLocalId(),
            cardmarketProductId: $entity->getCardmarketProductId(),
            tcgplayerProductId: $entity->getTcgplayerProductId(),
            setOfficialCardCount: $set->getOfficialCardCount(),
        );
    }

    private function fetchCard(string $setId, string $cardNumber): ?TcgdexCard
    {
        return $this->fetchCardById(\sprintf('%s-%s', $setId, $cardNumber));
    }

    /**
     * Fetches all printings of a card by name, checking local database first.
     *
     * @see docs/features.md F6.10 — Card identity and printing model
     *
     * @return list<TcgdexCard>
     */
    public function findAllPrintingsByName(string $cardName): array
    {
        // Layer 1: Local database
        $localEntities = $this->tcgdexCardRepository->findAllByNameEn($cardName);

        if ([] !== $localEntities) {
            return array_map($this->buildDtoFromEntity(...), $localEntities);
        }

        // Layer 2: HTTP API fallback
        $url = self::BASE_URL.'/cards?name='.urlencode($cardName);
        $response = $this->tcgdexClient->request('GET', $url);

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
        $response = $this->tcgdexClient->request('GET', $url);

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

        /** @var list<string> $abilities */
        $abilities = [];

        if (isset($data['abilities']) && \is_array($data['abilities'])) {
            foreach ($data['abilities'] as $ability) {
                if (\is_array($ability) && isset($ability['name']) && \is_string($ability['name'])) {
                    $abilities[] = $ability['name'];
                }
            }
        }

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

        $setOfficialCardCount = null;

        if (isset($data['set']) && \is_array($data['set'])) {
            if (isset($data['set']['releaseDate']) && \is_string($data['set']['releaseDate'])) {
                $setReleaseDate = $data['set']['releaseDate'];
            }

            if (isset($data['set']['id']) && \is_string($data['set']['id'])) {
                $setId = $data['set']['id'];
            }

            if (isset($data['set']['cardCount']) && \is_array($data['set']['cardCount'])) {
                $official = $data['set']['cardCount']['official'] ?? null;

                if (\is_int($official)) {
                    $setOfficialCardCount = $official;
                }
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

        // Parse pricing and marketplace IDs
        $priceInCents = $this->parsePriceInCents($data);
        [$cardmarketProductId, $tcgplayerProductId] = $this->parseMarketplaceIds($data);

        return new TcgdexCard(
            id: $id,
            name: $name,
            category: $category,
            trainerType: $trainerType,
            imageUrl: $imageUrl,
            isExpandedLegal: $isExpandedLegal,
            hp: $hp,
            abilities: $abilities,
            attacks: $attacks,
            rarity: $rarity,
            setReleaseDate: $setReleaseDate,
            setCode: $setCode,
            cardNumber: $localId,
            priceInCents: $priceInCents,
            cardmarketProductId: $cardmarketProductId,
            tcgplayerProductId: $tcgplayerProductId,
            setOfficialCardCount: $setOfficialCardCount,
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
        $response = $this->tcgdexClient->request('GET', $url);

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

    /**
     * Extract marketplace product IDs from TCGdex pricing data.
     *
     * @param array<string, mixed> $data
     *
     * @return array{0: ?int, 1: ?int} [cardmarketProductId, tcgplayerProductId]
     */
    private function parseMarketplaceIds(array $data): array
    {
        $cardmarketId = null;
        $tcgplayerId = null;

        if (!isset($data['pricing']) || !\is_array($data['pricing'])) {
            return [$cardmarketId, $tcgplayerId];
        }

        /** @var array<string, mixed> $pricing */
        $pricing = $data['pricing'];

        if (isset($pricing['cardmarket']) && \is_array($pricing['cardmarket'])) {
            $idProduct = $pricing['cardmarket']['idProduct'] ?? null;

            if (\is_int($idProduct)) {
                $cardmarketId = $idProduct;
            }
        }

        // TCGPlayer stores productId per variant — take the first one found
        if (isset($pricing['tcgplayer']) && \is_array($pricing['tcgplayer'])) {
            foreach ($pricing['tcgplayer'] as $key => $value) {
                if (\is_array($value) && isset($value['productId']) && \is_int($value['productId'])) {
                    $tcgplayerId = $value['productId'];

                    break;
                }
            }
        }

        return [$cardmarketId, $tcgplayerId];
    }
}
