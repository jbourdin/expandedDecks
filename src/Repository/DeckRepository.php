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
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\EventDeckRegistration;
use App\Entity\User;
use App\Enum\DeckFormat;
use App\Enum\DeckStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deck>
 */
class DeckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deck::class);
    }

    /**
     * @see docs/features.md F7.1 — Dashboard
     */
    public function countAll(): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status != :retired')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('retired', DeckStatus::Retired)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * Count distinct decks registered at upcoming events where the user is organizer or staff.
     *
     * @see docs/features.md F7.1 — Dashboard
     */
    public function countRegisteredByOrganizerOrStaff(User $user): int
    {
        /** @var int $count */
        $count = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT r.deck)')
            ->from(EventDeckRegistration::class, 'r')
            ->join('r.event', 'e')
            ->leftJoin('e.staff', 's', 'WITH', 's.user = :user')
            ->where('e.organizer = :user OR s.id IS NOT NULL')
            ->andWhere('e.date >= :today')
            ->andWhere('e.cancelledAt IS NULL')
            ->andWhere('e.finishedAt IS NULL')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * @see docs/features.md F10.2 — Anonymous homepage
     */
    public function countPublicDecks(): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.public = :public')
            ->andWhere('d.status != :retired')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('d.format = :format')
            ->setParameter('public', true)
            ->setParameter('retired', DeckStatus::Retired)
            ->setParameter('format', DeckFormat::Expanded)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * Count all decks (regardless of status or visibility) assigned to an archetype.
     *
     * @see docs/models/deck.md — Archetype soft-delete rules
     */
    public function countAllByArchetype(Archetype $archetype): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.archetype = :archetype')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('archetype', $archetype)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * @see docs/features.md F2.10 — Archetype detail page
     */
    public function countPublicByArchetype(Archetype $archetype): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.public = :public')
            ->andWhere('d.status != :retired')
            ->andWhere('d.archetype = :archetype')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('d.format = :format')
            ->setParameter('public', true)
            ->setParameter('retired', DeckStatus::Retired)
            ->setParameter('archetype', $archetype)
            ->setParameter('format', DeckFormat::Expanded)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * @see docs/features.md F2.10 — Archetype detail page
     *
     * @return list<Deck>
     */
    public function findLatestPublicByArchetype(Archetype $archetype, int $limit = 5): array
    {
        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->join('d.owner', 'o')
            ->addSelect('o')
            ->where('d.public = :public')
            ->andWhere('d.status != :retired')
            ->andWhere('d.archetype = :archetype')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('d.format = :format')
            ->setParameter('public', true)
            ->setParameter('retired', DeckStatus::Retired)
            ->setParameter('archetype', $archetype)
            ->setParameter('format', DeckFormat::Expanded)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * Find archetype variant decks (no owner), ordered canonical first, then by creation date.
     *
     * @see docs/features.md F18.13 — Archetype variant decks
     *
     * @return list<Deck>
     */
    public function findVariantsByArchetype(Archetype $archetype): array
    {
        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->leftJoin('d.currentVersion', 'cv')
            ->addSelect('cv')
            ->where('d.archetype = :archetype')
            ->andWhere('d.owner IS NULL')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('archetype', $archetype)
            ->orderBy('d.canonical', 'DESC')
            ->addOrderBy('d.position', 'ASC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     *
     * @return list<Deck>
     */
    public function findAvailableDecks(): array
    {
        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->join('d.owner', 'o')
            ->leftJoin('d.currentVersion', 'cv')
            ->addSelect('o', 'cv')
            ->where('d.status = :status')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('status', DeckStatus::Available)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * @see docs/features.md F2.1 — Register a new deck (owner)
     *
     * @return list<Deck>
     */
    public function findByOwner(User $owner): array
    {
        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->leftJoin('d.currentVersion', 'cv')
            ->addSelect('cv')
            ->where('d.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * @see docs/features.md F7.1 — Dashboard
     *
     * @return list<Deck>
     */
    public function findActiveExpandedByOwner(User $owner): array
    {
        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->leftJoin('d.currentVersion', 'cv')
            ->addSelect('cv')
            ->where('d.owner = :owner')
            ->andWhere('d.format = :expandedFormat')
            ->andWhere('d.status != :retired')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('owner', $owner)
            ->setParameter('expandedFormat', DeckFormat::Expanded)
            ->setParameter('retired', DeckStatus::Retired)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * @see docs/features.md F4.12 — Walk-up lending (direct lend)
     *
     * @return list<Deck>
     */
    public function searchAvailableForEvent(string $query, Event $event, int $limit = 10): array
    {
        $eventDate = $event->getDate();
        $startOfDay = new \DateTimeImmutable($eventDate->format('Y-m-d').' 00:00:00');
        $endDate = $event->getEndDate() ?? $eventDate;
        $endOfDay = new \DateTimeImmutable($endDate->format('Y-m-d').' 23:59:59');

        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->join('d.owner', 'o')
            ->addSelect('o')
            ->where('d.status != :retired')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('d.currentVersion IS NOT NULL')
            ->andWhere('d.format = :expandedFormat')
            ->andWhere('d.personal = false')
            ->andWhere('d.name LIKE :query OR d.shortTag LIKE :query')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\Borrow b
                JOIN b.event e2
                WHERE b.deck = d
                AND b.status IN (:activeStatuses)
                AND e2.date <= :endOfDay
                AND COALESCE(e2.endDate, e2.date) >= :startOfDay
            )')
            ->setParameter('retired', DeckStatus::Retired)
            ->setParameter('expandedFormat', DeckFormat::Expanded)
            ->setParameter('query', '%'.$query.'%')
            ->setParameter('activeStatuses', BorrowRepository::blockingStatusValues())
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('d.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * Find decks available to borrow for an event: must be registered at this event
     * (via EventDeckRegistration), not retired, not owned by the requesting user
     * (when one is provided — anonymous viewers see all owners), not already being
     * played (via EventDeckEntry), and not already borrowed.
     *
     * @see docs/features.md F4.1 — Request to borrow a deck
     * @see docs/features.md F4.11 — Borrow conflict detection
     * @see docs/features.md F3.24 — Public event detail page
     *
     * @return list<Deck>
     */
    public function findAvailableForEvent(Event $event, ?User $excludeOwner): array
    {
        $eventDate = $event->getDate();
        $startOfDay = new \DateTimeImmutable($eventDate->format('Y-m-d').' 00:00:00');
        $endDate = $event->getEndDate() ?? $eventDate;
        $endOfDay = new \DateTimeImmutable($endDate->format('Y-m-d').' 23:59:59');

        $queryBuilder = $this->createQueryBuilder('d')
            ->join('d.owner', 'o')
            ->addSelect('o')
            ->join('App\Entity\EventDeckRegistration', 'r', 'WITH', 'r.deck = d AND r.event = :event')
            ->where('d.status != :retired')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('d.format = :expandedFormat')
            ->andWhere('d.personal = false')
            ->andWhere('d.currentVersion IS NOT NULL')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\EventDeckEntry ede
                WHERE ede.deckVersion = d.currentVersion
                AND ede.event = :event
            )')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\Borrow b
                JOIN b.event e2
                WHERE b.deck = d
                AND b.status IN (:activeStatuses)
                AND e2.date <= :endOfDay
                AND COALESCE(e2.endDate, e2.date) >= :startOfDay
            )')
            ->setParameter('event', $event)
            ->setParameter('retired', DeckStatus::Retired)
            ->setParameter('expandedFormat', DeckFormat::Expanded)
            ->setParameter('activeStatuses', BorrowRepository::blockingStatusValues())
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('d.name', 'ASC');

        if ($excludeOwner instanceof User) {
            $queryBuilder
                ->andWhere('d.owner != :excludeOwner')
                ->setParameter('excludeOwner', $excludeOwner);
        }

        /** @var list<Deck> $decks */
        $decks = $queryBuilder->getQuery()->getResult();

        return $decks;
    }

    /**
     * Find all public, non-retired decks, returning only sitemap-relevant fields.
     *
     * @see docs/features.md F18.23 — Dynamic sitemap generation
     *
     * @return list<array{shortTag: string, updatedAt: ?\DateTimeImmutable, createdAt: \DateTimeImmutable}>
     */
    public function findPublicForSitemap(): array
    {
        /** @var list<array{shortTag: string, updatedAt: ?\DateTimeImmutable, createdAt: \DateTimeImmutable}> $results */
        $results = $this->createQueryBuilder('d')
            ->select('d.shortTag', 'd.updatedAt', 'd.createdAt')
            ->where('d.public = :public')
            ->andWhere('d.status != :retired')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('d.format = :format')
            ->setParameter('public', true)
            ->setParameter('retired', DeckStatus::Retired)
            ->setParameter('format', DeckFormat::Expanded)
            ->orderBy('d.updatedAt', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Return all public, owned decks for search indexing.
     *
     * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
     *
     * @return list<Deck>
     */
    public function findPublicForSearch(): array
    {
        /** @var list<Deck> $results */
        $results = $this->createQueryBuilder('d')
            ->addSelect('a', 'o')
            ->leftJoin('d.archetype', 'a')
            ->leftJoin('d.owner', 'o')
            ->where('d.public = :public')
            ->andWhere('d.owner IS NOT NULL')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('d.format = :format')
            ->setParameter('public', true)
            ->setParameter('format', DeckFormat::Expanded)
            ->orderBy('d.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Return all archetype variant decks (no owner) with cards eagerly loaded.
     *
     * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
     *
     * @return list<Deck>
     */
    public function findVariantsForSearch(): array
    {
        /** @var list<Deck> $results */
        $results = $this->createQueryBuilder('d')
            ->addSelect('a', 'cv', 'c')
            ->leftJoin('d.archetype', 'a')
            ->leftJoin('d.currentVersion', 'cv')
            ->leftJoin('cv.cards', 'c')
            ->where('d.owner IS NULL')
            ->andWhere('d.archetype IS NOT NULL')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('a.isPublished = true')
            ->andWhere('a.deletedAt IS NULL')
            ->orderBy('a.name', 'ASC')
            ->addOrderBy('d.canonical', 'DESC')
            ->addOrderBy('d.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Build a query for the public deck catalog with optional filters.
     *
     * When selfOwner is true the public-visibility constraint is dropped,
     * so the owner sees all their own decks (including private ones).
     *
     * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
     *
     * @param array{search?: string, archetype?: string, owner?: int, event?: int, selfOwner?: bool, format?: string} $filters
     */
    public function createCatalogQueryBuilder(array $filters = []): QueryBuilder
    {
        $selfOwner = $filters['selfOwner'] ?? false;

        $qb = $this->createQueryBuilder('d')
            ->join('d.owner', 'o')
            ->leftJoin('d.archetype', 'a')
            ->addSelect('o', 'a')
            ->andWhere('d.deletedAt IS NULL')
            ->orderBy('d.updatedAt', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC');

        if (!$selfOwner) {
            $qb->andWhere('d.status != :retired')
                ->setParameter('retired', DeckStatus::Retired)
                ->andWhere('d.public = :public')
                ->setParameter('public', true)
                ->andWhere('d.format = :format')
                ->setParameter('format', DeckFormat::Expanded);
        }

        if (isset($filters['search']) && '' !== $filters['search']) {
            $qb->andWhere('d.name LIKE :search OR d.shortTag LIKE :search')
                ->setParameter('search', '%'.$filters['search'].'%');
        }

        if (isset($filters['archetype']) && '' !== $filters['archetype']) {
            $qb->andWhere('a.slug = :archetype')
                ->setParameter('archetype', $filters['archetype']);
        }

        if (isset($filters['owner']) && $filters['owner'] > 0) {
            $qb->andWhere('o.id = :owner')
                ->setParameter('owner', $filters['owner']);
        }

        if (isset($filters['event']) && $filters['event'] > 0) {
            $qb->join('App\Entity\EventDeckRegistration', 'edr', 'WITH', 'edr.deck = d AND edr.event = :event')
                ->setParameter('event', $filters['event']);
        }

        if (isset($filters['format']) && '' !== $filters['format']) {
            $deckFormat = DeckFormat::tryFrom($filters['format']);
            if (null !== $deckFormat) {
                $qb->andWhere('d.format = :formatFilter')
                    ->setParameter('formatFilter', $deckFormat);
            }
        }

        return $qb;
    }
}
