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
            ->andWhere('EXISTS (
                SELECT d.id FROM App\Entity\Deck d
                WHERE d.archetype = a
                AND d.public = :public
                AND d.status != :retired
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
     * @see docs/features.md F2.10 — Archetype detail page
     */
    public function findPublishedBySlug(string $slug): ?Archetype
    {
        /** @var Archetype|null $archetype */
        $archetype = $this->findOneBy(['slug' => $slug, 'isPublished' => true]);

        return $archetype;
    }
}
