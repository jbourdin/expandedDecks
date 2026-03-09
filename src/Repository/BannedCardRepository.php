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

use App\Entity\BannedCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BannedCard>
 *
 * @see docs/features.md F6.5 — Banned card list management
 */
class BannedCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BannedCard::class);
    }

    /**
     * Returns the set of all banned card identifiers for fast lookup.
     *
     * @return array<string, true> keys are "setCode|cardNumber"
     */
    public function findBannedCardKeys(): array
    {
        /** @var list<array{setCode: string, cardNumber: string}> $rows */
        $rows = $this->createQueryBuilder('b')
            ->select('b.setCode, b.cardNumber')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['setCode'].'|'.$row['cardNumber']] = true;
        }

        return $map;
    }

    public function findOneBySetCodeAndNumber(string $setCode, string $cardNumber): ?BannedCard
    {
        return $this->findOneBy(['setCode' => $setCode, 'cardNumber' => $cardNumber]);
    }
}
