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
     * Find the currently published homepage layout with its translations eagerly loaded.
     * Returns null if no layout is published.
     */
    public function findPublished(): ?HomepageLayout
    {
        /** @var list<HomepageLayout> $layouts */
        $layouts = $this->createQueryBuilder('l')
            ->leftJoin('l.translations', 't')
            ->addSelect('t')
            ->where('l.isPublished = true')
            ->getQuery()
            ->getResult();

        return $layouts[0] ?? null;
    }
}
