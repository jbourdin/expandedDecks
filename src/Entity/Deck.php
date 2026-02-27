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

use App\Enum\DeckStatus;
use App\Repository\DeckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F2.1 — Register a new deck (owner)
 */
#[ORM\Entity(repositoryClass: DeckRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Deck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownedDecks')]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private string $format = 'Expanded';

    #[ORM\Column(length: 20, enumType: DeckStatus::class)]
    private DeckStatus $status = DeckStatus::Available;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: DeckVersion::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?DeckVersion $currentVersion = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, DeckVersion> */
    #[ORM\OneToMany(targetEntity: DeckVersion::class, mappedBy: 'deck')]
    private Collection $versions;

    /** @var Collection<int, Borrow> */
    #[ORM\OneToMany(targetEntity: Borrow::class, mappedBy: 'deck')]
    private Collection $borrows;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->versions = new ArrayCollection();
        $this->borrows = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getStatus(): DeckStatus
    {
        return $this->status;
    }

    public function setStatus(DeckStatus $status): static
    {
        $this->status = $status;

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

    public function getCurrentVersion(): ?DeckVersion
    {
        return $this->currentVersion;
    }

    public function setCurrentVersion(?DeckVersion $currentVersion): static
    {
        $this->currentVersion = $currentVersion;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, DeckVersion>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    /**
     * @return Collection<int, Borrow>
     */
    public function getBorrows(): Collection
    {
        return $this->borrows;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
