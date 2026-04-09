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
use App\Entity\MenuCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuCategory>
 *
 * @see docs/features.md F11.2 — Menu categories
 * @see docs/features.md F18.8 — Add channel association to MenuCategory
 */
class MenuCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuCategory::class);
    }

    /**
     * Find all categories ordered by position, with their translations eagerly loaded.
     * Optionally filtered by channel.
     *
     * @return list<MenuCategory>
     */
    public function findAllOrdered(?Channel $channel = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.translations', 't')
            ->addSelect('t')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if (null !== $channel) {
            $queryBuilder->andWhere('c.channel = :channel')->setParameter('channel', $channel);
        }

        /** @var list<MenuCategory> $categories */
        $categories = $queryBuilder->getQuery()->getResult();

        return $categories;
    }

    /**
     * Find menu (non-footer) categories ordered by position, with translations eagerly loaded.
     * Optionally filtered by channel.
     *
     * @return list<MenuCategory>
     */
    public function findMenuOrdered(?Channel $channel = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.translations', 't')
            ->addSelect('t')
            ->where('c.isFooter = false')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if (null !== $channel) {
            $queryBuilder->andWhere('c.channel = :channel')->setParameter('channel', $channel);
        }

        /** @var list<MenuCategory> $categories */
        $categories = $queryBuilder->getQuery()->getResult();

        return $categories;
    }

    /**
     * Find footer categories ordered by position, with translations eagerly loaded.
     * Optionally filtered by channel.
     *
     * @return list<MenuCategory>
     */
    public function findFooterOrdered(?Channel $channel = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.translations', 't')
            ->addSelect('t')
            ->where('c.isFooter = true')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if (null !== $channel) {
            $queryBuilder->andWhere('c.channel = :channel')->setParameter('channel', $channel);
        }

        /** @var list<MenuCategory> $categories */
        $categories = $queryBuilder->getQuery()->getResult();

        return $categories;
    }

    /**
     * Update positions for categories.
     *
     * @param list<int> $categoryIds ordered list of category IDs (index = new position)
     */
    public function reorderCategories(array $categoryIds): void
    {
        foreach ($categoryIds as $position => $categoryId) {
            $this->createQueryBuilder('c')
                ->update()
                ->set('c.position', ':position')
                ->where('c.id = :id')
                ->setParameter('position', $position)
                ->setParameter('id', $categoryId)
                ->getQuery()
                ->execute();
        }
    }

    /**
     * Find non-footer categories that have at least one published page, ordered by position.
     * Filtered by channel for navigation menu rendering.
     *
     * @return list<MenuCategory>
     */
    public function findWithPublishedPages(?Channel $channel = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.translations', 't')
            ->addSelect('t')
            ->innerJoin('c.pages', 'p')
            ->addSelect('p')
            ->leftJoin('p.translations', 'pt')
            ->addSelect('pt')
            ->where('p.isPublished = true')
            ->andWhere('c.isFooter = false')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if (null !== $channel) {
            $queryBuilder->andWhere('c.channel = :channel')->setParameter('channel', $channel);
        }

        /** @var list<MenuCategory> $categories */
        $categories = $queryBuilder->getQuery()->getResult();

        return $categories;
    }

    /**
     * Find footer categories that have at least one published page, ordered by position.
     * Filtered by channel for footer rendering.
     *
     * @return list<MenuCategory>
     */
    public function findFooterWithPublishedPages(?Channel $channel = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.translations', 't')
            ->addSelect('t')
            ->innerJoin('c.pages', 'p')
            ->addSelect('p')
            ->leftJoin('p.translations', 'pt')
            ->addSelect('pt')
            ->where('p.isPublished = true')
            ->andWhere('c.isFooter = true')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if (null !== $channel) {
            $queryBuilder->andWhere('c.channel = :channel')->setParameter('channel', $channel);
        }

        /** @var list<MenuCategory> $categories */
        $categories = $queryBuilder->getQuery()->getResult();

        return $categories;
    }
}
