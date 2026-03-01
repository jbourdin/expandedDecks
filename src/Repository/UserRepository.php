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

    /**
     * @see docs/features.md F1.2 — Email verification
     */
    public function findOneByVerificationToken(string $token): ?User
    {
        return $this->findOneBy(['verificationToken' => $token]);
    }

    /**
     * @see docs/features.md F1.7 — Password reset
     */
    public function findOneByResetToken(string $token): ?User
    {
        return $this->findOneBy(['resetToken' => $token]);
    }

    /**
     * Searches users by screen name, email, or Pokemon ID.
     *
     * @see docs/features.md F3.5 — Assign event staff team
     *
     * @return list<User>
     */
    public function searchUsers(string $query, int $limit = 10): array
    {
        /** @var list<User> $results */
        $results = $this->createQueryBuilder('u')
            ->where('u.isAnonymized = false')
            ->andWhere('u.isVerified = true')
            ->andWhere('u.screenName LIKE :query OR u.email LIKE :query OR u.playerId LIKE :query')
            ->setParameter('query', '%'.$query.'%')
            ->setMaxResults($limit)
            ->orderBy('u.screenName', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Finds a user by screen name, email, or Pokemon ID (first match wins).
     *
     * @see docs/features.md F3.5 — Assign event staff team
     */
    public function findByMultiField(string $query): ?User
    {
        return $this->findOneBy(['screenName' => $query])
            ?? $this->findOneBy(['email' => $query])
            ?? $this->findOneBy(['playerId' => $query]);
    }
}
