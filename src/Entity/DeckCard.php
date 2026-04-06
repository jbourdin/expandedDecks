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

use App\Repository\DeckCardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F2.3 — Import deck list (PTCG text format)
 * @see docs/features.md F6.1 — Parse PTCG text format
 */
#[ORM\Entity(repositoryClass: DeckCardRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_deck_card', columns: ['deck_version_id', 'set_code', 'card_number'])]
class DeckCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeckVersion::class, inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false)]
    private DeckVersion $deckVersion;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $cardName = '';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private string $setCode = '';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private string $cardNumber = '';

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private string $cardType = '';

    #[ORM\ManyToOne(targetEntity: CardPrinting::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?CardPrinting $cardPrinting = null;

    /** Locale of the original deck list input (e.g. "en", "fr"). */
    #[ORM\Column(length: 5)]
    private string $cardLocale = 'en';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeckVersion(): DeckVersion
    {
        return $this->deckVersion;
    }

    public function setDeckVersion(DeckVersion $deckVersion): static
    {
        $this->deckVersion = $deckVersion;

        return $this;
    }

    public function getCardName(): string
    {
        return $this->cardName;
    }

    public function setCardName(string $cardName): static
    {
        $this->cardName = $cardName;

        return $this;
    }

    public function getSetCode(): string
    {
        return $this->setCode;
    }

    public function setSetCode(string $setCode): static
    {
        $this->setCode = $setCode;

        return $this;
    }

    public function getCardNumber(): string
    {
        return $this->cardNumber;
    }

    public function setCardNumber(string $cardNumber): static
    {
        $this->cardNumber = $cardNumber;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getCardType(): string
    {
        return $this->cardType;
    }

    public function setCardType(string $cardType): static
    {
        $this->cardType = $cardType;

        return $this;
    }

    public function getCardPrinting(): ?CardPrinting
    {
        return $this->cardPrinting;
    }

    public function setCardPrinting(?CardPrinting $cardPrinting): static
    {
        $this->cardPrinting = $cardPrinting;

        return $this;
    }

    public function getCardLocale(): string
    {
        return $this->cardLocale;
    }

    public function setCardLocale(string $cardLocale): static
    {
        $this->cardLocale = $cardLocale;

        return $this;
    }

    /**
     * Computed accessor: image URL from the linked CardPrinting.
     */
    public function getImageUrl(): ?string
    {
        return $this->cardPrinting?->getImageUrl();
    }

    /**
     * Computed accessor: trainer subtype from the CardIdentity via CardPrinting.
     */
    public function getTrainerSubtype(): ?string
    {
        return $this->cardPrinting?->getCardIdentity()->getTrainerType();
    }

    /**
     * Computed accessor: TCGdex ID from the linked CardPrinting.
     */
    public function getTcgdexId(): ?string
    {
        return $this->cardPrinting?->getTcgdexId();
    }
}
