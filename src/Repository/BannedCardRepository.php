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
     * Returns the set of all banned card names for fast lookup.
     *
     * @return array<string, true>
     */
    public function findBannedCardNames(): array
    {
        /** @var list<array{cardName: string}> $rows */
        $rows = $this->createQueryBuilder('b')
            ->select('b.cardName')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['cardName']] = true;
        }

        return $map;
    }

    public function findOneByName(string $cardName): ?BannedCard
    {
        return $this->findOneBy(['cardName' => $cardName]);
    }
}
