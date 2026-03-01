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
use App\Enum\TournamentStructure;
use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F3.1 — Create a new event
 * @see docs/features.md F3.5 — Assign event staff team
 * @see docs/features.md F3.10 — Cancel an event
 * @see docs/features.md F3.20 — Mark event as finished
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 150)]
    private string $name = '';

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $eventId = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private string $format = 'Expanded';

    #[ORM\Column]
    private \DateTimeImmutable $date;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Timezone]
    #[Assert\Length(max: 50)]
    private string $timezone = 'UTC';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $organizer;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 255)]
    private string $registrationLink = '';

    #[ORM\Column(length: 30, nullable: true, enumType: TournamentStructure::class)]
    private ?TournamentStructure $tournamentStructure = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $minAttendees = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $maxAttendees = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $roundDuration = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $topCutRoundDuration = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $entryFeeAmount = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Assert\Length(exactly: 3)]
    private ?string $entryFeeCurrency = null;

    #[ORM\Column]
    private bool $isDecklistMandatory = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** @var Collection<int, EventEngagement> */
    #[ORM\OneToMany(targetEntity: EventEngagement::class, mappedBy: 'event')]
    private Collection $engagements;

    /** @var Collection<int, EventStaff> */
    #[ORM\OneToMany(targetEntity: EventStaff::class, mappedBy: 'event')]
    private Collection $staff;

    /** @var Collection<int, Borrow> */
    #[ORM\OneToMany(targetEntity: Borrow::class, mappedBy: 'event')]
    private Collection $borrows;

    /** @var Collection<int, EventDeckEntry> */
    #[ORM\OneToMany(targetEntity: EventDeckEntry::class, mappedBy: 'event')]
    private Collection $deckEntries;

    public function __construct()
    {
        $this->date = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->engagements = new ArrayCollection();
        $this->staff = new ArrayCollection();
        $this->borrows = new ArrayCollection();
        $this->deckEntries = new ArrayCollection();
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

    public function getEventId(): ?string
    {
        return $this->eventId;
    }

    public function setEventId(?string $eventId): static
    {
        $this->eventId = $eventId;

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

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getOrganizer(): User
    {
        return $this->organizer;
    }

    public function setOrganizer(User $organizer): static
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getRegistrationLink(): string
    {
        return $this->registrationLink;
    }

    public function setRegistrationLink(string $registrationLink): static
    {
        $this->registrationLink = $registrationLink;

        return $this;
    }

    public function getTournamentStructure(): ?TournamentStructure
    {
        return $this->tournamentStructure;
    }

    public function setTournamentStructure(?TournamentStructure $tournamentStructure): static
    {
        $this->tournamentStructure = $tournamentStructure;

        return $this;
    }

    public function getMinAttendees(): ?int
    {
        return $this->minAttendees;
    }

    public function setMinAttendees(?int $minAttendees): static
    {
        $this->minAttendees = $minAttendees;

        return $this;
    }

    public function getMaxAttendees(): ?int
    {
        return $this->maxAttendees;
    }

    public function setMaxAttendees(?int $maxAttendees): static
    {
        $this->maxAttendees = $maxAttendees;

        return $this;
    }

    public function getRoundDuration(): ?int
    {
        return $this->roundDuration;
    }

    public function setRoundDuration(?int $roundDuration): static
    {
        $this->roundDuration = $roundDuration;

        return $this;
    }

    public function getTopCutRoundDuration(): ?int
    {
        return $this->topCutRoundDuration;
    }

    public function setTopCutRoundDuration(?int $topCutRoundDuration): static
    {
        $this->topCutRoundDuration = $topCutRoundDuration;

        return $this;
    }

    public function getEntryFeeAmount(): ?int
    {
        return $this->entryFeeAmount;
    }

    public function setEntryFeeAmount(?int $entryFeeAmount): static
    {
        $this->entryFeeAmount = $entryFeeAmount;

        return $this;
    }

    public function getEntryFeeCurrency(): ?string
    {
        return $this->entryFeeCurrency;
    }

    public function setEntryFeeCurrency(?string $entryFeeCurrency): static
    {
        $this->entryFeeCurrency = $entryFeeCurrency;

        return $this;
    }

    public function isDecklistMandatory(): bool
    {
        return $this->isDecklistMandatory;
    }

    public function setIsDecklistMandatory(bool $isDecklistMandatory): static
    {
        $this->isDecklistMandatory = $isDecklistMandatory;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    /**
     * @return Collection<int, EventEngagement>
     */
    public function getEngagements(): Collection
    {
        return $this->engagements;
    }

    public function getEngagementFor(User $user): ?EventEngagement
    {
        foreach ($this->engagements as $engagement) {
            if ($engagement->getUser()->getId() === $user->getId()) {
                return $engagement;
            }
        }

        return null;
    }

    public function countByState(EngagementState $state): int
    {
        $count = 0;
        foreach ($this->engagements as $engagement) {
            if ($engagement->getState() === $state) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return Collection<int, EventStaff>
     */
    public function getStaff(): Collection
    {
        return $this->staff;
    }

    /**
     * @see docs/features.md F3.5 — Assign event staff team
     */
    public function getStaffFor(User $user): ?EventStaff
    {
        foreach ($this->staff as $staffMember) {
            if ($staffMember->getUser()->getId() === $user->getId()) {
                return $staffMember;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Borrow>
     */
    public function getBorrows(): Collection
    {
        return $this->borrows;
    }

    /**
     * @return Collection<int, EventDeckEntry>
     */
    public function getDeckEntries(): Collection
    {
        return $this->deckEntries;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
