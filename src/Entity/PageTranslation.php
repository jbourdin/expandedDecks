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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F11.1 — Content pages
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'page_translation_unique', columns: ['page_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'page_translation_slug_unique', columns: ['slug'])]
#[UniqueEntity(fields: ['page', 'locale'], message: 'app.cms.translation_locale_unique')]
class PageTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Page::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private Page $page;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5)]
    private string $locale = 'en';

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 200)]
    private string $title = '';

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'app.cms.slug_format')]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $content = '';

    #[ORM\Column(length: 70, nullable: true)]
    #[Assert\Length(max: 70)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Url(requireTld: true)]
    private ?string $ogImage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function setPage(Page $page): static
    {
        $this->page = $page;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $this->metaTitle = $metaTitle;

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

    public function getOgImage(): ?string
    {
        return $this->ogImage;
    }

    public function setOgImage(?string $ogImage): static
    {
        $this->ogImage = $ogImage;

        return $this;
    }
}
