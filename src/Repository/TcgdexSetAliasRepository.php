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

use App\Entity\TcgdexSetAlias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TcgdexSetAlias>
 */
class TcgdexSetAliasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TcgdexSetAlias::class);
    }

    /**
     * Find the international TCGdex set ID for an alias code (case-insensitive).
     */
    public function findTcgdexSetIdByAlias(string $aliasCode): ?string
    {
        $alias = $this->find(strtoupper($aliasCode));

        return $alias?->getTcgdexSetId();
    }
}
