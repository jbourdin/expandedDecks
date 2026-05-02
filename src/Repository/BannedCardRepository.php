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

use App\Entity\BannedCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BannedCard>
 *
 * @see docs/features.md F6.5 — Banned card list management
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BannedCard::class);
    }

    /**
     * Returns the set of all *active* (non soft-deleted) banned card identifiers for fast lookup.
     *
     * @return array<string, true> keys are "setCode|cardNumber"
     */
    public function findBannedCardKeys(): array
    {
        /** @var list<array{setCode: string, cardNumber: string}> $rows */
        $rows = $this->createQueryBuilder('b')
            ->select('b.setCode, b.cardNumber')
            ->andWhere('b.deletedAt IS NULL')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['setCode'].'|'.$row['cardNumber']] = true;
        }

        return $map;
    }

    /**
     * Active row only (excludes soft-deleted).
     */
    public function findOneBySetCodeAndNumber(string $setCode, string $cardNumber): ?BannedCard
    {
        return $this->findOneBy([
            'setCode' => $setCode,
            'cardNumber' => $cardNumber,
            'deletedAt' => null,
        ]);
    }

    /**
     * Returns the row matching the (setCode, cardNumber) pair regardless of soft-delete state.
     * Used by the sync/admin re-ban path to reactivate a previously soft-deleted entry.
     */
    public function findOneIncludingDeleted(string $setCode, string $cardNumber): ?BannedCard
    {
        return $this->findOneBy([
            'setCode' => $setCode,
            'cardNumber' => $cardNumber,
        ]);
    }

    /**
     * Active rows ordered by effectiveDate desc (newest bans first), nulls last.
     *
     * @return list<BannedCard>
     */
    public function findActiveOrderedByEffectiveDate(): array
    {
        /** @var list<BannedCard> $rows */
        $rows = $this->createQueryBuilder('b')
            ->andWhere('b.deletedAt IS NULL')
            ->addOrderBy('b.effectiveDate', 'DESC')
            ->addOrderBy('b.cardName', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Soft-deleted rows ordered by deletion date desc.
     *
     * @return list<BannedCard>
     */
    public function findDeletedOrderedByDeletionDate(): array
    {
        /** @var list<BannedCard> $rows */
        $rows = $this->createQueryBuilder('b')
            ->andWhere('b.deletedAt IS NOT NULL')
            ->orderBy('b.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
