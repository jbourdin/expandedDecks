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
use App\Enum\BorrowStatus;
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
     * @see docs/features.md F4.1 — Request to borrow a deck
     *
     * @return list<Deck>
     */
    public function findAvailableForEvent(Event $event, User $excludeOwner): array
    {
        $activeStatuses = array_map(
            static fn (BorrowStatus $s): string => $s->value,
            [BorrowStatus::Pending, BorrowStatus::Approved, BorrowStatus::Lent, BorrowStatus::Overdue],
        );

        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->join('d.owner', 'o')
            ->addSelect('o')
            ->where('d.status = :status')
            ->andWhere('d.owner != :excludeOwner')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\Borrow b
                WHERE b.deck = d
                AND b.event = :event
                AND b.status IN (:activeStatuses)
            )')
            ->setParameter('status', DeckStatus::Available)
            ->setParameter('excludeOwner', $excludeOwner)
            ->setParameter('event', $event)
            ->setParameter('activeStatuses', $activeStatuses)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $decks;
    }
}
