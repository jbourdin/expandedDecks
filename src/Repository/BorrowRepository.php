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

use App\Entity\Borrow;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Borrow>
 */
class BorrowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Borrow::class);
    }

    /**
     * @see docs/features.md F4.1 — Request to borrow a deck
     *
     * @return list<Borrow>
     */
    public function findRecentByBorrower(User $user, int $limit = 10): array
    {
        /** @var list<Borrow> */
        return $this->createQueryBuilder('b')
            ->join('b.deck', 'd')
            ->join('b.event', 'e')
            ->addSelect('d', 'e')
            ->where('b.borrower = :user')
            ->setParameter('user', $user)
            ->orderBy('b.requestedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
