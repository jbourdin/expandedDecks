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
use App\Enum\DeckStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deck>
 */
class DeckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deck::class);
    }

    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     *
     * @return list<Deck>
     */
    public function findAvailableDecks(): array
    {
        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->join('d.owner', 'o')
            ->leftJoin('d.currentVersion', 'cv')
            ->addSelect('o', 'cv')
            ->where('d.status = :status')
            ->setParameter('status', DeckStatus::Available)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     *
     * @return list<Deck>
     */
    public function findByOwner(User $owner): array
    {
        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->leftJoin('d.currentVersion', 'cv')
            ->addSelect('cv')
            ->where('d.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * @see docs/features.md F4.12 — Walk-up lending (direct lend)
     *
     * @return list<Deck>
     */
    public function searchAvailableForEvent(string $query, Event $event, int $limit = 10): array
    {
        $eventDate = $event->getDate();
        $startOfDay = new \DateTimeImmutable($eventDate->format('Y-m-d').' 00:00:00');
        $endDate = $event->getEndDate() ?? $eventDate;
        $endOfDay = new \DateTimeImmutable($endDate->format('Y-m-d').' 23:59:59');

        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->join('d.owner', 'o')
            ->addSelect('o')
            ->where('d.status != :retired')
            ->andWhere('d.currentVersion IS NOT NULL')
            ->andWhere('d.name LIKE :query OR d.shortTag LIKE :query')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\Borrow b
                JOIN b.event e2
                WHERE b.deck = d
                AND b.status IN (:activeStatuses)
                AND e2.date <= :endOfDay
                AND COALESCE(e2.endDate, e2.date) >= :startOfDay
            )')
            ->setParameter('retired', DeckStatus::Retired)
            ->setParameter('query', '%'.$query.'%')
            ->setParameter('activeStatuses', BorrowRepository::activeStatusValues())
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('d.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * Find decks available to borrow for an event: must be registered at this event
     * (via EventDeckRegistration), not retired, not owned by the requesting user,
     * not already being played (via EventDeckEntry), and not already borrowed.
     *
     * @see docs/features.md F4.1 — Request to borrow a deck
     * @see docs/features.md F4.11 — Borrow conflict detection
     *
     * @return list<Deck>
     */
    public function findAvailableForEvent(Event $event, User $excludeOwner): array
    {
        $eventDate = $event->getDate();
        $startOfDay = new \DateTimeImmutable($eventDate->format('Y-m-d').' 00:00:00');
        $endDate = $event->getEndDate() ?? $eventDate;
        $endOfDay = new \DateTimeImmutable($endDate->format('Y-m-d').' 23:59:59');

        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->join('d.owner', 'o')
            ->addSelect('o')
            ->join('App\Entity\EventDeckRegistration', 'r', 'WITH', 'r.deck = d AND r.event = :event')
            ->where('d.status != :retired')
            ->andWhere('d.owner != :excludeOwner')
            ->andWhere('d.currentVersion IS NOT NULL')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\EventDeckEntry ede
                WHERE ede.deckVersion = d.currentVersion
                AND ede.event = :event
            )')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\Borrow b
                JOIN b.event e2
                WHERE b.deck = d
                AND b.status IN (:activeStatuses)
                AND e2.date <= :endOfDay
                AND COALESCE(e2.endDate, e2.date) >= :startOfDay
            )')
            ->setParameter('event', $event)
            ->setParameter('retired', DeckStatus::Retired)
            ->setParameter('excludeOwner', $excludeOwner)
            ->setParameter('activeStatuses', BorrowRepository::activeStatusValues())
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $decks;
    }
}
