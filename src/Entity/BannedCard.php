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

use App\Repository\BannedCardRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A specific card printing banned from the Expanded format.
 *
 * Each row represents one printing (set code + card number) of a banned card.
 * A single ban announcement may produce multiple rows when the same card
 * has alternate-art or promo reprints.
 *
 * @see docs/features.md F6.5 — Banned card list management
 */
#[ORM\Entity(repositoryClass: BannedCardRepository::class)]
#[ORM\Table(name: 'banned_card')]
#[ORM\UniqueConstraint(name: 'uniq_banned_card', columns: ['set_code', 'card_number'])]
class BannedCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $cardName = '';

    #[ORM\Column(length: 20)]
    private string $setCode = '';

    #[ORM\Column(length: 20)]
    private string $cardNumber = '';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $effectiveDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceUrl = null;

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

    public function getEffectiveDate(): ?\DateTimeImmutable
    {
        return $this->effectiveDate;
    }

    public function setEffectiveDate(?\DateTimeImmutable $effectiveDate): static
    {
        $this->effectiveDate = $effectiveDate;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
