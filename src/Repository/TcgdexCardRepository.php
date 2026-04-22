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

use App\Entity\TcgdexCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TcgdexCard>
 */
class TcgdexCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TcgdexCard::class);
    }

    /**
     * Find a card by its set ID and local ID (card number within the set).
     */
    public function findBySetAndLocalId(string $setId, string $localId): ?TcgdexCard
    {
        /** @var TcgdexCard|null $result */
        $result = $this->createQueryBuilder('c')
            ->join('c.set', 's')
            ->where('s.id = :setId')
            ->andWhere('c.localId = :localId')
            ->setParameter('setId', $setId)
            ->setParameter('localId', $localId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * Find all cards with the given English name (exact match via generated column).
     *
     * @return list<TcgdexCard>
     */
    public function findAllByNameEn(string $name): array
    {
        /** @var list<TcgdexCard> $results */
        $results = $this->findBy(['nameEn' => $name]);

        return $results;
    }

    /**
     * Find the first card with the given English name.
     */
    public function findFirstByNameEn(string $name): ?TcgdexCard
    {
        /** @var TcgdexCard|null $result */
        $result = $this->findOneBy(['nameEn' => $name]);

        return $result;
    }

    /**
     * Count the number of cards in a given set.
     */
    public function countBySetId(string $setId): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.set', 's')
            ->where('s.id = :setId')
            ->setParameter('setId', $setId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * Find all cards with the given English name within a specific set.
     *
     * Used for Asian alias resolution: the set is known (via alias table)
     * but the card number doesn't transfer between Japanese and international.
     *
     * @return list<TcgdexCard>
     */
    public function findByNameEnAndSetId(string $name, string $setId): array
    {
        /** @var list<TcgdexCard> $results */
        $results = $this->createQueryBuilder('c')
            ->join('c.set', 's')
            ->where('c.nameEn = :name')
            ->andWhere('s.id = :setId')
            ->setParameter('name', $name)
            ->setParameter('setId', $setId)
            ->getQuery()
            ->getResult();

        return $results;
    }
}
