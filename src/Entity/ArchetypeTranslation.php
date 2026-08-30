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

use App\Attribute\Translatable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F9.6 — Archetype localization
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'archetype_translation_unique', columns: ['archetype_id', 'locale'])]
#[UniqueEntity(fields: ['archetype', 'locale'], message: 'app.cms.translation_locale_unique')]
class ArchetypeTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Archetype::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private Archetype $archetype;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5)]
    private string $locale = 'en';

    #[Translatable]
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $name = '';

    #[Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[Translatable]
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $metaDescription = null;

    /**
     * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '#^(/|https?://)#', message: 'app.cms.og_image_url_format')]
    private ?string $ogImage = null;

    /**
     * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
     */
    #[Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ogDescription = null;

    /**
     * Set when a newer source-locale revision exists than the one this
     * translation was based on; cleared when an up-to-date revision is
     * approved (F9.9). Denormalized so public pages never query the
     * revision tables (US-T6).
     *
     * @see docs/features.md F9.7 — Translation foundation
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $sourceOutdated = false;

    /**
     * Public translator credit for this locale (F19.8). The workflow state
     * and source-version tracking live on `ArchetypeTranslationRevision` (#612).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $translator = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArchetype(): Archetype
    {
        return $this->archetype;
    }

    public function setArchetype(Archetype $archetype): static
    {
        $this->archetype = $archetype;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    /**
     * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
     */
    public function getOgImage(): ?string
    {
        return $this->ogImage;
    }

    /**
     * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
     */
    public function setOgImage(?string $ogImage): static
    {
        $this->ogImage = $ogImage;

        return $this;
    }

    /**
     * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
     */
    public function getOgDescription(): ?string
    {
        return $this->ogDescription;
    }

    /**
     * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
     */
    public function setOgDescription(?string $ogDescription): static
    {
        $this->ogDescription = $ogDescription;

        return $this;
    }

    public function isSourceOutdated(): bool
    {
        return $this->sourceOutdated;
    }

    public function setSourceOutdated(bool $sourceOutdated): static
    {
        $this->sourceOutdated = $sourceOutdated;

        return $this;
    }

    public function getTranslator(): ?User
    {
        return $this->translator;
    }

    public function setTranslator(?User $translator): static
    {
        $this->translator = $translator;

        return $this;
    }
}
