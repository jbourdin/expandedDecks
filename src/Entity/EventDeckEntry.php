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

use App\Repository\EventDeckEntryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @see docs/features.md F3.7 — Register deck for tournament
 */
#[ORM\Entity(repositoryClass: EventDeckEntryRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_event_deck_entry', columns: ['event_id', 'player_id', 'deck_version_id'])]
#[ORM\HasLifecycleCallbacks]
class EventDeckEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'deckEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $player;

    #[ORM\ManyToOne(targetEntity: DeckVersion::class)]
    #[ORM\JoinColumn(nullable: false)]
    private DeckVersion $deckVersion;

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

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getPlayer(): User
    {
        return $this->player;
    }

    public function setPlayer(User $player): static
    {
        $this->player = $player;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
