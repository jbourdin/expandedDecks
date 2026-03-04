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

use App\Repository\EventDeckRegistrationRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ManyToOne;

/**
 * Records per-deck-per-event delegation preference. A deck owner registers
 * their deck at an event and optionally delegates handling to event staff.
 *
 * Also tracks the physical custody handover between owner and staff (F4.14).
 *
 * @see docs/features.md F4.8 — Staff-delegated lending
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
#[ORM\Entity(repositoryClass: EventDeckRegistrationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_event_deck_registration', columns: ['event_id', 'deck_id'])]
#[ORM\HasLifecycleCallbacks]
class EventDeckRegistration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ManyToOne(targetEntity: Event::class, inversedBy: 'deckRegistrations')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ManyToOne(targetEntity: Deck::class, inversedBy: 'eventRegistrations')]
    #[ORM\JoinColumn(nullable: false)]
    private Deck $deck;

    #[ORM\Column]
    private bool $delegateToStaff = false;

    #[ORM\Column]
    private \DateTimeImmutable $registeredAt;

    /**
     * When the owner confirmed handing the physical deck to staff.
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $staffReceivedAt = null;

    /**
     * The owner who confirmed handing the deck to staff.
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    #[ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $staffReceivedBy = null;

    /**
     * When staff confirmed returning the physical deck to the owner.
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $staffReturnedAt = null;

    /**
     * The staff member who confirmed returning the deck to the owner.
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    #[ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $staffReturnedBy = null;

    public function __construct()
    {
        $this->registeredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDeck(): Deck
    {
        return $this->deck;
    }

    public function setDeck(Deck $deck): static
    {
        $this->deck = $deck;

        return $this;
    }

    public function isDelegateToStaff(): bool
    {
        return $this->delegateToStaff;
    }

    public function setDelegateToStaff(bool $delegateToStaff): static
    {
        $this->delegateToStaff = $delegateToStaff;

        return $this;
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function getStaffReceivedAt(): ?\DateTimeImmutable
    {
        return $this->staffReceivedAt;
    }

    public function setStaffReceivedAt(?\DateTimeImmutable $staffReceivedAt): static
    {
        $this->staffReceivedAt = $staffReceivedAt;

        return $this;
    }

    public function getStaffReceivedBy(): ?User
    {
        return $this->staffReceivedBy;
    }

    public function setStaffReceivedBy(?User $staffReceivedBy): static
    {
        $this->staffReceivedBy = $staffReceivedBy;

        return $this;
    }

    public function getStaffReturnedAt(): ?\DateTimeImmutable
    {
        return $this->staffReturnedAt;
    }

    public function setStaffReturnedAt(?\DateTimeImmutable $staffReturnedAt): static
    {
        $this->staffReturnedAt = $staffReturnedAt;

        return $this;
    }

    public function getStaffReturnedBy(): ?User
    {
        return $this->staffReturnedBy;
    }

    public function setStaffReturnedBy(?User $staffReturnedBy): static
    {
        $this->staffReturnedBy = $staffReturnedBy;

        return $this;
    }

    public function hasStaffReceived(): bool
    {
        return null !== $this->staffReceivedAt;
    }

    public function hasStaffReturned(): bool
    {
        return null !== $this->staffReturnedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->registeredAt = new \DateTimeImmutable();
    }
}
