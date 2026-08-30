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
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F11.2 — Menu categories
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'menu_category_translation_unique', columns: ['menu_category_id', 'locale'])]
#[UniqueEntity(fields: ['menuCategory', 'locale'], message: 'app.cms.translation_locale_unique')]
class MenuCategoryTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MenuCategory::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private MenuCategory $menuCategory;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5)]
    private string $locale = 'en';

    #[Translatable]
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    private string $name = '';

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenuCategory(): MenuCategory
    {
        return $this->menuCategory;
    }

    public function setMenuCategory(MenuCategory $menuCategory): static
    {
        $this->menuCategory = $menuCategory;

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

    public function isSourceOutdated(): bool
    {
        return $this->sourceOutdated;
    }

    public function setSourceOutdated(bool $sourceOutdated): static
    {
        $this->sourceOutdated = $sourceOutdated;

        return $this;
    }
}
