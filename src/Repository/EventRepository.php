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

use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @see docs/features.md F10.2 — Anonymous homepage
     */
    public function countUpcoming(): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.date >= :today')
            ->andWhere('e.cancelledAt IS NULL')
            ->andWhere('e.finishedAt IS NULL')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * @see docs/features.md F3.1 — Create a new event
     *
     * @return list<Event>
     */
    public function findUpcoming(int $limit = 5): array
    {
        /** @var list<Event> $events */
        $events = $this->createQueryBuilder('e')
            ->join('e.organizer', 'o')
            ->addSelect('o')
            ->where('e.date >= :today')
            ->andWhere('e.cancelledAt IS NULL')
            ->andWhere('e.finishedAt IS NULL')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('e.date', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $events;
    }

    /**
     * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
     *
     * @return list<Event>
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        /** @var list<Event> $events */
        $events = $this->createQueryBuilder('e')
            ->where('e.name LIKE :query')
            ->andWhere('e.cancelledAt IS NULL')
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('e.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $events;
    }

    /**
     * Events where the user is organizer or staff, with start date >= 7 days ago.
     *
     * @see docs/features.md F7.1 — Dashboard
     *
     * @return list<Event>
     */
    public function findRecentByOrganizerOrStaff(User $user): array
    {
        /** @var list<Event> $events */
        $events = $this->createQueryBuilder('e')
            ->leftJoin('e.staff', 's', 'WITH', 's.user = :user')
            ->where('e.organizer = :user OR s.id IS NOT NULL')
            ->andWhere('e.date >= :cutoff')
            ->andWhere('e.cancelledAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('cutoff', new \DateTimeImmutable('-7 days'))
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();

        return $events;
    }

    /**
     * Upcoming events where the user has any engagement (interested, playing, spectating).
     *
     * @see docs/features.md F7.1 — Dashboard
     *
     * @return list<Event>
     */
    public function findUpcomingByEngagement(User $user): array
    {
        /** @var list<Event> $events */
        $events = $this->createQueryBuilder('e')
            ->join('e.engagements', 'eg', 'WITH', 'eg.user = :user')
            ->where('e.date >= :today')
            ->andWhere('e.cancelledAt IS NULL')
            ->andWhere('e.finishedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();

        return $events;
    }

    /**
     * Upcoming events where the user has an engagement (candidate events for borrow).
     * Same-day conflict filtering is done in the controller via BorrowRepository.
     *
     * @see docs/features.md F4.1 — Request to borrow a deck
     * @see docs/features.md F4.11 — Borrow conflict detection
     *
     * @return list<Event>
     */
    public function findEligibleForBorrow(User $user, Deck $deck): array
    {
        /** @var list<Event> $events */
        $events = $this->createQueryBuilder('e')
            ->join('e.engagements', 'eg', 'WITH', 'eg.user = :user')
            ->where('e.date >= :today')
            ->andWhere('e.cancelledAt IS NULL')
            ->andWhere('e.finishedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();

        return $events;
    }
}
