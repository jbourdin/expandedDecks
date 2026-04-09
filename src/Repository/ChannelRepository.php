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

use App\Entity\Channel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Channel>
 *
 * @see docs/features.md F18.1 — Channel entity and database schema
 */
class ChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Channel::class);
    }

    /**
     * @see docs/features.md F18.1 — Channel entity and database schema
     */
    public function findByCode(string $code): ?Channel
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * @see docs/features.md F18.2 — Channel resolver: request-to-channel matching via domain
     */
    public function findByDomain(string $domain): ?Channel
    {
        return $this->findOneBy(['domain' => $domain]);
    }
}
