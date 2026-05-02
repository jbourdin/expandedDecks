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

use App\Repository\BannedCardPrintingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One specific printing covered by a {@see BannedCard} entry. Each upstream
 * (set code, card number) pair from pokemon.com lands in this table; the
 * canonical metadata (date, source URL, explanation) lives on the parent.
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */
#[ORM\Entity(repositoryClass: BannedCardPrintingRepository::class)]
#[ORM\Table(name: 'banned_card_printing')]
#[ORM\UniqueConstraint(name: 'uniq_banned_card_printing', columns: ['set_code', 'card_number'])]
class BannedCardPrinting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BannedCard::class, inversedBy: 'printings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BannedCard $bannedCard;

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

    public function getBannedCard(): BannedCard
    {
        return $this->bannedCard;
    }

    public function setBannedCard(BannedCard $bannedCard): static
    {
        $this->bannedCard = $bannedCard;

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
