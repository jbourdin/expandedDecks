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

use App\Repository\MenuCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @see docs/features.md F11.2 — Menu categories
 */
#[ORM\Entity(repositoryClass: MenuCategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MenuCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, MenuCategoryTranslation> */
    #[ORM\OneToMany(targetEntity: MenuCategoryTranslation::class, mappedBy: 'menuCategory', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    /** @var Collection<int, Page> */
    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'menuCategory')]
    private Collection $pages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->translations = new ArrayCollection();
        $this->pages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, MenuCategoryTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(MenuCategoryTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setMenuCategory($this);
        }

        return $this;
    }

    public function removeTranslation(MenuCategoryTranslation $translation): static
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    /**
     * Get the translation for a given locale, with fallback to 'en'.
     */
    public function getTranslation(string $locale): ?MenuCategoryTranslation
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
     * Convenience method to get the translated name for a locale.
     */
    public function getName(string $locale = 'en'): string
    {
        return $this->getTranslation($locale)?->getName() ?? '';
    }

    /**
     * @return Collection<int, Page>
     */
    public function getPages(): Collection
    {
        return $this->pages;
    }
}
