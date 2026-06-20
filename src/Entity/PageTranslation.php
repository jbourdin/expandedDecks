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

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    /**
     * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '#^(/|https?://)#', message: 'app.cms.og_image_url_format')]
    private ?string $ogImage = null;

    /**
     * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ogDescription = null;

    /**
     * Public translator credit for this locale (F19.8). Forward-compatible
     * with the #612 translation-role epic, which will add workflow state and
     * source-version tracking on the same row.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $translator = null;

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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
     */
    public function getOgImage(): ?string
    {
        return $this->ogImage;
    }

    /**
     * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
     */
    public function setOgImage(?string $ogImage): static
    {
        $this->ogImage = $ogImage;

        return $this;
    }

    /**
     * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
     */
    public function getOgDescription(): ?string
    {
        return $this->ogDescription;
    }

    /**
     * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
     */
    public function setOgDescription(?string $ogDescription): static
    {
        $this->ogDescription = $ogDescription;

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
