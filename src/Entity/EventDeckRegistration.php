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

/**
 * Records per-deck-per-event delegation preference. A deck owner registers
 * their deck at an event and optionally delegates handling to event staff.
 *
 * @see docs/features.md F4.8 — Staff-delegated lending
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

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'deckRegistrations')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: Deck::class, inversedBy: 'eventRegistrations')]
    #[ORM\JoinColumn(nullable: false)]
    private Deck $deck;

    #[ORM\Column]
    private bool $delegateToStaff = false;

    #[ORM\Column]
    private \DateTimeImmutable $registeredAt;

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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->registeredAt = new \DateTimeImmutable();
    }
}
