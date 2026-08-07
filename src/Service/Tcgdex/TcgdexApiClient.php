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
use Psr\Log\LoggerInterface;
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

    /**
     * Card-number prefixes that mark a gallery subset printing.
     *
     * TCGdex splits Trainer Gallery (TG), Galarian Gallery (GG), Shiny Vault
     * (SV) and Classic Collection (CC) cards into their own set, which either
     * reuses the parent's PTCG code (ASR → swsh10 + swsh10.5tg) or carries a
     * suffixed one (ASR-TG → swsh10.5tg) depending on the upstream snapshot the
     * mapping was last rebuilt against. The prefix is what tells them apart.
     *
     * Radiant Collection (RC) subsets (Generations GEN-RC, Legendary Treasures
     * LTR-RC) follow the same suffixed-code convention in PTCG Live exports,
     * but TCGdex keeps their cards inside the parent set under RC-prefixed
     * local IDs (GEN-RC 27 → g1-RC27) rather than in a dedicated subset.
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     */
    private const array GALLERY_NUMBER_PREFIXES = ['TG', 'GG', 'SV', 'CC', 'RC'];

    /**
     * Number of ordered transformations tried when looking up a card number.
     *
     * @see buildLocalIdCandidates()
     */
    private const int NUMBER_CANDIDATE_RANKS = 4;

    /** Upper bound on how many candidate sets the HTTP fallback will probe. */
    private const int MAX_HTTP_CANDIDATE_SETS = 2;

    /**
     * Memoized PTCG code → TCGdex set IDs map.
     *
     * findCard() runs once per deck card, so without this the forward mapping
     * would be re-queried for every line of every imported list.
     *
     * @var array<string, list<string>>|null
     */
    private ?array $forwardCandidates = null;

    public function __construct(
        private readonly HttpClientInterface $tcgdexClient,
        private readonly CacheInterface $cache,
        private readonly TcgdexSetMappingRepository $setMappingRepository,
        private readonly TcgdexCardRepository $tcgdexCardRepository,
        private readonly TcgdexSetAliasRepository $setAliasRepository,
        private readonly CardNameMatcher $cardNameMatcher,
        private readonly LoggerInterface $logger,
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
     * A PTCG code does not identify one TCGdex set. A set and its gallery subset
     * may share a code (ASR → swsh10 + swsh10.5tg), and two unrelated sets may
     * have reused an abbreviation decades apart (RR → ex7 Team Rocket Returns +
     * pl2 Rising Rivals). Resolution therefore narrows by card number first and
     * only falls back to the card name when the number matches in several sets.
     *
     * Resolution strategy:
     * 1. PTCGL set code + card number (international, exact match)
     * 2. If set code is unrecognized, returns null — the enricher handles
     *    Asian alias resolution separately via findCardByNameInAliasedSet().
     *
     * @param string|null $cardName the name as written in the source list, used
     *                              only to break ties between candidate sets
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     */
    public function findCard(string $ptcgSetCode, string $cardNumber, ?string $cardName = null): ?TcgdexCard
    {
        $normalizedSetCode = strtoupper($ptcgSetCode);
        $normalizedNumber = $cardNumber;

        // Gallery suffix: "ASR-TG" → set "ASR", number "TG30"
        $suffixMarker = $this->extractGallerySuffix($normalizedSetCode);

        if (null !== $suffixMarker) {
            $normalizedSetCode = substr($normalizedSetCode, 0, -(\strlen($suffixMarker) + 1));
            $normalizedNumber = $suffixMarker.$cardNumber;
        }

        // Resolve PTCG set code → every TCGdex set it may denote
        $setIds = $this->resolveSetCandidates($normalizedSetCode, $normalizedNumber);

        if ([] === $setIds) {
            return null;
        }

        $chains = [];

        foreach ($setIds as $setId) {
            $chains[$setId] = $this->buildLocalIdCandidates($setId, $normalizedNumber);
        }

        // Layer 1: Local database lookup
        $card = $this->resolveFromLocalDatabase($setIds, $chains, $cardName, $normalizedSetCode, $normalizedNumber);

        if (null !== $card) {
            return $card;
        }

        // Layer 2: HTTP API fallback
        return $this->resolveFromApi($setIds, $chains, $cardName, $normalizedSetCode, $normalizedNumber);
    }

    /**
     * Every TCGdex set a PTCG code may denote, in stable order.
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     *
     * @return list<string>
     */
    public function getSetCandidates(string $ptcgSetCode, string $cardNumber = ''): array
    {
        return $this->resolveSetCandidates(strtoupper($ptcgSetCode), $cardNumber);
    }

    /**
     * @return list<string>
     */
    private function resolveSetCandidates(string $normalizedSetCode, string $cardNumber): array
    {
        $candidates = $this->getForwardCandidates()[$normalizedSetCode] ?? [];

        $staticSetId = self::STATIC_OVERRIDES[$normalizedSetCode] ?? null;

        if (null !== $staticSetId) {
            $candidates[] = $staticSetId;
        }

        // A gallery subset carries either its parent's code (ASR) or a suffixed
        // one (ASR-TG) depending on the TCGdex snapshot the mapping was last
        // rebuilt against — prod and local currently disagree. Folding in the
        // suffixed sibling makes both conventions resolve identically.
        $marker = $this->galleryMarkerOf($cardNumber);

        if (null !== $marker) {
            foreach ($this->getForwardCandidates()[$normalizedSetCode.'-'.$marker] ?? [] as $siblingSetId) {
                $candidates[] = $siblingSetId;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Ordered number interpretations for one candidate set, keyed by confidence rank.
     *
     * Rank 0 is the number exactly as printed (carrying the set's promo era
     * prefix when it has one); every later rank is a guess that papers over
     * inconsistent zero-padding and alternate-art suffixes between PTCGL, the
     * official banned-card list, and TCGdex. Ranks are aligned across sets so a
     * guessed hit in one set can never outrank an exact hit in another.
     *
     * @return array<int, string> rank → local ID
     */
    private function buildLocalIdCandidates(string $setId, string $normalizedNumber): array
    {
        // Promo sets use era-prefixed card numbers in TCGdex (e.g. XY177, SWSH001).
        // Skip the prepend when the upstream value already includes the prefix
        // (the official banned-card list ships "PR-SW SWSH022" verbatim).
        $prefix = self::PROMO_CARD_NUMBER_PREFIXES[$setId] ?? null;
        $lookupNumber = null !== $prefix && !str_starts_with($normalizedNumber, $prefix)
            ? $prefix.$normalizedNumber
            : $normalizedNumber;

        $candidates = [0 => $lookupNumber];

        $strippedNumber = preg_replace('/[a-z]+$/i', '', $lookupNumber) ?? $lookupNumber;

        if ($strippedNumber !== $lookupNumber) {
            $candidates[1] = $strippedNumber;
        }

        if (\strlen($strippedNumber) < 3 && ctype_digit($strippedNumber)) {
            $candidates[2] = str_pad($strippedNumber, 3, '0', \STR_PAD_LEFT);
        }

        // Strip leading zeros for padded numerics: "022" → "22", "083" → "83".
        // TCGdex stores some sets (RCL, EVS, …) without leading zeros while
        // other sources pad to 3 digits.
        if (ctype_digit($strippedNumber) && \strlen($strippedNumber) > 1 && '0' === $strippedNumber[0]) {
            $candidates[3] = ltrim($strippedNumber, '0') ?: '0';
        }

        return $candidates;
    }

    /**
     * @param list<string>                     $setIds
     * @param array<string, array<int,string>> $chains
     */
    private function resolveFromLocalDatabase(
        array $setIds,
        array $chains,
        ?string $cardName,
        string $ptcgSetCode,
        string $cardNumber,
    ): ?TcgdexCard {
        for ($rank = 0; $rank < self::NUMBER_CANDIDATE_RANKS; ++$rank) {
            $entities = [];
            $descriptors = [];

            foreach ($setIds as $setId) {
                $localId = $chains[$setId][$rank] ?? null;

                if (null === $localId) {
                    continue;
                }

                $entity = $this->tcgdexCardRepository->findBySetAndLocalId($setId, $localId);

                if (null === $entity) {
                    continue;
                }

                $entities[] = $entity;
                $descriptors[] = [
                    'setId' => $setId,
                    'names' => self::localizedNames($entity),
                    'hasImage' => null !== $entity->getImageBaseUrl(),
                    'releaseDate' => $entity->getSet()->getReleaseDate()?->format('Y-m-d') ?? '',
                ];
            }

            if ([] === $descriptors) {
                continue;
            }

            $index = $this->selectMatchIndex($descriptors, $cardName, $ptcgSetCode, $cardNumber);

            return $this->buildDtoFromEntity($entities[$index]);
        }

        return null;
    }

    /**
     * @param list<string>                     $setIds
     * @param array<string, array<int,string>> $chains
     */
    private function resolveFromApi(
        array $setIds,
        array $chains,
        ?string $cardName,
        string $ptcgSetCode,
        string $cardNumber,
    ): ?TcgdexCard {
        // Every confirmed collision is a pair, so this cap is a no-op today. It
        // exists so a future upstream change cannot turn one deck line into an
        // unbounded burst of API calls.
        if (\count($setIds) > self::MAX_HTTP_CANDIDATE_SETS) {
            $this->logger->debug('Capping TCGdex HTTP lookup for {ptcgCode} {number} to {cap} of {total} candidate sets.', [
                'ptcgCode' => $ptcgSetCode,
                'number' => $cardNumber,
                'cap' => self::MAX_HTTP_CANDIDATE_SETS,
                'total' => \count($setIds),
            ]);

            $setIds = \array_slice($setIds, 0, self::MAX_HTTP_CANDIDATE_SETS);
        }

        for ($rank = 0; $rank < self::NUMBER_CANDIDATE_RANKS; ++$rank) {
            $cards = [];
            $descriptors = [];

            foreach ($setIds as $setId) {
                $localId = $chains[$setId][$rank] ?? null;

                if (null === $localId) {
                    continue;
                }

                $card = $this->fetchCard($setId, $localId);

                if (null === $card) {
                    continue;
                }

                $cards[] = $card;
                $descriptors[] = [
                    'setId' => $setId,
                    // The API is queried on the English endpoint, so French
                    // disambiguation is local-database only.
                    'names' => self::nonEmptyNames([$card->name]),
                    'hasImage' => null !== $card->imageUrl,
                    'releaseDate' => $card->setReleaseDate ?? '',
                ];
            }

            if ([] === $descriptors) {
                continue;
            }

            return $cards[$this->selectMatchIndex($descriptors, $cardName, $ptcgSetCode, $cardNumber)];
        }

        return null;
    }

    /**
     * Picks the winning candidate when a card number matched in several sets.
     *
     * Ladder, in order: exact name → best similarity above the threshold →
     * structural preference. The card name is only consulted as a tiebreaker;
     * it can never override a set that the number already singled out.
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     *
     * @param non-empty-list<array{setId: string, names: list<string>, hasImage: bool, releaseDate: string}> $descriptors
     */
    private function selectMatchIndex(array $descriptors, ?string $cardName, string $ptcgSetCode, string $cardNumber): int
    {
        // The number alone resolved it — the common case, and never worth a log line.
        if (1 === \count($descriptors)) {
            return 0;
        }

        $indexes = array_keys($descriptors);

        if (null !== $cardName && '' !== $cardName) {
            $exact = array_values(array_filter(
                $indexes,
                fn (int $index): bool => $this->hasExactName($descriptors[$index]['names'], $cardName),
            ));

            if (1 === \count($exact)) {
                $this->logResolution('debug', 'exact name match', $descriptors, $exact[0], $ptcgSetCode, $cardNumber, $cardName);

                return $exact[0];
            }

            if ([] !== $exact) {
                // Several sets print the same card at the same number — the
                // gallery case. Narrow, then let the structural rule decide.
                $indexes = $exact;
            } else {
                $closest = $this->closestByName($descriptors, $indexes, $cardName);

                if (null !== $closest) {
                    $this->logResolution('debug', 'closest name match', $descriptors, $closest, $ptcgSetCode, $cardNumber, $cardName);

                    return $closest;
                }
            }
        }

        $winner = $this->preferStructurally($descriptors, $indexes, $cardNumber);

        if (1 < \count($indexes)) {
            $this->logResolution('warning', 'structural preference', $descriptors, $winner, $ptcgSetCode, $cardNumber, $cardName);
        }

        return $winner;
    }

    /**
     * The single closest name above MINIMUM_SIMILARITY, or null when the name
     * carries no usable signal — no candidate clears the threshold, or two tie.
     *
     * Returning null rather than "the least bad" guarantees a garbled name can
     * never do worse than supplying no name at all.
     *
     * @param non-empty-list<array{setId: string, names: list<string>, hasImage: bool, releaseDate: string}> $descriptors
     * @param list<int>                                                                                      $indexes
     */
    private function closestByName(array $descriptors, array $indexes, string $cardName): ?int
    {
        $best = null;
        $bestScore = CardNameMatcher::MINIMUM_SIMILARITY;
        $tied = false;

        foreach ($indexes as $index) {
            $score = 0.0;

            foreach ($descriptors[$index]['names'] as $name) {
                $score = max($score, $this->cardNameMatcher->score($cardName, $name));
            }

            if ($score > $bestScore) {
                $best = $index;
                $bestScore = $score;
                $tied = false;

                continue;
            }

            if (null !== $best && $score === $bestScore) {
                $tied = true;
            }
        }

        return $tied ? null : $best;
    }

    /**
     * Deterministic preference when the name cannot separate the candidates.
     *
     * A gallery-prefixed number (TG/GG/SV/CC) belongs to the dedicated gallery
     * set — but only when that set actually carries card images. TCGdex
     * currently ships every gallery subset with a null image URL, and the
     * computed CDN fallback 404s for those set IDs, so the gate keeps the
     * preference dormant until upstream backfills them. Everything else prefers
     * the parent expansion, whose ID is a strict prefix of the subset's
     * (swsh10 / swsh10.5tg, cel25 / cel25cc).
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     *
     * @param non-empty-list<array{setId: string, names: list<string>, hasImage: bool, releaseDate: string}> $descriptors
     * @param list<int>                                                                                      $indexes
     */
    private function preferStructurally(array $descriptors, array $indexes, string $cardNumber): int
    {
        $marker = $this->galleryMarkerOf($cardNumber);

        if (null !== $marker) {
            $galleries = array_values(array_filter(
                $indexes,
                fn (int $index): bool => $descriptors[$index]['hasImage']
                    && str_ends_with($descriptors[$index]['setId'], strtolower($marker))
                    && $this->isSubsetOfAnother($descriptors[$index]['setId'], $descriptors, $indexes),
            ));

            if (1 === \count($galleries)) {
                return $galleries[0];
            }
        }

        $inPlay = $indexes;

        usort($indexes, function (int $first, int $second) use ($descriptors, $inPlay): int {
            $firstSetId = $descriptors[$first]['setId'];
            $secondSetId = $descriptors[$second]['setId'];

            // Parents (nothing in play is a prefix of them) come first.
            $byDepth = $this->countParents($firstSetId, $descriptors, $inPlay)
                <=> $this->countParents($secondSetId, $descriptors, $inPlay);

            if (0 !== $byDepth) {
                return $byDepth;
            }

            // Unrelated sets sharing a code (RR → ex7, pl2): newest wins.
            $byRelease = $descriptors[$second]['releaseDate'] <=> $descriptors[$first]['releaseDate'];

            return 0 !== $byRelease ? $byRelease : $firstSetId <=> $secondSetId;
        });

        return $indexes[0];
    }

    /**
     * How many other candidates are a strict prefix of this set ID.
     *
     * Zero means "no candidate is my parent", i.e. this is the top-level
     * expansion. Counting rather than comparing pairwise keeps the ordering a
     * well-defined total order that usort() can rely on.
     *
     * @param non-empty-list<array{setId: string, names: list<string>, hasImage: bool, releaseDate: string}> $descriptors
     * @param list<int>                                                                                      $indexes
     */
    private function countParents(string $setId, array $descriptors, array $indexes): int
    {
        $parents = 0;

        foreach ($indexes as $index) {
            $other = $descriptors[$index]['setId'];

            if ($other !== $setId && str_starts_with($setId, $other)) {
                ++$parents;
            }
        }

        return $parents;
    }

    /**
     * @param non-empty-list<array{setId: string, names: list<string>, hasImage: bool, releaseDate: string}> $descriptors
     * @param list<int>                                                                                      $indexes
     */
    private function isSubsetOfAnother(string $setId, array $descriptors, array $indexes): bool
    {
        return $this->countParents($setId, $descriptors, $indexes) > 0;
    }

    /**
     * @param list<string> $names
     */
    private function hasExactName(array $names, string $cardName): bool
    {
        foreach ($names as $name) {
            if ($this->cardNameMatcher->isExactMatch($cardName, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The gallery marker a card number carries, if any (TG01 → "TG").
     */
    private function galleryMarkerOf(string $cardNumber): ?string
    {
        $normalized = strtoupper($cardNumber);

        foreach (self::GALLERY_NUMBER_PREFIXES as $marker) {
            if (str_starts_with($normalized, $marker) && \strlen($normalized) > \strlen($marker)) {
                return $marker;
            }
        }

        return null;
    }

    /**
     * The gallery marker a set code carries as a suffix, if any (ASR-TG → "TG").
     *
     * Static override codes are exempt: "PR-SV" is a promo set, not a Shiny
     * Vault subset, and must never be split.
     */
    private function extractGallerySuffix(string $normalizedSetCode): ?string
    {
        if (isset(self::STATIC_OVERRIDES[$normalizedSetCode])) {
            return null;
        }

        foreach (self::GALLERY_NUMBER_PREFIXES as $marker) {
            if (str_ends_with($normalizedSetCode, '-'.$marker)) {
                return $marker;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getForwardCandidates(): array
    {
        return $this->forwardCandidates ??= $this->setMappingRepository->getForwardCandidates();
    }

    /**
     * @param list<string|null> $names
     *
     * @return list<string>
     */
    private static function nonEmptyNames(array $names): array
    {
        return array_values(array_filter(
            $names,
            static fn (?string $name): bool => null !== $name && '' !== $name,
        ));
    }

    /**
     * Every locale's spelling of a card's name.
     *
     * Reads the locale-keyed JSON column rather than the nameEn/nameFr getters:
     * those are MySQL generated columns, so they are null on any entity that
     * has not made a round trip through the database. Comparing against every
     * locale also means a French deck list matches without special-casing, and
     * a future locale works with no code change.
     *
     * @return list<string>
     */
    private static function localizedNames(TcgdexCardEntity $entity): array
    {
        $names = [];

        foreach ($entity->getName() as $name) {
            if (\is_string($name)) {
                $names[] = $name;
            }
        }

        return self::nonEmptyNames($names);
    }

    /**
     * @param non-empty-list<array{setId: string, names: list<string>, hasImage: bool, releaseDate: string}> $descriptors
     */
    private function logResolution(
        string $level,
        string $rule,
        array $descriptors,
        int $winner,
        string $ptcgSetCode,
        string $cardNumber,
        ?string $cardName,
    ): void {
        $this->logger->log(
            $level,
            'Ambiguous TCGdex set code {ptcgCode} {number}: {count} sets matched ({setIds}); chose {chosen} by {rule}.',
            [
                'ptcgCode' => $ptcgSetCode,
                'number' => $cardNumber,
                'count' => \count($descriptors),
                'setIds' => implode(', ', array_column($descriptors, 'setId')),
                'chosen' => $descriptors[$winner]['setId'],
                'rule' => $rule,
                'cardName' => $cardName ?? '(none supplied)',
            ],
        );
    }

    /**
     * Resolve an Asian set code to its international equivalent and find a card by name within it.
     *
     * Asian deck lists use different set codes (SM8, S6K, SV1S) and card numbering
     * from international releases. The card number is NOT used — only the name
     * is matched within the resolved international set.
     *
     * @return TcgdexCard|null the matching card DTO, or null if the set code is not an Asian alias
     *                         or no card with this name exists in the resolved set
     */
    public function findCardByNameInAliasedSet(string $setCode, string $cardName): ?TcgdexCard
    {
        $setId = $this->setAliasRepository->findTcgdexSetIdByAlias($setCode);

        if (null === $setId) {
            return null;
        }

        $entities = $this->tcgdexCardRepository->findByNameEnAndSetId($cardName, $setId);

        if ([] === $entities) {
            return null;
        }

        // If multiple matches in the same set (rare — different HP/abilities), return the first
        return $this->buildDtoFromEntity($entities[0]);
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
            attackDamages: $entity->getAttackDamagesEn(),
            types: $entity->getTypes(),
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
        /** @var list<int|string|null> $attackDamages */
        $attackDamages = [];

        if (isset($data['attacks']) && \is_array($data['attacks'])) {
            foreach ($data['attacks'] as $attack) {
                if (\is_array($attack) && isset($attack['name']) && \is_string($attack['name'])) {
                    $attacks[] = $attack['name'];
                    $damage = $attack['damage'] ?? null;
                    $attackDamages[] = \is_int($damage) || \is_string($damage) ? $damage : null;
                }
            }
        }

        /** @var list<string> $types */
        $types = [];

        if (isset($data['types']) && \is_array($data['types'])) {
            foreach ($data['types'] as $type) {
                if (\is_string($type)) {
                    $types[] = $type;
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
            attackDamages: $attackDamages,
            types: $types,
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
