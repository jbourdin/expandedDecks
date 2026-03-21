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

    /** Expanded era starts with Black & White (2011-04-25). */
    private const string EXPANDED_ERA_START = '2011-04-25';

    /**
     * Find the least rare, most recent Expanded-legal printing for a card identity.
     *
     * Sort priority:
     * 1. Rarity tier ascending — lowest rarity first (Common before Rare)
     * 2. Release date descending — most recent reprint within same rarity
     * 3. Price ascending — cheapest as final tiebreaker
     */
    public function findLowestRarityForIdentity(CardIdentity $identity): ?CardPrinting
    {
        /** @var CardPrinting|null $result */
        $result = $this->createQueryBuilder('cp')
            ->where('cp.cardIdentity = :identity')
            ->andWhere('cp.isExpandedLegal = :legal')
            ->andWhere('cp.imageUrl IS NOT NULL')
            ->andWhere('cp.setReleaseDate >= :expandedStart OR cp.setReleaseDate IS NULL')
            ->setParameter('identity', $identity)
            ->setParameter('legal', true)
            ->setParameter('expandedStart', new \DateTimeImmutable(self::EXPANDED_ERA_START))
            ->orderBy('cp.rarityTier', 'ASC')
            ->addOrderBy('cp.setReleaseDate', 'DESC')
            ->addOrderBy('cp.priceInCents', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }
}
