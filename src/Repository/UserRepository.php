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

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @see docs/features.md F1.1 — Register a new account
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Loads a user by email, excluding anonymized accounts.
     */
    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        $user = $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->andWhere('u.isAnonymized = false')
            ->setParameter('email', $identifier)
            ->getQuery()
            ->getOneOrNullResult();

        \assert(null === $user || $user instanceof UserInterface);

        return $user;
    }
}
