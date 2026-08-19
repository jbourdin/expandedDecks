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
 * Localized ban explanation, non-source locales only (F9.17): the canonical
 * source-locale explanation stays on `BannedCard.explanation` — same pattern
 * bend as variant notes. Card names are never translated.
 *
 * @see docs/features.md F9.17 — Translation workflow for banned & staple cards
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'banned_card_translation_unique', columns: ['banned_card_id', 'locale'])]
#[UniqueEntity(fields: ['bannedCard', 'locale'], message: 'app.cms.translation_locale_unique')]
class BannedCardTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BannedCard::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BannedCard $bannedCard;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5)]
    private string $locale = 'en';

    #[Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $explanation = null;

    /**
     * Set when a newer source-locale revision exists than the one this
     * translation was based on; cleared when an up-to-date revision is
     * approved (F9.9).
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

    public function getBannedCard(): BannedCard
    {
        return $this->bannedCard;
    }

    public function setBannedCard(BannedCard $bannedCard): static
    {
        $this->bannedCard = $bannedCard;

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

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): static
    {
        $this->explanation = $explanation;

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
