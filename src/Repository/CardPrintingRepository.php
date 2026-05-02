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

namespace App\Repository;

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardPrinting>
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class CardPrintingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardPrinting::class);
    }

    public function findByTcgdexId(string $tcgdexId): ?CardPrinting
    {
        /** @var CardPrinting|null $result */
        $result = $this->findOneBy(['tcgdexId' => $tcgdexId]);

        return $result;
    }

    /**
     * Find a printing by its set code + card number. Returns the lowest-rarity
     * Expanded-legal printing if multiple printings share the same coordinates,
     * since the BannedCard image preview should reflect the canonical Expanded form.
     */
    public function findFirstBySetCodeAndCardNumber(string $setCode, string $cardNumber): ?CardPrinting
    {
        /** @var CardPrinting|null $result */
        $result = $this->createQueryBuilder('cp')
            ->andWhere('cp.setCode = :setCode')
            ->andWhere('cp.cardNumber = :cardNumber')
            ->setParameter('setCode', $setCode)
            ->setParameter('cardNumber', $cardNumber)
            ->orderBy('cp.isExpandedLegal', 'DESC')
            ->addOrderBy('cp.rarityTier', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * Find the canonical printing for an identity (already computed and flagged).
     */
    public function findCanonicalForIdentity(CardIdentity $identity): ?CardPrinting
    {
        /** @var CardPrinting|null $result */
        $result = $this->findOneBy([
            'cardIdentity' => $identity,
            'isCanonical' => true,
        ]);

        return $result;
    }

    /** Expanded era starts with Black & White (2011-04-25). */
    private const string EXPANDED_ERA_START = '2011-04-25';

    /** Rarity tiers at or below this threshold are considered "common" (Common, Uncommon, Rare). */
    private const int COMMON_TIER_THRESHOLD = 3;

    /**
     * Trainer Gallery card number prefixes — these are always premium full-art
     * variants despite TCGdex often reporting them as "Rare" (same tier as the
     * regular version). They should be excluded from minified selection.
     */
    private const array PREMIUM_CARD_NUMBER_PREFIXES = ['TG', 'GG'];

    /**
     * Find the best Expanded-legal printing for minified export (price-free algorithm).
     *
     * Three-pass strategy using locally-available data only (no price dependency):
     * 1. Common non-premium printing (tier 1–3): prefer standard number, then most recent.
     * 2. Rare+ non-premium (tier 4+): prefer standard number, then most recent.
     * 3. Any printing including premium: most recent.
     *
     * "Standard number" = numeric card number within the set's official count,
     * not a TG/GG prefix, and not from an unreliable-rarity set.
     *
     * @see docs/technicalities/enrichment.md — Lowest-Rarity Printing Selection
     */
    public function findLowestRarityForIdentity(CardIdentity $identity): ?CardPrinting
    {
        // Pass 1: common non-premium printings (tiers 1–3) — standard number first, then most recent
        $result = $this->findPrintingByTierRange($identity, 1, self::COMMON_TIER_THRESHOLD, true);

        if (null !== $result) {
            return $result;
        }

        // Pass 2: rare+ non-premium printings (tier 4+) — standard number first, then most recent
        $result = $this->findPrintingByTierRange($identity, self::COMMON_TIER_THRESHOLD + 1, null, true);

        if (null !== $result) {
            return $result;
        }

        // Pass 3: any printing including premium — most recent
        return $this->findPrintingByTierRange($identity, 1, null, false);
    }

    /**
     * Compute and persist the canonical printing for an identity.
     *
     * Clears any existing canonical flag on the identity's printings,
     * then marks the best one as canonical.
     */
    public function computeCanonical(CardIdentity $identity): ?CardPrinting
    {
        // Clear existing canonical flag
        $this->createQueryBuilder('cp')
            ->update()
            ->set('cp.isCanonical', ':false')
            ->where('cp.cardIdentity = :identity')
            ->andWhere('cp.isCanonical = :true')
            ->setParameter('identity', $identity)
            ->setParameter('true', true)
            ->setParameter('false', false)
            ->getQuery()
            ->execute();

        $best = $this->findLowestRarityForIdentity($identity);

        if (null !== $best) {
            $best->setIsCanonical(true);
        }

        return $best;
    }

    private function findPrintingByTierRange(
        CardIdentity $identity,
        int $minTier,
        ?int $maxTier,
        bool $excludePremiumNumbers,
    ): ?CardPrinting {
        $queryBuilder = $this->createQueryBuilder('cp')
            ->where('cp.cardIdentity = :identity')
            ->andWhere('cp.isExpandedLegal = :legal')
            ->andWhere('cp.imageUrl IS NOT NULL')
            ->andWhere('cp.rarityTier >= :minTier')
            ->andWhere('cp.setReleaseDate >= :expandedStart OR cp.setReleaseDate IS NULL')
            ->setParameter('identity', $identity)
            ->setParameter('legal', true)
            ->setParameter('minTier', $minTier)
            ->setParameter('expandedStart', new \DateTimeImmutable(self::EXPANDED_ERA_START))
            ->setMaxResults(1);

        if (null !== $maxTier) {
            $queryBuilder
                ->andWhere('cp.rarityTier <= :maxTier')
                ->setParameter('maxTier', $maxTier);
        }

        if ($excludePremiumNumbers) {
            $this->excludePremiumNumbers($queryBuilder);
        }

        // Sort: rarity tier ASC, then most recent release date
        $queryBuilder->addOrderBy('cp.rarityTier', 'ASC');
        $queryBuilder->addOrderBy('cp.setReleaseDate', 'DESC');

        /** @var CardPrinting|null $result */
        $result = $queryBuilder->getQuery()->getOneOrNullResult();

        return $result;
    }

    private function excludePremiumNumbers(QueryBuilder $queryBuilder): void
    {
        foreach (self::PREMIUM_CARD_NUMBER_PREFIXES as $index => $prefix) {
            $queryBuilder
                ->andWhere(\sprintf('cp.cardNumber NOT LIKE :premiumPrefix%d', $index))
                ->setParameter(\sprintf('premiumPrefix%d', $index), $prefix.'%');
        }
    }
}
