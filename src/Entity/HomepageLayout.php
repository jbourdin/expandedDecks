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

use App\Repository\HomepageLayoutRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stores the homepage block layout as a JSON structure.
 * Only one layout should be published at a time (singleton pattern).
 *
 * @see docs/features.md F10.3 — HomepageLayout entity and data model
 */
#[ORM\Entity(repositoryClass: HomepageLayoutRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HomepageLayout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Ordered list of block definitions.
     * Each block has: type, columnWidth, cssClasses, startAt, endAt, and type-specific settings.
     *
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(type: 'json')]
    private array $blocks = [];

    #[ORM\Column]
    private bool $isPublished = false;

    /**
     * @see docs/features.md F18.10 — Add channel association to HomepageLayout
     */
    #[ORM\ManyToOne(targetEntity: Channel::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Channel $channel = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, HomepageLayoutTranslation> */
    #[ORM\OneToMany(targetEntity: HomepageLayoutTranslation::class, mappedBy: 'homepageLayout', cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    /**
     * @return list<array<string, mixed>>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    public function setBlocks(array $blocks): static
    {
        $this->blocks = $blocks;

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

    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    public function setChannel(?Channel $channel): static
    {
        $this->channel = $channel;

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
     * @return Collection<int, HomepageLayoutTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(HomepageLayoutTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setHomepageLayout($this);
        }

        return $this;
    }

    public function removeTranslation(HomepageLayoutTranslation $translation): static
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    /**
     * Get the translation for a given locale, with fallback to 'en'.
     */
    public function getTranslation(string $locale): ?HomepageLayoutTranslation
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
}
