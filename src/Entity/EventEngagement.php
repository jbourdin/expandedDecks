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

use App\Enum\EngagementState;
use App\Enum\ParticipationMode;
use App\Repository\EventEngagementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @see docs/features.md F3.4 — Register participation to an event
 */
#[ORM\Entity(repositoryClass: EventEngagementRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_event_engagement', columns: ['event_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class EventEngagement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'engagements')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'eventEngagements')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 30, enumType: EngagementState::class)]
    private EngagementState $state;

    #[ORM\Column(length: 20, nullable: true, enumType: ParticipationMode::class)]
    private ?ParticipationMode $participationMode = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $invitedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getState(): EngagementState
    {
        return $this->state;
    }

    public function setState(EngagementState $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getParticipationMode(): ?ParticipationMode
    {
        return $this->participationMode;
    }

    public function setParticipationMode(?ParticipationMode $participationMode): static
    {
        $this->participationMode = $participationMode;

        return $this;
    }

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function setInvitedBy(?User $invitedBy): static
    {
        $this->invitedBy = $invitedBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
