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

use App\Enum\NotificationType;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F1.1 — Register a new account
 * @see docs/features.md F1.8 — GDPR data anonymization
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'This email is already in use.')]
#[UniqueEntity(fields: ['screenName'], message: 'This screen name is already taken.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    private string $screenName = '';

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $firstName = '';

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $lastName = '';

    #[ORM\Column(length: 30, unique: true, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $playerId = null;

    #[ORM\Column]
    private string $password = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $verificationToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5)]
    private string $preferredLocale = 'en';

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Timezone]
    #[Assert\Length(max: 50)]
    private string $timezone = 'UTC';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $deletionToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletionTokenExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column]
    private bool $isAnonymized = false;

    /** @var array<string, array<string, bool>>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $notificationPreferences = null;

    /** @var Collection<int, Deck> */
    #[ORM\OneToMany(targetEntity: Deck::class, mappedBy: 'owner')]
    private Collection $ownedDecks;

    /** @var Collection<int, Borrow> */
    #[ORM\OneToMany(targetEntity: Borrow::class, mappedBy: 'borrower')]
    private Collection $borrowRequests;

    /** @var Collection<int, EventEngagement> */
    #[ORM\OneToMany(targetEntity: EventEngagement::class, mappedBy: 'user')]
    private Collection $eventEngagements;

    /** @var Collection<int, EventStaff> */
    #[ORM\OneToMany(targetEntity: EventStaff::class, mappedBy: 'user')]
    private Collection $staffAssignments;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'recipient')]
    private Collection $notifications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->ownedDecks = new ArrayCollection();
        $this->borrowRequests = new ArrayCollection();
        $this->eventEngagements = new ArrayCollection();
        $this->staffAssignments = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getScreenName(): string
    {
        return $this->screenName;
    }

    public function setScreenName(string $screenName): static
    {
        $this->screenName = $screenName;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * Returns a privacy-friendly display name: "FirstName L.".
     */
    public function getPublicDisplayName(): string
    {
        $initial = '' !== $this->lastName ? mb_strtoupper(mb_substr($this->lastName, 0, 1)).'.' : '';

        return trim($this->firstName.' '.$initial);
    }

    public function getPlayerId(): ?string
    {
        return $this->playerId;
    }

    public function setPlayerId(?string $playerId): static
    {
        $this->playerId = $playerId;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function setVerificationToken(?string $verificationToken): static
    {
        $this->verificationToken = $verificationToken;

        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeImmutable $resetTokenExpiresAt): static
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;

        return $this;
    }

    public function getPreferredLocale(): string
    {
        return $this->preferredLocale;
    }

    public function setPreferredLocale(string $preferredLocale): static
    {
        $this->preferredLocale = $preferredLocale;

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

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getDeletionToken(): ?string
    {
        return $this->deletionToken;
    }

    public function setDeletionToken(?string $deletionToken): static
    {
        $this->deletionToken = $deletionToken;

        return $this;
    }

    public function getDeletionTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->deletionTokenExpiresAt;
    }

    public function setDeletionTokenExpiresAt(?\DateTimeImmutable $deletionTokenExpiresAt): static
    {
        $this->deletionTokenExpiresAt = $deletionTokenExpiresAt;

        return $this;
    }

    /**
     * Anonymize the account: replace all PII with placeholders.
     *
     * @see docs/features.md F1.8 — Account deletion & data export (GDPR)
     */
    public function anonymize(): void
    {
        $this->isAnonymized = true;
        $this->deletedAt ??= new \DateTimeImmutable();
        $this->email = 'anonymized-'.$this->id.'@example.com';
        $this->screenName = 'anonymous-'.$this->id;
        $this->firstName = 'Anonymous';
        $this->lastName = 'User';
        $this->playerId = null;
        $this->password = '';
        $this->roles = [];
        $this->verificationToken = null;
        $this->resetToken = null;
        $this->resetTokenExpiresAt = null;
        $this->deletionToken = null;
        $this->deletionTokenExpiresAt = null;
        $this->notificationPreferences = null;
    }

    public function isAnonymized(): bool
    {
        return $this->isAnonymized;
    }

    public function setIsAnonymized(bool $isAnonymized): static
    {
        $this->isAnonymized = $isAnonymized;

        return $this;
    }

    /**
     * @return Collection<int, Deck>
     */
    public function getOwnedDecks(): Collection
    {
        return $this->ownedDecks;
    }

    /**
     * @return Collection<int, Borrow>
     */
    public function getBorrowRequests(): Collection
    {
        return $this->borrowRequests;
    }

    /**
     * @return Collection<int, EventEngagement>
     */
    public function getEventEngagements(): Collection
    {
        return $this->eventEngagements;
    }

    /**
     * @return Collection<int, EventStaff>
     */
    public function getStaffAssignments(): Collection
    {
        return $this->staffAssignments;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    /**
     * Returns whether a notification channel is enabled for the given type.
     * When no preferences are stored (null), all channels default to enabled.
     *
     * @see docs/features.md F8.3 — Notification preferences
     */
    public function isNotificationEnabled(NotificationType $type, string $channel): bool
    {
        if (null === $this->notificationPreferences) {
            return true;
        }

        return $this->notificationPreferences[$type->value][$channel] ?? true;
    }

    /**
     * @see docs/features.md F8.3 — Notification preferences
     */
    public function setNotificationPreference(NotificationType $type, string $channel, bool $enabled): void
    {
        if (null === $this->notificationPreferences) {
            $this->notificationPreferences = [];
        }

        $this->notificationPreferences[$type->value][$channel] = $enabled;
    }

    /**
     * Returns the full notification preferences map with defaults filled in.
     *
     * @see docs/features.md F8.3 — Notification preferences
     *
     * @return array<string, array{email: bool, inApp: bool}>
     */
    public function getNotificationPreferences(): array
    {
        $defaults = [];
        foreach (NotificationType::cases() as $type) {
            $defaults[$type->value] = [
                'email' => $this->notificationPreferences[$type->value]['email'] ?? true,
                'inApp' => $this->notificationPreferences[$type->value]['inApp'] ?? true,
            ];
        }

        return $defaults;
    }

    /**
     * @param array<string, array<string, bool>>|null $notificationPreferences
     *
     * @see docs/features.md F8.3 — Notification preferences
     */
    public function setNotificationPreferences(?array $notificationPreferences): static
    {
        $this->notificationPreferences = $notificationPreferences;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
