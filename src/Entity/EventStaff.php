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

use App\Repository\EventStaffRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @see docs/features.md F3.5 — Assign event staff
 */
#[ORM\Entity(repositoryClass: EventStaffRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_event_staff', columns: ['event_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class EventStaff
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'staff')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'staffAssignments')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $assignedBy;

    #[ORM\Column]
    private \DateTimeImmutable $assignedAt;

    public function __construct()
    {
        $this->assignedAt = new \DateTimeImmutable();
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

    public function getAssignedBy(): User
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(User $assignedBy): static
    {
        $this->assignedBy = $assignedBy;

        return $this;
    }

    public function getAssignedAt(): \DateTimeImmutable
    {
        return $this->assignedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->assignedAt = new \DateTimeImmutable();
    }
}
