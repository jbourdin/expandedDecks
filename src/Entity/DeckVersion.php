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

use App\Repository\DeckVersionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F2.3 — Import deck list (PTCG text format)
 */
#[ORM\Entity(repositoryClass: DeckVersionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_deck_version', columns: ['deck_id', 'version_number'])]
#[ORM\HasLifecycleCallbacks]
class DeckVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Deck::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false)]
    private Deck $deck;

    #[ORM\Column]
    #[Assert\Positive]
    private int $versionNumber = 1;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $archetype = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $archetypeName = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $languages = [];

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $estimatedValueAmount = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Assert\Length(exactly: 3)]
    private ?string $estimatedValueCurrency = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawList = null;

    #[ORM\Column(length: 20)]
    private string $enrichmentStatus = 'pending';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, DeckCard> */
    #[ORM\OneToMany(targetEntity: DeckCard::class, mappedBy: 'deckVersion', cascade: ['persist', 'remove'])]
    private Collection $cards;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->cards = new ArrayCollection();
    }

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

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): static
    {
        $this->versionNumber = $versionNumber;

        return $this;
    }

    public function getArchetype(): ?string
    {
        return $this->archetype;
    }

    public function setArchetype(?string $archetype): static
    {
        $this->archetype = $archetype;

        return $this;
    }

    public function getArchetypeName(): ?string
    {
        return $this->archetypeName;
    }

    public function setArchetypeName(?string $archetypeName): static
    {
        $this->archetypeName = $archetypeName;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * @param list<string> $languages
     */
    public function setLanguages(array $languages): static
    {
        $this->languages = $languages;

        return $this;
    }

    public function getEstimatedValueAmount(): ?int
    {
        return $this->estimatedValueAmount;
    }

    public function setEstimatedValueAmount(?int $estimatedValueAmount): static
    {
        $this->estimatedValueAmount = $estimatedValueAmount;

        return $this;
    }

    public function getEstimatedValueCurrency(): ?string
    {
        return $this->estimatedValueCurrency;
    }

    public function setEstimatedValueCurrency(?string $estimatedValueCurrency): static
    {
        $this->estimatedValueCurrency = $estimatedValueCurrency;

        return $this;
    }

    public function getRawList(): ?string
    {
        return $this->rawList;
    }

    public function setRawList(?string $rawList): static
    {
        $this->rawList = $rawList;

        return $this;
    }

    public function getEnrichmentStatus(): string
    {
        return $this->enrichmentStatus;
    }

    public function setEnrichmentStatus(string $enrichmentStatus): static
    {
        $this->enrichmentStatus = $enrichmentStatus;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, DeckCard>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function addCard(DeckCard $card): static
    {
        if (!$this->cards->contains($card)) {
            $this->cards->add($card);
            $card->setDeckVersion($this);
        }

        return $this;
    }

    public function removeCard(DeckCard $card): static
    {
        $this->cards->removeElement($card);

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
