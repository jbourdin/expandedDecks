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

use App\Entity\TcgdexSetMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TcgdexSetMapping>
 */
class TcgdexSetMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TcgdexSetMapping::class);
    }

    /**
     * Returns the forward mapping: PTCG code → TCGdex set ID.
     *
     * @return array<string, string>
     */
    public function getForwardMapping(): array
    {
        /** @var list<array{tcgdexSetId: string, ptcgCode: string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('m.tcgdexSetId', 'm.ptcgCode')
            ->getQuery()
            ->getArrayResult();

        $mapping = [];

        foreach ($rows as $row) {
            $mapping[$row['ptcgCode']] = $row['tcgdexSetId'];
        }

        return $mapping;
    }

    /**
     * Returns every TCGdex set ID a PTCG code maps to.
     *
     * The table's primary key is the TCGdex set ID, so a single PTCG code may
     * legitimately appear on several rows — a set and its Trainer Gallery
     * subset (ASR → swsh10 + swsh10.5tg), or two unrelated sets that reused the
     * same abbreviation decades apart (RR → ex7 + pl2). getForwardMapping()
     * collapses those to one arbitrary winner; this keeps them all so the
     * caller can disambiguate by card number and name.
     *
     * Ordered by set ID so the candidate order is stable across environments —
     * there is no index on ptcg_code, so an unordered scan returns rows in
     * clustered-PK order, which is an implementation detail, not a guarantee.
     *
     * @see docs/features.md F6.16 — Ambiguous PTCG set code resolution
     *
     * @return array<string, list<string>> PTCG code → TCGdex set IDs
     */
    public function getForwardCandidates(): array
    {
        /** @var list<array{tcgdexSetId: string, ptcgCode: string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('m.tcgdexSetId', 'm.ptcgCode')
            ->orderBy('m.tcgdexSetId', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $candidates = [];

        foreach ($rows as $row) {
            $candidates[$row['ptcgCode']][] = $row['tcgdexSetId'];
        }

        return $candidates;
    }

    /**
     * Returns the reverse mapping: TCGdex set ID → PTCG code.
     *
     * @return array<string, string>
     */
    public function getReverseMapping(): array
    {
        /** @var list<array{tcgdexSetId: string, ptcgCode: string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('m.tcgdexSetId', 'm.ptcgCode')
            ->getQuery()
            ->getArrayResult();

        $mapping = [];

        foreach ($rows as $row) {
            $mapping[$row['tcgdexSetId']] = $row['ptcgCode'];
        }

        return $mapping;
    }

    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    public function truncate(): void
    {
        $this->createQueryBuilder('m')
            ->delete()
            ->getQuery()
            ->execute();
    }
}
