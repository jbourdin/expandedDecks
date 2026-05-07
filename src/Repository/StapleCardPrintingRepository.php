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

use App\Entity\StapleCardPrinting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StapleCardPrinting>
 *
 * @see docs/features.md F6.15 — Staple cards
 */
class StapleCardPrintingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StapleCardPrinting::class);
    }

    public function findOneBySetCodeAndCardNumber(string $setCode, string $cardNumber): ?StapleCardPrinting
    {
        /** @var StapleCardPrinting|null $row */
        $row = $this->findOneBy(['setCode' => $setCode, 'cardNumber' => $cardNumber]);

        return $row;
    }

    /**
     * @return list<StapleCardPrinting>
     */
    public function findAllOrderedBySetAndNumber(): array
    {
        /** @var list<StapleCardPrinting> $rows */
        $rows = $this->createQueryBuilder('scp')
            ->orderBy('scp.setCode', 'ASC')
            ->addOrderBy('scp.cardNumber', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
