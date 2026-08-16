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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Localized notes of an archetype variant, non-source locales only.
 *
 * Deliberate pattern bend versus the other `*Translation` entities: the
 * canonical source-locale notes stay on `Deck.notes` (user decks never grow
 * translation rows), so this table only ever holds non-source locales, and
 * only for archetype variants — enforced by the translation workflow, not by
 * schema.
 *
 * @see docs/features.md F9.7 — Translation foundation
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'deck_translation_unique', columns: ['deck_id', 'locale'])]
#[UniqueEntity(fields: ['deck', 'locale'], message: 'app.cms.translation_locale_unique')]
class DeckTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Deck::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Deck $deck;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5)]
    private string $locale = 'en';

    #[Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Set when a newer source-locale revision exists than the one this
     * translation was based on; cleared when an up-to-date revision is
     * approved (F9.9). Denormalized so public pages never query the
     * revision tables (US-T6).
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $sourceOutdated = false;

    /**
     * Public translator credit for this locale (F19.8 pattern).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $translator = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeck(): Deck
    {
        return $this->deck;
    }

    public function setDeck(Deck $deck): static
    {
        $this->deck = $deck;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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
