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
     * Resolve a page by slug: first try localized translation slug,
     * then fall back to the canonical Page.slug.
     *
     * @see docs/features.md F11.3 — Page rendering & locale fallback
     */
    public function findBySlug(string $slug): ?Page
    {
        // Try localized slug first
        /** @var Page|null $page */
        $page = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->where('t.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();

        if ($page instanceof Page) {
            return $page;
        }

        // Fall back to canonical slug
        /** @var Page|null $page */
        $page = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();

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
     * Build a query builder for the admin page list with optional search.
     */
    public function createAdminListQueryBuilder(?string $search = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->leftJoin('p.menuCategory', 'c')
            ->addSelect('c')
            ->orderBy('p.createdAt', 'DESC');

        if (null !== $search && '' !== $search) {
            $queryBuilder
                ->andWhere('p.slug LIKE :search OR t.title LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $queryBuilder;
    }
}
