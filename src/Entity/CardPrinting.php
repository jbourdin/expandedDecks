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

use App\Repository\CardPrintingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A specific physical printing of a card in a particular set.
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 */
#[ORM\Entity(repositoryClass: CardPrintingRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_tcgdex_id', columns: ['tcgdex_id'])]
class CardPrinting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CardIdentity::class, inversedBy: 'printings')]
    #[ORM\JoinColumn(nullable: false)]
    private CardIdentity $cardIdentity;

    #[ORM\Column(length: 30)]
    private string $tcgdexId;

    #[ORM\Column(length: 20)]
    private string $setCode = '';

    #[ORM\Column(length: 20)]
    private string $cardNumber = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $rarity = null;

    /** 1 = cheapest (Common), 6 = most expensive (Special). */
    #[ORM\Column]
    private int $rarityTier = 6;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $setReleaseDate = null;

    /** Average price in euro cents from Cardmarket (nullable if unknown). */
    #[ORM\Column(nullable: true)]
    private ?int $priceInCents = null;

    #[ORM\Column]
    private bool $isExpandedLegal = false;

    /** Cardmarket product ID for direct linking (e.g. cardmarket.com/en/Pokemon/Products/Singles/{id}). */
    #[ORM\Column(nullable: true)]
    private ?int $cardmarketProductId = null;

    /** TCGPlayer product ID for direct linking (e.g. tcgplayer.com/product/{id}). */
    #[ORM\Column(nullable: true)]
    private ?int $tcgplayerProductId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCardIdentity(): CardIdentity
    {
        return $this->cardIdentity;
    }

    public function setCardIdentity(CardIdentity $cardIdentity): static
    {
        $this->cardIdentity = $cardIdentity;

        return $this;
    }

    public function getTcgdexId(): string
    {
        return $this->tcgdexId;
    }

    public function setTcgdexId(string $tcgdexId): static
    {
        $this->tcgdexId = $tcgdexId;

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

    public function getRarity(): ?string
    {
        return $this->rarity;
    }

    public function setRarity(?string $rarity): static
    {
        $this->rarity = $rarity;

        return $this;
    }

    public function getRarityTier(): int
    {
        return $this->rarityTier;
    }

    public function setRarityTier(int $rarityTier): static
    {
        $this->rarityTier = $rarityTier;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getSetReleaseDate(): ?\DateTimeImmutable
    {
        return $this->setReleaseDate;
    }

    public function setSetReleaseDate(?\DateTimeImmutable $setReleaseDate): static
    {
        $this->setReleaseDate = $setReleaseDate;

        return $this;
    }

    public function getPriceInCents(): ?int
    {
        return $this->priceInCents;
    }

    public function setPriceInCents(?int $priceInCents): static
    {
        $this->priceInCents = $priceInCents;

        return $this;
    }

    public function isExpandedLegal(): bool
    {
        return $this->isExpandedLegal;
    }

    public function setIsExpandedLegal(bool $isExpandedLegal): static
    {
        $this->isExpandedLegal = $isExpandedLegal;

        return $this;
    }

    public function getCardmarketProductId(): ?int
    {
        return $this->cardmarketProductId;
    }

    public function setCardmarketProductId(?int $cardmarketProductId): static
    {
        $this->cardmarketProductId = $cardmarketProductId;

        return $this;
    }

    public function getTcgplayerProductId(): ?int
    {
        return $this->tcgplayerProductId;
    }

    public function setTcgplayerProductId(?int $tcgplayerProductId): static
    {
        $this->tcgplayerProductId = $tcgplayerProductId;

        return $this;
    }
}
