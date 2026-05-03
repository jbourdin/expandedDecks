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

namespace App\Tests\Functional;

use App\Entity\PokemonSpriteMapping;
use App\Repository\PokemonSpriteMappingRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class PokemonSpriteMappingRepositoryTest extends AbstractFunctionalTest
{
    public function testFindPokedexIdBySlugReturnsMatchingId(): void
    {
        $this->persistMapping('pikachu', 25);
        $this->persistMapping('bulbasaur', 1);

        self::assertSame(25, $this->getRepository()->findPokedexIdBySlug('pikachu'));
    }

    public function testFindPokedexIdBySlugReturnsNullWhenSlugMissing(): void
    {
        self::assertNull($this->getRepository()->findPokedexIdBySlug('nonexistent-pokemon'));
    }

    public function testFindAllSlugsReturnsListOrderedAlphabetically(): void
    {
        $this->persistMapping('pikachu', 25);
        $this->persistMapping('arceus', 493);
        $this->persistMapping('bulbasaur', 1);

        $slugs = $this->getRepository()->findAllSlugs();

        self::assertSame(['arceus', 'bulbasaur', 'pikachu'], $slugs);
    }

    public function testFindAllSlugsReturnsEmptyListWhenNoMappings(): void
    {
        self::assertSame([], $this->getRepository()->findAllSlugs());
    }

    private function getRepository(): PokemonSpriteMappingRepository
    {
        /** @var PokemonSpriteMappingRepository $repository */
        $repository = static::getContainer()->get(PokemonSpriteMappingRepository::class);

        return $repository;
    }

    private function persistMapping(string $slug, int $pokedexId): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $mapping = new PokemonSpriteMapping();
        $mapping->setSlug($slug);
        $mapping->setPokedexId($pokedexId);
        $em->persist($mapping);
        $em->flush();
    }
}
