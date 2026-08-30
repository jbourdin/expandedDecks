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
 * Revision history of archetype-variant notes, all locales included.
 *
 * The source locale is snapshotted from `Deck.notes` directly (the canonical
 * English content stays on the deck, see the epic's pattern bend); non-source
 * locales are snapshotted from `DeckTranslation` rows.
 *
 * @see docs/features.md F9.7 — Translation foundation
 */
#[ORM\Entity]
#[ORM\Index(columns: ['deck_id', 'locale'], name: 'deck_translation_revision_subject_locale')]
class DeckTranslationRevision implements TranslationRevisionInterface
{
    use TranslationRevisionTrait;

    #[ORM\ManyToOne(targetEntity: Deck::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Deck $deck;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $sourceRevision = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromTranslation(DeckTranslation $translation): self
    {
        $revision = new self();
        $revision->deck = $translation->getDeck();
        $revision->locale = $translation->getLocale();
        $revision->notes = $translation->getNotes();

        return $revision;
    }

    /**
     * Source-locale snapshot: the canonical notes live on the deck itself,
     * not in a `DeckTranslation` row.
     */
    public static function fromDeckNotes(Deck $deck, string $sourceLocale): self
    {
        $revision = new self();
        $revision->deck = $deck;
        $revision->locale = $sourceLocale;
        $revision->notes = $deck->getNotes();

        return $revision;
    }

    /**
     * Approval write path (F9.9): copies the `#[Translatable]` fields into
     * the live row. Mirror of {@see fromTranslation()}, guarded by the
     * revision consistency test.
     */
    public function applyTo(DeckTranslation $translation): void
    {
        $translation->setNotes($this->notes);
    }

    public function getDeck(): Deck
    {
        return $this->deck;
    }

    public function getSubject(): Deck
    {
        return $this->deck;
    }

    public static function subjectFieldName(): string
    {
        return 'deck';
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
}
