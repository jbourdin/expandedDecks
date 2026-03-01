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

use App\Entity\Borrow;
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\BorrowStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Borrow>
 */
class BorrowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Borrow::class);
    }

    /**
     * @see docs/features.md F4.1 — Request to borrow a deck
     *
     * @return list<Borrow>
     */
    public function findRecentByBorrower(User $user, int $limit = 10): array
    {
        /** @var list<Borrow> $borrows */
        $borrows = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.event', 'e')
            ->addSelect('d', 'e')
            ->where('b.borrower = :user')
            ->setParameter('user', $user)
            ->orderBy('b.requestedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $borrows;
    }

    /**
     * @see docs/features.md F4.11 — Borrow conflict detection
     */
    public function findActiveBorrowForDeckAtEvent(Deck $deck, Event $event): ?Borrow
    {
        $activeStatuses = [
            BorrowStatus::Pending,
            BorrowStatus::Approved,
            BorrowStatus::Lent,
            BorrowStatus::Overdue,
        ];

        /** @var Borrow|null $borrow */
        $borrow = $this->createQueryBuilder('b')
            ->where('b.deck = :deck')
            ->andWhere('b.event = :event')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('deck', $deck)
            ->setParameter('event', $event)
            ->setParameter('statuses', array_map(static fn (BorrowStatus $s): string => $s->value, $activeStatuses))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $borrow;
    }

    /**
     * @see docs/features.md F4.1 — Request to borrow a deck
     *
     * @return list<Borrow>
     */
    public function findByEvent(Event $event): array
    {
        /** @var list<Borrow> $borrows */
        $borrows = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.borrower', 'u')
            ->addSelect('d', 'u')
            ->where('b.event = :event')
            ->setParameter('event', $event)
            ->orderBy('b.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $borrows;
    }

    /**
     * @see docs/features.md F4.2 — Approve / deny a borrow request
     *
     * @return list<Borrow>
     */
    public function findPendingRequestsForOwner(User $owner): array
    {
        /** @var list<Borrow> $borrows */
        $borrows = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.event', 'e')
            ->join('b.borrower', 'u')
            ->addSelect('d', 'e', 'u')
            ->where('d.owner = :owner')
            ->andWhere('b.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', BorrowStatus::Pending->value)
            ->orderBy('b.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $borrows;
    }
}
