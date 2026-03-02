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
}
