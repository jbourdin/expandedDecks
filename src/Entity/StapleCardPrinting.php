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

use App\Repository\StapleCardPrintingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One specific printing covered by a {@see StapleCard} entry. Each editor-submitted
 * (set code, card number) pair lands in this table. Sibling printings of the same
 * functional card share a {@see StapleCard} parent, populated by
 * {@see \App\Service\CardIdentity\CardIdentityResolver::expandPrintings()}.
 *
 * @see docs/features.md F6.15 — Staple cards
 */
#[ORM\Entity(repositoryClass: StapleCardPrintingRepository::class)]
#[ORM\Table(name: 'staple_card_printing')]
#[ORM\UniqueConstraint(name: 'uniq_staple_card_printing', columns: ['set_code', 'card_number'])]
class StapleCardPrinting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StapleCard::class, inversedBy: 'printings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private StapleCard $stapleCard;

    #[ORM\Column(length: 20)]
    private string $setCode = '';

    #[ORM\Column(length: 20)]
    private string $cardNumber = '';

    #[ORM\ManyToOne(targetEntity: CardPrinting::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CardPrinting $cardPrinting = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStapleCard(): StapleCard
    {
        return $this->stapleCard;
    }

    public function setStapleCard(StapleCard $stapleCard): static
    {
        $this->stapleCard = $stapleCard;

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

    public function getCardPrinting(): ?CardPrinting
    {
        return $this->cardPrinting;
    }

    public function setCardPrinting(?CardPrinting $cardPrinting): static
    {
        $this->cardPrinting = $cardPrinting;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
