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

use App\Entity\Event;
use App\Entity\EventDeckEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventDeckEntry>
 */
class EventDeckEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventDeckEntry::class);
    }

    /**
     * @see docs/features.md F3.7 — Register played deck for event
     */
    public function findOneByEventAndPlayer(Event $event, User $player): ?EventDeckEntry
    {
        /** @var EventDeckEntry|null $entry */
        $entry = $this->createQueryBuilder('e')
            ->join('e.deckVersion', 'dv')
            ->join('dv.deck', 'd')
            ->addSelect('dv', 'd')
            ->where('e.event = :event')
            ->andWhere('e.player = :player')
            ->setParameter('event', $event)
            ->setParameter('player', $player)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $entry;
    }
}
