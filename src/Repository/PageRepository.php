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
use App\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Page>
 *
 * @see docs/features.md F11.1 — Content pages
 */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    /**
     * Find a page by its slug, with translations eagerly loaded.
     *
     * @see docs/features.md F11.3 — Page rendering & locale fallback
     */
    public function findBySlug(string $slug, ?Channel $channel = null): ?Page
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->where('p.slug = :slug')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('slug', $slug);

        if (null !== $channel) {
            $queryBuilder->andWhere('p.channel = :channel')->setParameter('channel', $channel);
        }

        /** @var Page|null $page */
        $page = $queryBuilder->getQuery()->getOneOrNullResult();

        return $page;
    }

    /**
     * Find published pages in a given menu category, ordered by creation date (newest first).
     *
     * @return list<Page>
     */
    public function findPublishedByCategory(MenuCategory $category, ?int $limit = null): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->where('p.menuCategory = :category')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('category', $category)
            ->orderBy('p.createdAt', 'DESC');

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var list<Page> $pages */
        $pages = $queryBuilder->getQuery()->getResult();

        return $pages;
    }

    /**
     * Count published pages in a given menu category.
     */
    public function countPublishedByCategory(MenuCategory $category): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.menuCategory = :category')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * Build a query builder for the admin page list with optional search and category filter.
     *
     * @see docs/features.md F7.10 — Admin pages: filter by category and drag-and-drop sorting
     */
    public function createAdminListQueryBuilder(?string $search = null, ?MenuCategory $category = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->leftJoin('p.menuCategory', 'c')
            ->addSelect('c');

        if (null !== $category) {
            $queryBuilder
                ->andWhere('p.menuCategory = :category')
                ->setParameter('category', $category)
                ->orderBy('p.position', 'ASC')
                ->addOrderBy('p.createdAt', 'DESC');
        } else {
            $queryBuilder->orderBy('p.createdAt', 'DESC');
        }

        if (null !== $search && '' !== $search) {
            $queryBuilder
                ->andWhere('p.slug LIKE :search OR t.title LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $queryBuilder;
    }

    /**
     * Update positions for pages within a category.
     *
     * @param list<int> $pageIds ordered list of page IDs (index = new position)
     *
     * @see docs/features.md F7.10 — Admin pages: filter by category and drag-and-drop sorting
     */
    public function reorderPages(array $pageIds): void
    {
        foreach ($pageIds as $position => $pageId) {
            $this->createQueryBuilder('p')
                ->update()
                ->set('p.position', ':position')
                ->where('p.id = :id')
                ->setParameter('position', $position)
                ->setParameter('id', $pageId)
                ->getQuery()
                ->execute();
        }
    }
}
