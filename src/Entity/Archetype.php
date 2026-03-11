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

use App\Repository\ArchetypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F2.6 — Archetype management (create, browse, detail)
 */
#[ORM\Entity(repositoryClass: ArchetypeRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_archetype_name', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'uniq_archetype_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Archetype
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $name = '';

    #[ORM\Column(length: 100)]
    private string $slug = '';

    /**
     * @var list<string>
     *
     * @see docs/features.md F2.12 — Archetype sprite pictograms
     */
    #[ORM\Column(type: Types::JSON)]
    private array $pokemonSlugs = [];

    /**
     * @see docs/features.md F2.10 — Archetype detail page
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @see docs/features.md F2.10 — Archetype detail page
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $metaDescription = null;

    /**
     * @see docs/features.md F2.10 — Archetype detail page
     */
    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return list<string>
     */
    public function getPokemonSlugs(): array
    {
        return $this->pokemonSlugs;
    }

    /**
     * @param list<string> $pokemonSlugs
     */
    public function setPokemonSlugs(array $pokemonSlugs): static
    {
        $this->pokemonSlugs = $pokemonSlugs;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->generateSlug();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->generateSlug();
    }

    private function generateSlug(): void
    {
        if ('' !== $this->name) {
            $slugger = new AsciiSlugger();
            $this->slug = $slugger->slug($this->name)->lower()->toString();
        }
    }
}
