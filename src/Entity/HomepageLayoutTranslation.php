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

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stores per-locale translatable content for homepage blocks.
 * The blockTranslations JSON is keyed by block index, containing translatable fields
 * (e.g. title, subtitle, content) for each block that has translatable content.
 *
 * @see docs/features.md F10.3 — HomepageLayout entity and data model
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'homepage_layout_translation_unique', columns: ['homepage_layout_id', 'locale'])]
class HomepageLayoutTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HomepageLayout::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private HomepageLayout $homepageLayout;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5)]
    private string $locale = 'en';

    /**
     * Per-block translatable content, keyed by block index.
     * Example: {"0": {"title": "Welcome", "subtitle": "..."}, "2": {"content": "..."}}.
     * Note: PHP coerces numeric string keys to int, so keys may be int|string at runtime.
     *
     * @var array<int|string, array<string, mixed>>
     */
    #[ORM\Column(type: 'json')]
    private array $blockTranslations = [];

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    // length 65535 maps to MySQL TEXT (not LONGTEXT), matching the live schema.
    #[ORM\Column(type: 'text', length: 65535, nullable: true)]
    private ?string $ogDescription = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHomepageLayout(): HomepageLayout
    {
        return $this->homepageLayout;
    }

    public function setHomepageLayout(HomepageLayout $homepageLayout): static
    {
        $this->homepageLayout = $homepageLayout;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getBlockTranslations(): array
    {
        return $this->blockTranslations;
    }

    /**
     * @param array<int|string, array<string, mixed>> $blockTranslations
     */
    public function setBlockTranslations(array $blockTranslations): static
    {
        $this->blockTranslations = $blockTranslations;

        return $this;
    }

    /**
     * Get translatable fields for a specific block by its index.
     *
     * @return array<string, mixed>
     */
    public function getBlockTranslation(int $blockIndex): array
    {
        return $this->blockTranslations[(string) $blockIndex] ?? [];
    }

    /**
     * Set translatable fields for a specific block by its index.
     *
     * @param array<string, mixed> $translation
     */
    public function setBlockTranslation(int $blockIndex, array $translation): static
    {
        $this->blockTranslations[$blockIndex] = $translation;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getOgDescription(): ?string
    {
        return $this->ogDescription;
    }

    public function setOgDescription(?string $ogDescription): static
    {
        $this->ogDescription = $ogDescription;

        return $this;
    }
}
