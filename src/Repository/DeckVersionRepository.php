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
use App\Entity\DeckVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeckVersion>
 */
class DeckVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeckVersion::class);
    }

    /**
     * @return list<DeckVersion>
     */
    public function findNotEnriched(): array
    {
        /** @var list<DeckVersion> $results */
        $results = $this->createQueryBuilder('dv')
            ->where('dv.enrichmentStatus IN (:statuses)')
            ->setParameter('statuses', ['pending', 'failed'])
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * @see docs/features.md F2.2 — Import deck list (PTCG text format)
     */
    public function findMaxVersionNumber(Deck $deck): int
    {
        /** @var int|null $max */
        $max = $this->createQueryBuilder('dv')
            ->select('MAX(dv.versionNumber)')
            ->where('dv.deck = :deck')
            ->setParameter('deck', $deck)
            ->getQuery()
            ->getSingleScalarResult();

        return $max ?? 0;
    }
}
