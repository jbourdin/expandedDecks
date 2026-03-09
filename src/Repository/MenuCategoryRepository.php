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

use App\Entity\MenuCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuCategory>
 *
 * @see docs/features.md F11.2 — Menu categories
 */
class MenuCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuCategory::class);
    }

    /**
     * Find all categories ordered by position, with their translations eagerly loaded.
     *
     * @return list<MenuCategory>
     */
    public function findAllOrdered(): array
    {
        /** @var list<MenuCategory> $categories */
        $categories = $this->createQueryBuilder('c')
            ->leftJoin('c.translations', 't')
            ->addSelect('t')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $categories;
    }

    /**
     * Find categories that have at least one published page, ordered by position.
     * Eagerly loads published pages and their translations for menu rendering.
     *
     * @return list<MenuCategory>
     */
    public function findWithPublishedPages(): array
    {
        /** @var list<MenuCategory> $categories */
        $categories = $this->createQueryBuilder('c')
            ->leftJoin('c.translations', 't')
            ->addSelect('t')
            ->innerJoin('c.pages', 'p')
            ->addSelect('p')
            ->leftJoin('p.translations', 'pt')
            ->addSelect('pt')
            ->where('p.isPublished = true')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $categories;
    }
}
