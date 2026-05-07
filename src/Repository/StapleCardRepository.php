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
use App\Entity\StapleCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StapleCard>
 *
 * @see docs/features.md F6.15 — Staple cards
 */
class StapleCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StapleCard::class);
    }

    /**
     * Active staples in a given bucket, ordered by position. Used by both the public list
     * (with `$minHotness`) and the admin "active" tab (with `$minHotness = null`).
     *
     * @return list<StapleCard>
     */
    public function findActiveByBucket(string $bucket, ?int $minHotness = null): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->andWhere('sc.bucket = :bucket')
            ->andWhere('sc.deletedAt IS NULL')
            ->setParameter('bucket', $bucket)
            ->orderBy('sc.position', 'ASC')
            ->addOrderBy('sc.cardName', 'ASC');

        if (null !== $minHotness) {
            $qb->andWhere('sc.hotness >= :minHotness')
                ->setParameter('minHotness', $minHotness);
        }

        /** @var list<StapleCard> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    /**
     * Soft-deleted staples ordered by deletion date desc. Used by the admin "history" tab.
     *
     * @return list<StapleCard>
     */
    public function findDeletedOrderedByDeletionDate(): array
    {
        /** @var list<StapleCard> $rows */
        $rows = $this->createQueryBuilder('sc')
            ->andWhere('sc.deletedAt IS NOT NULL')
            ->orderBy('sc.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Lookup a staple parent by CardIdentity, regardless of soft-delete state.
     * Used by the enricher's reparent-by-identity logic.
     */
    public function findOneByCardIdentity(CardIdentity $cardIdentity): ?StapleCard
    {
        /** @var StapleCard|null $row */
        $row = $this->findOneBy(['cardIdentity' => $cardIdentity]);

        return $row;
    }

    /**
     * Highest `position` value currently used in the given bucket among ACTIVE rows.
     * Returns -1 when the bucket is empty so callers can append at `findMaxPosition() + 1`.
     */
    public function findMaxPositionInBucket(string $bucket): int
    {
        /** @var int|null $max */
        $max = $this->createQueryBuilder('sc')
            ->select('MAX(sc.position)')
            ->andWhere('sc.bucket = :bucket')
            ->andWhere('sc.deletedAt IS NULL')
            ->setParameter('bucket', $bucket)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? -1 : (int) $max;
    }

    /**
     * All ACTIVE staples (any bucket), used by the technical re-enrich and the CLI command.
     *
     * @return list<StapleCard>
     */
    public function findAllActive(): array
    {
        /** @var list<StapleCard> $rows */
        $rows = $this->createQueryBuilder('sc')
            ->andWhere('sc.deletedAt IS NULL')
            ->orderBy('sc.bucket', 'ASC')
            ->addOrderBy('sc.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
