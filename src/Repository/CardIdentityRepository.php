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
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardIdentity>
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class CardIdentityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardIdentity::class);
    }

    public function findBySignature(string $name, string $category, int $hp, string $abilitySignature, string $attackSignature): ?CardIdentity
    {
        /** @var CardIdentity|null $result */
        $result = $this->findOneBy([
            'name' => $name,
            'category' => $category,
            'hp' => $hp,
            'abilitySignature' => $abilitySignature,
            'attackSignature' => $attackSignature,
        ]);

        return $result;
    }
}
