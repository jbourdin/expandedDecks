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

/**
 * @see docs/features.md F9.7 — Translation foundation
 */
#[ORM\Entity]
#[ORM\Index(columns: ['menu_category_id', 'locale'], name: 'menu_category_translation_revision_subject_locale')]
class MenuCategoryTranslationRevision implements TranslationRevisionInterface
{
    use TranslationRevisionTrait;

    #[ORM\ManyToOne(targetEntity: MenuCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MenuCategory $menuCategory;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $sourceRevision = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromTranslation(MenuCategoryTranslation $translation): self
    {
        $revision = new self();
        $revision->menuCategory = $translation->getMenuCategory();
        $revision->locale = $translation->getLocale();
        $revision->name = $translation->getName();

        return $revision;
    }

    /**
     * Approval write path (F9.9): copies the `#[Translatable]` fields into
     * the live row. Mirror of {@see fromTranslation()}, guarded by the
     * revision consistency test.
     */
    public function applyTo(MenuCategoryTranslation $translation): void
    {
        $translation->setName($this->name);
    }

    public function getMenuCategory(): MenuCategory
    {
        return $this->menuCategory;
    }

    public function getSubject(): MenuCategory
    {
        return $this->menuCategory;
    }

    public static function subjectFieldName(): string
    {
        return 'menuCategory';
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
}
