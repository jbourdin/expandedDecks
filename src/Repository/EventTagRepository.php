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

use App\Entity\EventTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @see docs/features.md F3.12 — Event tags
 *
 * @extends ServiceEntityRepository<EventTag>
 */
class EventTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventTag::class);
    }

    public function findOneBySlug(string $slug): ?EventTag
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<EventTag>
     */
    public function findAllOrderedByName(): array
    {
        /** @var list<EventTag> $tags */
        $tags = $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $tags;
    }

    /**
     * Resolve a list of free-form tag names to existing tags + newly-instantiated
     * (un-flushed) EventTag rows for unknown names. The caller persists new ones.
     *
     * @param list<string> $names
     *
     * @return list<EventTag>
     */
    public function resolveByNames(array $names): array
    {
        $resolved = [];
        $seenSlugs = [];

        foreach ($names as $rawName) {
            $name = trim($rawName);

            if ('' === $name) {
                continue;
            }

            $slug = EventTag::slugify($name);

            if ('' === $slug || isset($seenSlugs[$slug])) {
                continue;
            }

            $seenSlugs[$slug] = true;

            $existing = $this->findOneBySlug($slug);

            if (null !== $existing) {
                $resolved[] = $existing;

                continue;
            }

            $tag = new EventTag();
            $tag->setName($name);
            $resolved[] = $tag;
        }

        return $resolved;
    }
}
