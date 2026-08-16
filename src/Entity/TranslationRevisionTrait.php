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

use App\Enum\TranslationRevisionState;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Shared workflow columns of every translation revision entity.
 *
 * The `sourceRevision` self-reference is deliberately NOT part of this trait:
 * a self-referencing association needs the concrete entity type, so each
 * revision entity declares it itself.
 *
 * @see docs/features.md F9.7 — Translation foundation
 */
trait TranslationRevisionTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5)]
    private string $locale = 'en';

    #[ORM\Column(length: 20, enumType: TranslationRevisionState::class)]
    private TranslationRevisionState $state = TranslationRevisionState::Validated;

    /**
     * Contributor who produced this revision. `SET NULL` on user deletion so
     * GDPR anonymization never orphans history rows (F19.8 pattern).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Moderator who validated or rejected this revision (F9.9). `SET NULL`
     * on user deletion, like `author`.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    /**
     * Moderator feedback carried back to the contributor on rejection (F9.9).
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reviewComment = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getState(): TranslationRevisionState
    {
        return $this->state;
    }

    public function setState(TranslationRevisionState $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getReviewComment(): ?string
    {
        return $this->reviewComment;
    }

    public function setReviewComment(?string $reviewComment): static
    {
        $this->reviewComment = $reviewComment;

        return $this;
    }

    /**
     * Used by the Symfony Workflow MethodMarkingStore (reads string places).
     */
    public function getMarking(): string
    {
        return $this->state->value;
    }

    /**
     * Used by the Symfony Workflow MethodMarkingStore (writes string places).
     */
    public function setMarking(string $marking): void
    {
        $this->state = TranslationRevisionState::from($marking);
    }
}
