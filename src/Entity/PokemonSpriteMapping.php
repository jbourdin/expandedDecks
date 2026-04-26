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

namespace App\Entity;

use App\Repository\PokemonSpriteMappingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Maps a Pokemon slug (e.g. "iron-thorns") to its PokeAPI national dex ID
 * (e.g. 995), used by the sprite proxy controller to fetch HOME 3D renders.
 *
 * Populated from PokeAPI's pokemon.csv via the app:sprites:sync-mapping command.
 *
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
#[ORM\Entity(repositoryClass: PokemonSpriteMappingRepository::class)]
#[ORM\Table(name: 'pokemon_sprite_mapping')]
#[ORM\UniqueConstraint(name: 'uniq_sprite_slug', columns: ['slug'])]
class PokemonSpriteMapping
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $slug = '';

    #[ORM\Column]
    private int $pokedexId = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPokedexId(): int
    {
        return $this->pokedexId;
    }

    public function setPokedexId(int $pokedexId): static
    {
        $this->pokedexId = $pokedexId;

        return $this;
    }
}
