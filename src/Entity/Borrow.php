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

use App\Enum\BorrowStatus;
use App\Repository\BorrowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @see docs/features.md F4.1 — Request to borrow a deck
 * @see docs/features.md F4.11 — Borrow conflict detection
 */
#[ORM\Entity(repositoryClass: BorrowRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Borrow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Deck::class, inversedBy: 'borrows')]
    #[ORM\JoinColumn(nullable: false)]
    private Deck $deck;

    #[ORM\ManyToOne(targetEntity: DeckVersion::class)]
    #[ORM\JoinColumn(nullable: false)]
    private DeckVersion $deckVersion;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'borrowRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private User $borrower;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'borrows')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\Column(length: 30, enumType: BorrowStatus::class)]
    private BorrowStatus $status = BorrowStatus::Pending;

    #[ORM\Column]
    private bool $isDelegatedToStaff = false;

    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $approvedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $handedOffAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $handedOffBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $returnedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $returnedTo = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $returnedToOwnerAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $cancelledBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
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

    public function getDeckVersion(): DeckVersion
    {
        return $this->deckVersion;
    }

    public function setDeckVersion(DeckVersion $deckVersion): static
    {
        $this->deckVersion = $deckVersion;

        return $this;
    }

    public function getBorrower(): User
    {
        return $this->borrower;
    }

    public function setBorrower(User $borrower): static
    {
        $this->borrower = $borrower;

        return $this;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getStatus(): BorrowStatus
    {
        return $this->status;
    }

    public function setStatus(BorrowStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isDelegatedToStaff(): bool
    {
        return $this->isDelegatedToStaff;
    }

    public function setIsDelegatedToStaff(bool $isDelegatedToStaff): static
    {
        $this->isDelegatedToStaff = $isDelegatedToStaff;

        return $this;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getHandedOffAt(): ?\DateTimeImmutable
    {
        return $this->handedOffAt;
    }

    public function setHandedOffAt(?\DateTimeImmutable $handedOffAt): static
    {
        $this->handedOffAt = $handedOffAt;

        return $this;
    }

    public function getHandedOffBy(): ?User
    {
        return $this->handedOffBy;
    }

    public function setHandedOffBy(?User $handedOffBy): static
    {
        $this->handedOffBy = $handedOffBy;

        return $this;
    }

    public function getReturnedAt(): ?\DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function setReturnedAt(?\DateTimeImmutable $returnedAt): static
    {
        $this->returnedAt = $returnedAt;

        return $this;
    }

    public function getReturnedTo(): ?User
    {
        return $this->returnedTo;
    }

    public function setReturnedTo(?User $returnedTo): static
    {
        $this->returnedTo = $returnedTo;

        return $this;
    }

    public function getReturnedToOwnerAt(): ?\DateTimeImmutable
    {
        return $this->returnedToOwnerAt;
    }

    public function setReturnedToOwnerAt(?\DateTimeImmutable $returnedToOwnerAt): static
    {
        $this->returnedToOwnerAt = $returnedToOwnerAt;

        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?User $cancelledBy): static
    {
        $this->cancelledBy = $cancelledBy;

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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->requestedAt = new \DateTimeImmutable();
    }
}
