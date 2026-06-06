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

use App\Repository\PageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F11.1 — Content pages
 */
#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_page_slug_channel', columns: ['slug', 'channel_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug', 'channel'], message: 'app.cms.slug_unique')]
class Page
{
    use PublishableTimestampsTrait;
    use TimestampExemptChangeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 150)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'app.cms.slug_format')]
    private string $slug = '';

    #[ORM\ManyToOne(targetEntity: Channel::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Channel $channel = null;

    #[ORM\ManyToOne(targetEntity: MenuCategory::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(nullable: true)]
    private ?MenuCategory $menuCategory = null;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '#^(/|https?://)#', message: 'app.cms.og_image_url_format')]
    private ?string $ogImage = null;

    #[ORM\Column]
    private bool $noIndex = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @var Collection<int, PageTranslation> */
    #[ORM\OneToMany(targetEntity: PageTranslation::class, mappedBy: 'page', cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getMenuCategory(): ?MenuCategory
    {
        return $this->menuCategory;
    }

    public function setMenuCategory(?MenuCategory $menuCategory): static
    {
        $this->menuCategory = $menuCategory;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getOgImage(): ?string
    {
        return $this->ogImage;
    }

    public function setOgImage(?string $ogImage): static
    {
        $this->ogImage = $ogImage;

        return $this;
    }

    public function isNoIndex(): bool
    {
        return $this->noIndex;
    }

    public function setNoIndex(bool $noIndex): static
    {
        $this->noIndex = $noIndex;

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

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->stampPublicationOnPersist();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(PreUpdateEventArgs $args): void
    {
        // Social-preview tuning (`ogImage`, F18.32) is not a content update, so
        // leave `updatedAt` and the publication timestamps untouched.
        if ($this->isTimestampExemptChange($args)) {
            return;
        }

        $this->updatedAt = new \DateTimeImmutable();
        $this->stampPublicationOnUpdate($args);
    }

    /**
     * @return Collection<int, PageTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(PageTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setPage($this);
        }

        return $this;
    }

    public function removeTranslation(PageTranslation $translation): static
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    /**
     * Get the translation for a given locale, with fallback to 'en'.
     *
     * @see docs/features.md F11.3 — Page rendering & locale fallback
     */
    public function getTranslation(string $locale): ?PageTranslation
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
     * Get the translation for display, skipping translations with empty content
     * and falling back to 'en'.
     *
     * @see docs/features.md F11.3 — Page rendering & locale fallback
     */
    public function getDisplayTranslation(string $locale): ?PageTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale && '' !== $translation->getContent()) {
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
     * Convenience: get the title for a locale.
     */
    public function getTitle(string $locale = 'en'): string
    {
        return $this->getDisplayTranslation($locale)?->getTitle() ?? '';
    }
}
