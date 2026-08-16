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
}
