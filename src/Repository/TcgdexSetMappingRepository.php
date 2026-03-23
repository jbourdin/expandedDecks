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

use App\Entity\TcgdexSetMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TcgdexSetMapping>
 */
class TcgdexSetMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TcgdexSetMapping::class);
    }

    /**
     * Returns the forward mapping: PTCG code → TCGdex set ID.
     *
     * @return array<string, string>
     */
    public function getForwardMapping(): array
    {
        /** @var list<array{tcgdexSetId: string, ptcgCode: string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('m.tcgdexSetId', 'm.ptcgCode')
            ->getQuery()
            ->getArrayResult();

        $mapping = [];

        foreach ($rows as $row) {
            $mapping[$row['ptcgCode']] = $row['tcgdexSetId'];
        }

        return $mapping;
    }

    /**
     * Returns the reverse mapping: TCGdex set ID → PTCG code.
     *
     * @return array<string, string>
     */
    public function getReverseMapping(): array
    {
        /** @var list<array{tcgdexSetId: string, ptcgCode: string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('m.tcgdexSetId', 'm.ptcgCode')
            ->getQuery()
            ->getArrayResult();

        $mapping = [];

        foreach ($rows as $row) {
            $mapping[$row['tcgdexSetId']] = $row['ptcgCode'];
        }

        return $mapping;
    }

    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    public function truncate(): void
    {
        $this->createQueryBuilder('m')
            ->delete()
            ->getQuery()
            ->execute();
    }
}
