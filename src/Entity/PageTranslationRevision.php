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
#[ORM\Index(columns: ['page_id', 'locale'], name: 'page_translation_revision_subject_locale')]
class PageTranslationRevision implements TranslationRevisionInterface
{
    use TranslationRevisionTrait;

    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Page $page;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $sourceRevision = null;

    #[ORM\Column(length: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ogDescription = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromTranslation(PageTranslation $translation): self
    {
        $revision = new self();
        $revision->page = $translation->getPage();
        $revision->locale = $translation->getLocale();
        $revision->title = $translation->getTitle();
        $revision->content = $translation->getContent();
        $revision->ogDescription = $translation->getOgDescription();

        return $revision;
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getSubject(): object
    {
        return $this->page;
    }

    public static function subjectFieldName(): string
    {
        return 'page';
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
