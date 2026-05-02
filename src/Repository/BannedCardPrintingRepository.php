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

use App\Entity\BannedCardPrinting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BannedCardPrinting>
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardPrintingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BannedCardPrinting::class);
    }

    public function findOneBySetCodeAndCardNumber(string $setCode, string $cardNumber): ?BannedCardPrinting
    {
        /** @var BannedCardPrinting|null $row */
        $row = $this->findOneBy(['setCode' => $setCode, 'cardNumber' => $cardNumber]);

        return $row;
    }

    /**
     * @return list<BannedCardPrinting>
     */
    public function findAllOrderedBySetAndNumber(): array
    {
        /** @var list<BannedCardPrinting> $rows */
        $rows = $this->createQueryBuilder('bcp')
            ->orderBy('bcp.setCode', 'ASC')
            ->addOrderBy('bcp.cardNumber', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
