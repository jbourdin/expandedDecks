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
use App\Entity\EventDeckRegistration;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventDeckRegistration>
 *
 * @see docs/features.md F4.8 — Staff-delegated lending
 */
class EventDeckRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventDeckRegistration::class);
    }

    /**
     * @return EventDeckRegistration[]
     */
    public function findByEventAndOwner(Event $event, User $owner): array
    {
        /** @var EventDeckRegistration[] $results */
        $results = $this->createQueryBuilder('r')
            ->join('r.deck', 'd')
            ->where('r.event = :event')
            ->andWhere('d.owner = :owner')
            ->setParameter('event', $event)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getResult();

        return $results;
    }

    public function findOneByEventAndDeck(Event $event, Deck $deck): ?EventDeckRegistration
    {
        return $this->findOneBy(['event' => $event, 'deck' => $deck]);
    }

    /**
     * Find all delegated registrations for an event, with deck and owner eager-loaded.
     *
     * @return EventDeckRegistration[]
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    public function findDelegatedByEvent(Event $event): array
    {
        /** @var EventDeckRegistration[] $results */
        $results = $this->createQueryBuilder('r')
            ->join('r.deck', 'd')
            ->addSelect('d')
            ->join('d.owner', 'o')
            ->addSelect('o')
            ->where('r.event = :event')
            ->andWhere('r.delegateToStaff = true')
            ->orderBy('d.name', 'ASC')
            ->setParameter('event', $event)
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Check if a deck has registrations at events that are still active
     * (not cancelled, not finished, and not more than 1 day in the past).
     *
     * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
     */
    public function hasActiveRegistrations(Deck $deck): bool
    {
        $oneDayAgo = new \DateTimeImmutable('-1 day');

        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.event', 'e')
            ->where('r.deck = :deck')
            ->andWhere('e.cancelledAt IS NULL')
            ->andWhere('e.finishedAt IS NULL')
            ->andWhere('e.date >= :oneDayAgo')
            ->setParameter('deck', $deck)
            ->setParameter('oneDayAgo', $oneDayAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
