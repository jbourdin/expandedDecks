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

use App\Entity\TcgdexSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TcgdexSet>
 */
class TcgdexSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TcgdexSet::class);
    }

    /**
     * The best set for a PTCG code.
     *
     * A code may denote several sets — a parent expansion and its gallery
     * subset, or two unrelated sets that reused an abbreviation. findOneBy()
     * used to return an arbitrary one, which for callers that build CDN URLs
     * from the set ID meant a 404 whenever the gallery subset won.
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     */
    public function findByPtcgCode(string $ptcgCode): ?TcgdexSet
    {
        return $this->findAllByPtcgCode($ptcgCode)[0] ?? null;
    }

    /**
     * Every set carrying a PTCG code, parent expansions before gallery subsets.
     *
     * A subset's ID always extends its parent's (swsh10 → swsh10.5tg,
     * cel25 → cel25cc), so ordering by ID length puts parents first; the ID
     * itself breaks any remaining tie so the result never depends on row order.
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     *
     * @return list<TcgdexSet>
     */
    public function findAllByPtcgCode(string $ptcgCode): array
    {
        /** @var list<TcgdexSet> $sets */
        $sets = $this->createQueryBuilder('s')
            ->where('s.ptcgCode = :ptcgCode')
            ->setParameter('ptcgCode', $ptcgCode)
            ->orderBy('LENGTH(s.id)', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $sets;
    }

    /** Series IDs that belong to the Expanded format (Black & White onward). */
    private const array EXPANDED_SERIES = ['bw', 'xy', 'sm', 'swsh', 'sv', 'me'];

    /**
     * Base query builder for non-promo expansion sets in the Expanded era,
     * ordered by release date descending.
     *
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function createExpandedSetsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.serie', 'serie')
            ->where('s.releaseDate IS NOT NULL')
            ->andWhere('s.ptcgCode IS NOT NULL')
            ->andWhere('s.ptcgCode NOT LIKE :promoPrefix')
            ->andWhere('serie.id IN (:expandedSeries)')
            ->setParameter('promoPrefix', 'PR-%')
            ->setParameter('expandedSeries', self::EXPANDED_SERIES)
            ->orderBy('s.releaseDate', 'DESC');
    }

    /**
     * Return all non-promo expansion sets from the Expanded era,
     * ordered by release date descending (most recent first).
     *
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     *
     * @return list<TcgdexSet>
     */
    public function findExpansionSetsOrderedByReleaseDate(): array
    {
        /** @var list<TcgdexSet> $sets */
        $sets = $this->createExpandedSetsQueryBuilder()
            ->getQuery()
            ->getResult();

        return $sets;
    }

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function findLatestExpansionSet(): ?TcgdexSet
    {
        /** @var TcgdexSet|null $set */
        $set = $this->createExpandedSetsQueryBuilder()
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $set;
    }
}
