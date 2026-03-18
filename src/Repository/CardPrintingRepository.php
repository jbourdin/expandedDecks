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

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardPrinting>
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class CardPrintingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardPrinting::class);
    }

    public function findByTcgdexId(string $tcgdexId): ?CardPrinting
    {
        /** @var CardPrinting|null $result */
        $result = $this->findOneBy(['tcgdexId' => $tcgdexId]);

        return $result;
    }

    /**
     * Find the lowest-rarity Expanded-legal printing for a card identity.
     * Sorts by rarity tier ascending (cheapest first), then release date descending (most recent).
     */
    public function findLowestRarityForIdentity(CardIdentity $identity): ?CardPrinting
    {
        /** @var CardPrinting|null $result */
        $result = $this->createQueryBuilder('cp')
            ->where('cp.cardIdentity = :identity')
            ->andWhere('cp.isExpandedLegal = :legal')
            ->andWhere('cp.imageUrl IS NOT NULL')
            ->setParameter('identity', $identity)
            ->setParameter('legal', true)
            ->orderBy('cp.rarityTier', 'ASC')
            ->addOrderBy('cp.setReleaseDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * Find the most recent printing for a card identity (for basic energies).
     */
    public function findLatestForIdentity(CardIdentity $identity): ?CardPrinting
    {
        /** @var CardPrinting|null $result */
        $result = $this->createQueryBuilder('cp')
            ->where('cp.cardIdentity = :identity')
            ->andWhere('cp.isExpandedLegal = :legal')
            ->andWhere('cp.imageUrl IS NOT NULL')
            ->setParameter('identity', $identity)
            ->setParameter('legal', true)
            ->orderBy('cp.setReleaseDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }
}
