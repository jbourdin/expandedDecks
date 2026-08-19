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
 * Revision history of ban explanations, all locales included: the source
 * locale snapshots from `BannedCard.explanation`, non-source locales from
 * `BannedCardTranslation` rows.
 *
 * @see docs/features.md F9.17 — Translation workflow for banned & staple cards
 */
#[ORM\Entity]
#[ORM\Index(columns: ['banned_card_id', 'locale'], name: 'banned_card_translation_revision_subject_locale')]
class BannedCardTranslationRevision implements TranslationRevisionInterface
{
    use TranslationRevisionTrait;

    #[ORM\ManyToOne(targetEntity: BannedCard::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BannedCard $bannedCard;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $sourceRevision = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $explanation = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromTranslation(BannedCardTranslation $translation): self
    {
        $revision = new self();
        $revision->bannedCard = $translation->getBannedCard();
        $revision->locale = $translation->getLocale();
        $revision->explanation = $translation->getExplanation();

        return $revision;
    }

    /**
     * Source-locale snapshot: the canonical explanation lives on the card.
     */
    public static function fromBannedCardExplanation(BannedCard $bannedCard, string $sourceLocale): self
    {
        $revision = new self();
        $revision->bannedCard = $bannedCard;
        $revision->locale = $sourceLocale;
        $revision->explanation = $bannedCard->getExplanation();

        return $revision;
    }

    /**
     * Approval write path (F9.9): copies the `#[Translatable]` fields into
     * the live row. Mirror of {@see fromTranslation()}, guarded by the
     * revision consistency test.
     */
    public function applyTo(BannedCardTranslation $translation): void
    {
        $translation->setExplanation($this->explanation);
    }

    public function getBannedCard(): BannedCard
    {
        return $this->bannedCard;
    }

    public function getSubject(): BannedCard
    {
        return $this->bannedCard;
    }

    public static function subjectFieldName(): string
    {
        return 'bannedCard';
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

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): static
    {
        $this->explanation = $explanation;

        return $this;
    }
}
