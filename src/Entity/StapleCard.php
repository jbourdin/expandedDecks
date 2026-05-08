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

use App\Constants\CardHotness;
use App\Repository\StapleCardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * One curated "staple" — a card frequently played across decks and archetypes,
 * keyed by functional card identity. All printings of the same staple share
 * metadata (note, hotness, bucket) and live as child {@see StapleCardPrinting}
 * rows under a single StapleCard parent.
 *
 * `cardIdentity` is nullable so a printing whose CardIdentity hasn't been
 * resolved yet (e.g. before TCGdex enrichment) still has a placeholder parent;
 * {@see \App\Service\StapleCardEnricher::reparentByIdentity()} consolidates onto
 * the canonical parent once enrichment runs.
 *
 * @see docs/features.md F6.15 — Staple cards
 * @see https://github.com/jbourdin/expandedDecks/issues/532
 */
#[ORM\Entity(repositoryClass: StapleCardRepository::class)]
#[ORM\Table(name: 'staple_card')]
#[ORM\UniqueConstraint(name: 'uniq_staple_card_identity', columns: ['card_identity_id'])]
#[ORM\Index(columns: ['bucket', 'position'], name: 'idx_staple_card_bucket_position')]
#[ORM\Index(columns: ['bucket', 'hotness'], name: 'idx_staple_card_bucket_hotness')]
class StapleCard
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

    /**
     * Bucket value from {@see \App\Constants\StapleCardBucket} — denormalised at enrichment time
     * via the priority rule (Ace Spec wins over type-based buckets). Stored as a column rather than
     * computed at query time so public-list / reorder / hotness-filter queries stay simple.
     */
    #[ORM\Column(length: 20)]
    private string $bucket = '';

    /** 0-based ordering scoped to {@see self::$bucket}. Editor-controlled via SortableJS reorder. */
    #[ORM\Column]
    private int $position = 0;

    /**
     * Editor-curated relevance score. {@see CardHotness::STAPLE_THRESHOLD} is the
     * default and the public-list default minimum filter. Bridge to issue #437.
     */
    #[ORM\Column]
    private int $hotness = CardHotness::STAPLE_THRESHOLD;

    /** Optional explicit override for the public-page image. Falls back to lowest-rarity printing among children. */
    #[ORM\ManyToOne(targetEntity: CardPrinting::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CardPrinting $representativePrinting = null;

    /** Markdown explanation, edited via the rich-text editor (rich_text_editor macro). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, StapleCardPrinting> */
    #[ORM\OneToMany(targetEntity: StapleCardPrinting::class, mappedBy: 'stapleCard', cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function setBucket(string $bucket): static
    {
        $this->bucket = $bucket;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getHotness(): int
    {
        return $this->hotness;
    }

    public function setHotness(int $hotness): static
    {
        $this->hotness = $hotness;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

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
     * @return Collection<int, StapleCardPrinting>
     */
    public function getPrintings(): Collection
    {
        return $this->printings;
    }

    public function addPrinting(StapleCardPrinting $printing): static
    {
        if (!$this->printings->contains($printing)) {
            $this->printings->add($printing);
            $printing->setStapleCard($this);
        }

        return $this;
    }

    public function removePrinting(StapleCardPrinting $printing): static
    {
        $this->printings->removeElement($printing);

        return $this;
    }
}
