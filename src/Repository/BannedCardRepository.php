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
use App\Entity\CardIdentity;
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
     * Active rows ordered by effective date desc (newest bans first), nulls last,
     * then by name. Used by the public list and the admin "active" tab.
     *
     * Empty parents (no child printings yet) are excluded — they're transient
     * placeholders during sync or stale rows from a manual cleanup, never
     * something to render.
     *
     * @return list<BannedCard>
     */
    public function findActiveOrderedByEffectiveDate(): array
    {
        /** @var list<BannedCard> $rows */
        $rows = $this->createQueryBuilder('bc')
            ->innerJoin('bc.printings', 'bcp')
            ->andWhere('bc.deletedAt IS NULL')
            ->groupBy('bc.id')
            ->addOrderBy('bc.effectiveDate', 'DESC')
            ->addOrderBy('bc.cardName', 'ASC')
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
        $rows = $this->createQueryBuilder('bc')
            ->andWhere('bc.deletedAt IS NOT NULL')
            ->orderBy('bc.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Lookup a parent ban by CardIdentity, regardless of soft-delete state.
     */
    public function findOneByCardIdentity(CardIdentity $cardIdentity): ?BannedCard
    {
        /** @var BannedCard|null $row */
        $row = $this->findOneBy(['cardIdentity' => $cardIdentity]);

        return $row;
    }

    /**
     * Returns the set of all *active* banned (setCode, cardNumber) pairs for
     * fast deck-list validation. Joins against banned_card_printing through the
     * parent so soft-deleted parents (whole-card unbanning) are excluded.
     *
     * @return array<string, true> keys are "setCode|cardNumber"
     */
    public function findBannedPrintingKeys(): array
    {
        /** @var list<array{setCode: string, cardNumber: string}> $rows */
        $rows = $this->getEntityManager()->createQuery(
            <<<'DQL'
                SELECT bcp.setCode, bcp.cardNumber
                FROM App\Entity\BannedCardPrinting bcp
                INNER JOIN bcp.bannedCard bc
                WHERE bc.deletedAt IS NULL
                DQL
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['setCode'].'|'.$row['cardNumber']] = true;
        }

        return $map;
    }
}
