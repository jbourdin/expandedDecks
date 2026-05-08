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
     * Active staples across the given buckets, with the relations needed to render the public
     * list page eager-loaded in a single query. Returns cards grouped by bucket in the order
     * the buckets were provided, then by position and cardName within each bucket.
     *
     * The eager fetch covers: representativePrinting → tcgdexCard → set → serie, and the
     * printings collection → cardPrinting → tcgdexCard → set → serie. Without this, rendering
     * the page issues one query per card for each lazy-loaded relation (N+1).
     *
     * @param list<string> $buckets
     *
     * @return array<string, list<StapleCard>>
     */
    public function findActiveGroupedByBucket(array $buckets, ?int $minHotness = null): array
    {
        if ([] === $buckets) {
            return [];
        }

        $qb = $this->createQueryBuilder('sc')
            ->addSelect('representativePrinting', 'representativeTcgdexCard', 'representativeSet', 'representativeSerie')
            ->addSelect('staplePrinting', 'staplePrintingCard', 'staplePrintingTcgdexCard', 'staplePrintingSet', 'staplePrintingSerie')
            ->leftJoin('sc.representativePrinting', 'representativePrinting')
            ->leftJoin('representativePrinting.tcgdexCard', 'representativeTcgdexCard')
            ->leftJoin('representativeTcgdexCard.set', 'representativeSet')
            ->leftJoin('representativeSet.serie', 'representativeSerie')
            ->leftJoin('sc.printings', 'staplePrinting')
            ->leftJoin('staplePrinting.cardPrinting', 'staplePrintingCard')
            ->leftJoin('staplePrintingCard.tcgdexCard', 'staplePrintingTcgdexCard')
            ->leftJoin('staplePrintingTcgdexCard.set', 'staplePrintingSet')
            ->leftJoin('staplePrintingSet.serie', 'staplePrintingSerie')
            ->andWhere('sc.bucket IN (:buckets)')
            ->andWhere('sc.deletedAt IS NULL')
            ->setParameter('buckets', $buckets)
            ->orderBy('sc.bucket', 'ASC')
            ->addOrderBy('sc.position', 'ASC')
            ->addOrderBy('sc.cardName', 'ASC');

        if (null !== $minHotness) {
            $qb->andWhere('sc.hotness >= :minHotness')
                ->setParameter('minHotness', $minHotness);
        }

        /** @var list<StapleCard> $rows */
        $rows = $qb->getQuery()->getResult();

        /** @var array<string, list<StapleCard>> $grouped */
        $grouped = array_fill_keys($buckets, []);
        foreach ($rows as $card) {
            $bucket = $card->getBucket();
            if (\array_key_exists($bucket, $grouped)) {
                $grouped[$bucket][] = $card;
            }
        }

        return $grouped;
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
