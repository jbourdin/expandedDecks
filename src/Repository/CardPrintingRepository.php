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

    /** Expanded era starts with Black & White (2011-04-25). */
    private const string EXPANDED_ERA_START = '2011-04-25';

    /** Rarity tiers at or below this threshold are considered "common" (Common, Uncommon, Rare). */
    private const int COMMON_TIER_THRESHOLD = 3;

    /**
     * Find the best Expanded-legal printing for minified export.
     *
     * Two-pass strategy:
     * 1. Look for a common printing (tier 1–3): prefer most recent reprint, then cheapest.
     *    This picks the latest Ultra Ball Common rather than an old cheap one.
     * 2. If none, fall back to rare+ printings (tier 4+): prefer cheapest, then most recent.
     *    This picks the regular GX (€5) over the Full Art (€50) when TCGdex reports
     *    both as "Ultra Rare" at the same tier.
     *
     * @see docs/technicalities/enrichment.md — Lowest-Rarity Printing Selection
     */
    public function findLowestRarityForIdentity(CardIdentity $identity): ?CardPrinting
    {
        // Pass 1: common printings (tiers 1–3) — most recent first, price as tiebreaker
        $result = $this->findPrintingByTierRange($identity, 1, self::COMMON_TIER_THRESHOLD, 'date');

        if (null !== $result) {
            return $result;
        }

        // Pass 2: rare+ printings (tier 4+) — cheapest first, date as tiebreaker
        return $this->findPrintingByTierRange($identity, self::COMMON_TIER_THRESHOLD + 1, null, 'price');
    }

    /**
     * @param 'date'|'price' $primarySort
     */
    private function findPrintingByTierRange(
        CardIdentity $identity,
        int $minTier,
        ?int $maxTier,
        string $primarySort,
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

        $queryBuilder->addOrderBy('cp.rarityTier', 'ASC');

        if ('date' === $primarySort) {
            $queryBuilder->addOrderBy('cp.setReleaseDate', 'DESC');
            $queryBuilder->addOrderBy('cp.priceInCents', 'ASC');
        } else {
            $queryBuilder->addOrderBy('cp.priceInCents', 'ASC');
            $queryBuilder->addOrderBy('cp.setReleaseDate', 'DESC');
        }

        /** @var CardPrinting|null $result */
        $result = $queryBuilder->getQuery()->getOneOrNullResult();

        return $result;
    }
}
