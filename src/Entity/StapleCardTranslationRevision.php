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
 * Revision history of staple relevance notes, all locales included: the
 * source locale snapshots from `StapleCard.note`, non-source locales from
 * `StapleCardTranslation` rows.
 *
 * @see docs/features.md F9.17 — Translation workflow for banned & staple cards
 */
#[ORM\Entity]
#[ORM\Index(columns: ['staple_card_id', 'locale'], name: 'staple_card_translation_revision_subject_locale')]
class StapleCardTranslationRevision implements TranslationRevisionInterface
{
    use TranslationRevisionTrait;

    #[ORM\ManyToOne(targetEntity: StapleCard::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private StapleCard $stapleCard;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $sourceRevision = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromTranslation(StapleCardTranslation $translation): self
    {
        $revision = new self();
        $revision->stapleCard = $translation->getStapleCard();
        $revision->locale = $translation->getLocale();
        $revision->note = $translation->getNote();

        return $revision;
    }

    /**
     * Source-locale snapshot: the canonical note lives on the card.
     */
    public static function fromStapleCardNote(StapleCard $stapleCard, string $sourceLocale): self
    {
        $revision = new self();
        $revision->stapleCard = $stapleCard;
        $revision->locale = $sourceLocale;
        $revision->note = $stapleCard->getNote();

        return $revision;
    }

    /**
     * Approval write path (F9.9): copies the `#[Translatable]` fields into
     * the live row. Mirror of {@see fromTranslation()}, guarded by the
     * revision consistency test.
     */
    public function applyTo(StapleCardTranslation $translation): void
    {
        $translation->setNote($this->note);
    }

    public function getStapleCard(): StapleCard
    {
        return $this->stapleCard;
    }

    public function getSubject(): StapleCard
    {
        return $this->stapleCard;
    }

    public static function subjectFieldName(): string
    {
        return 'stapleCard';
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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
