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
use Doctrine\ORM\QueryBuilder;
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
     * Find any active (non-terminal) borrow for this deck at this event.
     *
     * @see docs/features.md F4.11 — Borrow conflict detection
     */
    public function findActiveBorrowForDeckAtEvent(Deck $deck, Event $event): ?Borrow
    {
        /** @var Borrow|null $borrow */
        $borrow = $this->createQueryBuilder('b')
            ->where('b.deck = :deck')
            ->andWhere('b.event = :event')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('deck', $deck)
            ->setParameter('event', $event)
            ->setParameter('statuses', self::activeStatusValues())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $borrow;
    }

    /**
     * Find a borrow that blocks new requests for this deck at this event.
     * Only approved/lent/overdue borrows block; pending borrows do not.
     *
     * @see docs/features.md F4.11 — Borrow conflict detection
     */
    public function findBlockingBorrowForDeckAtEvent(Deck $deck, Event $event): ?Borrow
    {
        /** @var Borrow|null $borrow */
        $borrow = $this->createQueryBuilder('b')
            ->where('b.deck = :deck')
            ->andWhere('b.event = :event')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('deck', $deck)
            ->setParameter('event', $event)
            ->setParameter('statuses', self::blockingStatusValues())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $borrow;
    }

    /**
     * Finds borrows that block a new request for this deck on the same day
     * at a different event. Only approved/lent/overdue borrows block.
     *
     * @see docs/features.md F4.11 — Borrow conflict detection
     *
     * @return list<Borrow>
     */
    public function findBlockingBorrowsOnSameDay(Deck $deck, Event $event): array
    {
        $eventDate = $event->getDate();
        $startOfDay = new \DateTimeImmutable($eventDate->format('Y-m-d').' 00:00:00');

        $endDate = $event->getEndDate() ?? $eventDate;
        $endOfDay = new \DateTimeImmutable($endDate->format('Y-m-d').' 23:59:59');

        /** @var list<Borrow> $borrows */
        $borrows = $this->createQueryBuilder('b')
            ->join('b.event', 'e')
            ->addSelect('e')
            ->where('b.deck = :deck')
            ->andWhere('b.event != :event')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere('e.date <= :endOfDay')
            ->andWhere('COALESCE(e.endDate, e.date) >= :startOfDay')
            ->setParameter('deck', $deck)
            ->setParameter('event', $event)
            ->setParameter('statuses', self::blockingStatusValues())
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getResult();

        return $borrows;
    }

    /**
     * @return list<string>
     */
    public static function activeStatusValues(): array
    {
        return array_map(
            static fn (BorrowStatus $s): string => $s->value,
            [BorrowStatus::Pending, BorrowStatus::Approved, BorrowStatus::Lent, BorrowStatus::Overdue],
        );
    }

    /**
     * Statuses that hard-block new borrow requests and hide decks from availability lists.
     * Pending is excluded: multiple pending requests are allowed so the owner can choose.
     *
     * @see docs/features.md F4.11 — Borrow conflict detection
     *
     * @return list<string>
     */
    public static function blockingStatusValues(): array
    {
        return array_map(
            static fn (BorrowStatus $s): string => $s->value,
            [BorrowStatus::Approved, BorrowStatus::Lent, BorrowStatus::Overdue],
        );
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
     * Role-based event borrow filtering: organizer/staff see all,
     * others see only borrows where they are the borrower or the deck owner.
     *
     * @see docs/features.md F4.5 — Borrow history
     *
     * @return list<Borrow>
     */
    public function findByEventForUser(Event $event, User $user): array
    {
        if ($event->isOrganizerOrStaff($user)) {
            return $this->findByEvent($event);
        }

        /** @var list<Borrow> $borrows */
        $borrows = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.borrower', 'u')
            ->addSelect('d', 'u')
            ->where('b.event = :event')
            ->andWhere('b.borrower = :user OR d.owner = :user')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->orderBy('b.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $borrows;
    }

    /**
     * Recent borrows at events where the user is organizer or staff,
     * excluding borrows already visible in their borrow/lend sections.
     *
     * @see docs/features.md F4.8 — Staff-delegated lending
     *
     * @return list<Borrow>
     */
    public function findRecentByEventOrganizerOrStaff(User $user, int $limit = 10): array
    {
        /** @var list<Borrow> $borrows */
        $borrows = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.event', 'e')
            ->join('b.borrower', 'u')
            ->leftJoin('e.staff', 's', 'WITH', 's.user = :user')
            ->addSelect('d', 'e', 'u')
            ->where('e.organizer = :user OR s.id IS NOT NULL')
            ->andWhere('b.borrower != :user')
            ->andWhere('d.owner != :user')
            ->setParameter('user', $user)
            ->orderBy('b.requestedAt', 'DESC')
            ->setMaxResults($limit)
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

    /**
     * Borrows for a deck visible to the given user (owner, borrower, or event organizer).
     *
     * @see docs/features.md F4.5 — Borrow history
     *
     * @return list<Borrow>
     */
    public function findByDeckForUser(Deck $deck, User $user): array
    {
        /** @var list<Borrow> $borrows */
        $borrows = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.event', 'e')
            ->join('b.borrower', 'u')
            ->addSelect('d', 'e', 'u')
            ->where('b.deck = :deck')
            ->andWhere('d.owner = :user OR b.borrower = :user OR e.organizer = :user')
            ->setParameter('deck', $deck)
            ->setParameter('user', $user)
            ->orderBy('b.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $borrows;
    }

    /**
     * Recent borrows for decks owned by the given user (lend activity).
     *
     * @see docs/features.md F4.10 — Owner borrow inbox
     *
     * @return list<Borrow>
     */
    public function findRecentByDeckOwner(User $owner, int $limit = 10): array
    {
        /** @var list<Borrow> $borrows */
        $borrows = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.event', 'e')
            ->join('b.borrower', 'u')
            ->addSelect('d', 'e', 'u')
            ->where('d.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('b.requestedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $borrows;
    }

    /**
     * Non-terminal borrows for decks owned by the user, grouped-ready
     * (ordered by event date ASC, then requestedAt DESC).
     *
     * @see docs/features.md F4.10 — Owner borrow inbox
     *
     * @return list<Borrow>
     */
    public function findActiveBorrowsForOwner(User $owner): array
    {
        /** @var list<Borrow> $borrows */
        $borrows = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.event', 'e')
            ->join('b.borrower', 'u')
            ->addSelect('d', 'e', 'u')
            ->where('d.owner = :owner')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('owner', $owner)
            ->setParameter('statuses', self::activeStatusValues())
            ->orderBy('e.date', 'ASC')
            ->addOrderBy('b.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $borrows;
    }

    /**
     * Active delegated borrows at an event — for the staff custody dashboard.
     *
     * @see docs/features.md F4.9 — Staff deck custody tracking
     *
     * @return list<Borrow>
     */
    public function findDelegatedBorrowsByEvent(Event $event): array
    {
        /** @var list<Borrow> $borrows */
        $borrows = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.borrower', 'u')
            ->addSelect('d', 'u')
            ->where('b.event = :event')
            ->andWhere('b.isDelegatedToStaff = true')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('event', $event)
            ->setParameter('statuses', self::activeStatusValues())
            ->orderBy('b.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $borrows;
    }

    /**
     * @see docs/features.md F4.5 — Borrow history
     */
    public function createBorrowerQueryBuilder(User $borrower, ?BorrowStatus $status = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.event', 'e')
            ->join('d.owner', 'o')
            ->addSelect('d', 'e', 'o')
            ->where('b.borrower = :borrower')
            ->setParameter('borrower', $borrower)
            ->orderBy('b.requestedAt', 'DESC');

        if (null !== $status) {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', $status->value);
        }

        return $qb;
    }

    /**
     * @see docs/features.md F4.10 — Owner borrow inbox
     */
    public function createDeckOwnerQueryBuilder(User $owner, ?BorrowStatus $status = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.event', 'e')
            ->join('b.borrower', 'u')
            ->addSelect('d', 'e', 'u')
            ->where('d.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('b.requestedAt', 'DESC');

        if (null !== $status) {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', $status->value);
        }

        return $qb;
    }
}
