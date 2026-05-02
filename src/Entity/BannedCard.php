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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * One ban announcement, keyed by the functional card identity.
 *
 * All printings of a banned functional card share metadata (effective date,
 * announcement URL, explanation) and are stored as child {@see BannedCardPrinting}
 * rows under a single BannedCard entry. Admins manage one row per identity.
 *
 * `cardIdentity` is nullable so a printing whose CardIdentity hasn't been
 * resolved yet (e.g. before TCGdex enrichment) still has a placeholder parent.
 *
 * @see docs/features.md F6.5 — Banned card list management
 * @see docs/features.md F6.14 — Banned cards public page
 */
#[ORM\Entity(repositoryClass: BannedCardRepository::class)]
#[ORM\Table(name: 'banned_card')]
#[ORM\UniqueConstraint(name: 'uniq_banned_card_identity', columns: ['card_identity_id'])]
class BannedCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Functional card identity. Null for placeholder rows whose printing has not been enriched yet. */
    #[ORM\ManyToOne(targetEntity: CardIdentity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CardIdentity $cardIdentity = null;

    /** Denormalised display name. Mirrors CardIdentity::name when linked. */
    #[ORM\Column(length: 100)]
    private string $cardName = '';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $effectiveDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $explanation = null;

    /** Optional explicit override for the public-page image. Falls back to lowest-rarity printing among children. */
    #[ORM\ManyToOne(targetEntity: CardPrinting::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CardPrinting $representativePrinting = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, BannedCardPrinting> */
    #[ORM\OneToMany(targetEntity: BannedCardPrinting::class, mappedBy: 'bannedCard', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $printings;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->printings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCardIdentity(): ?CardIdentity
    {
        return $this->cardIdentity;
    }

    public function setCardIdentity(?CardIdentity $cardIdentity): static
    {
        $this->cardIdentity = $cardIdentity;

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

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): static
    {
        $this->explanation = $explanation;

        return $this;
    }

    public function getRepresentativePrinting(): ?CardPrinting
    {
        return $this->representativePrinting;
    }

    public function setRepresentativePrinting(?CardPrinting $representativePrinting): static
    {
        $this->representativePrinting = $representativePrinting;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt instanceof \DateTimeImmutable;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, BannedCardPrinting>
     */
    public function getPrintings(): Collection
    {
        return $this->printings;
    }

    public function addPrinting(BannedCardPrinting $printing): static
    {
        if (!$this->printings->contains($printing)) {
            $this->printings->add($printing);
            $printing->setBannedCard($this);
        }

        return $this;
    }

    public function removePrinting(BannedCardPrinting $printing): static
    {
        if ($this->printings->removeElement($printing)) {
            // bannedCard is required, orphanRemoval handles physical deletion.
        }

        return $this;
    }
}
