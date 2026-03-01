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
}
