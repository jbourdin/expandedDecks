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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @see docs/features.md F9.7 — Translation foundation
 */
#[ORM\Entity]
#[ORM\Index(columns: ['archetype_id', 'locale'], name: 'archetype_translation_revision_subject_locale')]
class ArchetypeTranslationRevision implements TranslationRevisionInterface
{
    use TranslationRevisionTrait;

    #[ORM\ManyToOne(targetEntity: Archetype::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Archetype $archetype;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $sourceRevision = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ogDescription = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromTranslation(ArchetypeTranslation $translation): self
    {
        $revision = new self();
        $revision->archetype = $translation->getArchetype();
        $revision->locale = $translation->getLocale();
        $revision->name = $translation->getName();
        $revision->description = $translation->getDescription();
        $revision->metaDescription = $translation->getMetaDescription();
        $revision->ogDescription = $translation->getOgDescription();

        return $revision;
    }

    public function getArchetype(): Archetype
    {
        return $this->archetype;
    }

    public function getSubject(): Archetype
    {
        return $this->archetype;
    }

    public static function subjectFieldName(): string
    {
        return 'archetype';
    }

    public function getSourceRevision(): ?self
    {
        return $this->sourceRevision;
    }

    public function setSourceRevision(?self $sourceRevision): static
    {
        $this->sourceRevision = $sourceRevision;

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
