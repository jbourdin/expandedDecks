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

use App\Entity\Archetype;
use App\Enum\DeckStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Archetype>
 */
class ArchetypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Archetype::class);
    }

    /**
     * @see docs/features.md F2.6 — Archetype management (create, browse, detail)
     *
     * @return list<Archetype>
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        /** @var list<Archetype> $archetypes */
        $archetypes = $this->createQueryBuilder('a')
            ->where('a.name LIKE :query')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('a.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $archetypes;
    }

    /**
     * Find all published archetypes that have at least one public, non-retired deck.
     *
     * @see docs/features.md F2.17 — Deck catalog archetype filter UX
     *
     * @return list<Archetype>
     */
    public function findPublishedWithPublicDecks(): array
    {
        /** @var list<Archetype> $archetypes */
        $archetypes = $this->createQueryBuilder('a')
            ->where('a.isPublished = :published')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('EXISTS (
                SELECT d.id FROM App\Entity\Deck d
                WHERE d.archetype = a
                AND d.public = :public
                AND d.status != :retired
                AND d.deletedAt IS NULL
            )')
            ->setParameter('published', true)
            ->setParameter('public', true)
            ->setParameter('retired', DeckStatus::Retired)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $archetypes;
    }

    /**
     * Find all published archetypes with their public deck count.
     *
     * Returns an array of [archetype => Archetype, deckCount => int] rows,
     * optionally filtered by playstyle tags and sorted by the given criteria.
     * The tags filter combines selected tags with AND (must match every tag) by
     * default, or OR (matches any) when `$tagsMode === 'or'`. Any other value is
     * treated as `'and'`.
     *
     * @see docs/features.md F2.16 — Archetype catalog
     * @see https://github.com/jbourdin/expandedDecks/issues/548
     *
     * @param list<string> $tags     playstyle tags to filter by
     * @param string       $tagsMode 'and' (default — matches every selected tag) or 'or' (matches any)
     *
     * @return list<array{archetype: Archetype, deckCount: int}>
     */
    public function findPublishedWithDeckCounts(array $tags = [], string $sort = 'name', string $tagsMode = 'and'): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a AS archetype')
            ->addSelect('(
                SELECT COUNT(d.id) FROM App\Entity\Deck d
                WHERE d.archetype = a
                AND d.public = :public
                AND d.status != :retired
                AND d.deletedAt IS NULL
            ) AS deckCount')
            ->where('a.isPublished = :published')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('published', true)
            ->setParameter('public', true)
            ->setParameter('retired', DeckStatus::Retired);

        if ([] !== $tags) {
            $tagConditions = [];
            foreach ($tags as $index => $tag) {
                $paramName = 'tag'.$index;
                $tagConditions[] = 'a.playstyleTags LIKE :'.$paramName;
                $qb->setParameter($paramName, '%"'.$tag.'"%');
            }
            $separator = 'or' === $tagsMode ? ' OR ' : ' AND ';
            $qb->andWhere(implode($separator, $tagConditions));
        }

        if ('decks' === $sort) {
            $qb->orderBy('deckCount', 'DESC')
                ->addOrderBy('a.name', 'ASC');
        } elseif ('position' === $sort) {
            $qb->orderBy('a.position', 'ASC')
                ->addOrderBy('a.name', 'ASC');
        } elseif ('updatedAt' === $sort) {
            // COALESCE keeps the sort stable for rows that pre-date the F2.27 backfill
            // or were never republished (lastPublishedAt → firstPublishedAt → createdAt).
            $qb->addSelect('COALESCE(a.lastPublishedAt, a.firstPublishedAt, a.createdAt) AS HIDDEN effectiveUpdatedAt')
                ->orderBy('effectiveUpdatedAt', 'DESC')
                ->addOrderBy('a.name', 'ASC');
        } else {
            $qb->orderBy('a.name', 'ASC');
        }

        /** @var list<array{archetype: Archetype, deckCount: int}> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    /**
     * Return all unpublished (draft) archetypes with their deck counts, sorted by name.
     *
     * @see docs/features.md F7.11 — Draft state with preview
     *
     * @return list<array{archetype: Archetype, deckCount: int}>
     */
    public function findUnpublishedWithDeckCounts(): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a AS archetype')
            ->addSelect('(
                SELECT COUNT(d.id) FROM App\Entity\Deck d
                WHERE d.archetype = a
                AND d.public = :public
                AND d.status != :retired
                AND d.deletedAt IS NULL
            ) AS deckCount')
            ->where('a.isPublished = :published')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('published', false)
            ->setParameter('public', true)
            ->setParameter('retired', DeckStatus::Retired)
            ->orderBy('a.name', 'ASC');

        /** @var list<array{archetype: Archetype, deckCount: int}> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    /**
     * Collect all unique playstyle tags from published archetypes.
     *
     * @see docs/features.md F2.16 — Archetype catalog
     *
     * @return list<string>
     */
    public function findAllPublishedPlaystyleTags(): array
    {
        /** @var list<array{playstyleTags: list<string>}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('a.playstyleTags')
            ->where('a.isPublished = :published')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('published', true)
            ->getQuery()
            ->getResult();

        $tags = [];
        foreach ($rows as $row) {
            foreach ($row['playstyleTags'] as $tag) {
                $tags[$tag] = true;
            }
        }

        $tagList = array_keys($tags);
        sort($tagList);

        return $tagList;
    }

    /**
     * Find all published archetypes, returning only sitemap-relevant fields.
     *
     * @see docs/features.md F18.23 — Dynamic sitemap generation
     *
     * @return list<array{slug: string, updatedAt: ?\DateTimeImmutable, createdAt: \DateTimeImmutable}>
     */
    public function findPublishedForSitemap(): array
    {
        /** @var list<array{slug: string, updatedAt: ?\DateTimeImmutable, createdAt: \DateTimeImmutable}> $results */
        $results = $this->createQueryBuilder('a')
            ->select('a.slug', 'a.updatedAt', 'a.createdAt')
            ->where('a.isPublished = :published')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('published', true)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Return all published archetypes with their translations eagerly loaded.
     *
     * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
     *
     * @return list<Archetype>
     */
    public function findPublishedForSearch(): array
    {
        /** @var list<Archetype> $results */
        $results = $this->createQueryBuilder('a')
            ->addSelect('t')
            ->leftJoin('a.translations', 't')
            ->where('a.isPublished = :published')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('published', true)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * @see docs/features.md F2.10 — Archetype detail page
     */
    public function findPublishedBySlug(string $slug): ?Archetype
    {
        /** @var Archetype|null $archetype */
        $archetype = $this->createQueryBuilder('a')
            ->where('a.slug = :slug')
            ->andWhere('a.isPublished = :published')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('slug', $slug)
            ->setParameter('published', true)
            ->getQuery()
            ->getOneOrNullResult();

        return $archetype;
    }
}
