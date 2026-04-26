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

use App\Entity\PokemonSpriteMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PokemonSpriteMapping>
 *
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class PokemonSpriteMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PokemonSpriteMapping::class);
    }

    public function findPokedexIdBySlug(string $slug): ?int
    {
        /** @var ?int $result */
        $result = $this->createQueryBuilder('m')
            ->select('m.pokedexId')
            ->where('m.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult(\Doctrine\ORM\Query::HYDRATE_SINGLE_SCALAR);

        return $result;
    }

    /**
     * @return list<string>
     */
    public function findAllSlugs(): array
    {
        /** @var list<string> $slugs */
        $slugs = $this->createQueryBuilder('m')
            ->select('m.slug')
            ->orderBy('m.slug', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $slugs;
    }
}
