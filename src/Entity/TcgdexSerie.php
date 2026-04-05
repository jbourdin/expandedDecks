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

use App\Repository\TcgdexSerieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A TCGdex serie (top-level grouping, e.g. "Scarlet & Violet", "Sword & Shield").
 *
 * This is a local mirror of TCGdex data, seeded from the tcgdex/cards-database repository.
 */
#[ORM\Entity(repositoryClass: TcgdexSerieRepository::class)]
#[ORM\Table(name: 'tcgdex_serie')]
class TcgdexSerie
{
    /** TCGdex serie ID (e.g. "sv", "swsh", "sm"). */
    #[ORM\Id]
    #[ORM\Column(length: 20)]
    private string $id;

    /**
     * Multilingual name: {"en": "Scarlet & Violet", "fr": "Écarlate et Violet", ...}.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $name = [];

    /** @var Collection<int, TcgdexSet> */
    #[ORM\OneToMany(targetEntity: TcgdexSet::class, mappedBy: 'serie')]
    private Collection $sets;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->sets = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function getName(): array
    {
        return $this->name;
    }

    public function getLocalizedName(string $locale = 'en'): ?string
    {
        $value = $this->name[$locale] ?? $this->name['en'] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $name
     */
    public function setName(array $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, TcgdexSet>
     */
    public function getSets(): Collection
    {
        return $this->sets;
    }
}
