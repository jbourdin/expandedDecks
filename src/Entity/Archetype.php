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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\PreUpdateEventArgs;
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
    use PublishableTimestampsTrait;

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
     * @var list<string>
     *
     * @see docs/features.md F2.15 — Archetype playstyle tags
     */
    #[ORM\Column(type: Types::JSON)]
    private array $playstyleTags = [];

    /**
     * @see docs/features.md F2.10 — Archetype detail page
     */
    #[ORM\Column]
    private bool $isPublished = false;

    /**
     * Display order in the archetype catalog (lower = first).
     *
     * @see docs/features.md F18.11 — Archetype relevance ordering
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * @var Collection<int, ArchetypeTranslation>
     *
     * @see docs/features.md F9.6 — Archetype localization
     */
    #[ORM\OneToMany(targetEntity: ArchetypeTranslation::class, mappedBy: 'archetype', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->translations = new ArrayCollection();
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

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
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

    /**
     * @return list<string>
     *
     * @see docs/features.md F2.15 — Archetype playstyle tags
     */
    public function getPlaystyleTags(): array
    {
        return $this->playstyleTags;
    }

    /**
     * @param list<string> $playstyleTags
     *
     * @see docs/features.md F2.15 — Archetype playstyle tags
     */
    public function setPlaystyleTags(array $playstyleTags): static
    {
        $this->playstyleTags = $playstyleTags;

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

    /**
     * @see docs/features.md F18.11 — Archetype relevance ordering
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @see docs/features.md F18.11 — Archetype relevance ordering
     */
    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return Collection<int, ArchetypeTranslation>
     *
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function addTranslation(ArchetypeTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setArchetype($this);
        }

        return $this;
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function removeTranslation(ArchetypeTranslation $translation): static
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    /**
     * Get the translation for a given locale, with fallback to 'en'.
     *
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function getTranslation(string $locale): ?ArchetypeTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        if ('en' !== $locale) {
            foreach ($this->translations as $translation) {
                if ('en' === $translation->getLocale()) {
                    return $translation;
                }
            }
        }

        return null;
    }

    /**
     * Get the localized name, falling back to the canonical name.
     *
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function getLocalizedName(string $locale = 'en'): string
    {
        return $this->getTranslation($locale)?->getName() ?? $this->name;
    }

    /**
     * Get the localized description (falls back to EN translation).
     *
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function getLocalizedDescription(string $locale = 'en'): ?string
    {
        return $this->getTranslation($locale)?->getDescription();
    }

    /**
     * Get the localized meta description (falls back to EN translation).
     *
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function getLocalizedMetaDescription(string $locale = 'en'): ?string
    {
        return $this->getTranslation($locale)?->getMetaDescription();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->generateSlug();
        $this->stampPublicationOnPersist();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(PreUpdateEventArgs $args): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->generateSlug();
        $this->stampPublicationOnUpdate($args);
    }

    private function generateSlug(): void
    {
        if ('' !== $this->name) {
            $slugger = new AsciiSlugger();
            $this->slug = $slugger->slug($this->name)->lower()->toString();
        }
    }
}
