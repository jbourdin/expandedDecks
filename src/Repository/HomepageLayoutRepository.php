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
use App\Entity\HomepageLayout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomepageLayout>
 *
 * @see docs/features.md F10.3 — HomepageLayout entity and data model
 */
class HomepageLayoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomepageLayout::class);
    }

    /**
     * Find the currently published homepage layout for a channel.
     * Returns null if no layout is published for the given channel.
     *
     * @see docs/features.md F18.10 — Add channel association to HomepageLayout
     */
    public function findPublished(?Channel $channel = null): ?HomepageLayout
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->leftJoin('l.translations', 't')
            ->addSelect('t')
            ->where('l.isPublished = true')
            ->orderBy('l.id', 'DESC');

        if (null !== $channel) {
            $queryBuilder->andWhere('l.channel = :channel OR l.channel IS NULL')
                ->setParameter('channel', $channel);
        }

        /** @var list<HomepageLayout> $layouts */
        $layouts = $queryBuilder->getQuery()->getResult();

        return $layouts[0] ?? null;
    }
}
