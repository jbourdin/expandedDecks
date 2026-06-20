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

    /**
     * @see docs/features.md F5.13 — Printable A4 decklist PDF
     */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1920, max: 2020)]
    private ?int $yearOfBirth = null;

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

    #[ORM\Column(length: 32, nullable: true)]
    #[Assert\Length(max: 32)]
    #[Assert\Regex(pattern: '/^[a-z0-9_.]{2,32}$/i', message: 'Invalid Discord username format.')]
    private ?string $discordUsername = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $showCardmarketExport = false;

    /*
     * Public author/contributor profile (F19.8).
     *
     * These fields surface publicly ONLY when the user is credited as the
     * author or translator of published content. They MUST NOT expose login
     * or legal-identity fields (email, firstName, lastName); the public byline
     * uses screenName. See App\Service\Seo\StructuredDataBuilder::buildPerson().
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isPublicAuthor = false;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $credential = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $bio = null;

    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\All([new Assert\Url(), new Assert\Length(max: 255)])]
    private ?array $sameAs = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 255)]
    private ?string $avatarUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 255)]
    private ?string $primaryUrl = null;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Invalid public slug format.')]
    private ?string $publicSlug = null;

    /**
     * @see docs/features.md F3.14 — iCal agenda feed
     */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $calendarToken = null;

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

    public function getYearOfBirth(): ?int
    {
        return $this->yearOfBirth;
    }

    public function setYearOfBirth(?int $yearOfBirth): static
    {
        $this->yearOfBirth = $yearOfBirth;

        return $this;
    }

    public function isPublicAuthor(): bool
    {
        return $this->isPublicAuthor;
    }

    public function setIsPublicAuthor(bool $isPublicAuthor): static
    {
        $this->isPublicAuthor = $isPublicAuthor;

        return $this;
    }

    public function getCredential(): ?string
    {
        return $this->credential;
    }

    public function setCredential(?string $credential): static
    {
        $this->credential = $credential;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSameAs(): array
    {
        return $this->sameAs ?? [];
    }

    /**
     * @param list<string>|null $sameAs
     */
    public function setSameAs(?array $sameAs): static
    {
        // Drop empty entries so a trailing blank line in the form never yields "".
        $this->sameAs = null === $sameAs ? null : array_values(array_filter($sameAs, static fn (string $url): bool => '' !== trim($url)));

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getPrimaryUrl(): ?string
    {
        return $this->primaryUrl;
    }

    public function setPrimaryUrl(?string $primaryUrl): static
    {
        $this->primaryUrl = $primaryUrl;

        return $this;
    }

    public function getPublicSlug(): ?string
    {
        return $this->publicSlug;
    }

    public function setPublicSlug(?string $publicSlug): static
    {
        $this->publicSlug = $publicSlug;

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
        $this->email = password_hash($this->email, \PASSWORD_BCRYPT);
        $this->screenName = 'anonymous-'.$this->id;
        $this->firstName = 'Anonymous';
        $this->lastName = 'User';
        $this->playerId = null;
        $this->yearOfBirth = null;
        $this->discordUsername = null;
        $this->password = '';
        $this->roles = [];
        $this->verificationToken = null;
        $this->resetToken = null;
        $this->resetTokenExpiresAt = null;
        $this->deletionToken = null;
        $this->deletionTokenExpiresAt = null;
        $this->notificationPreferences = null;
        $this->calendarToken = null;
        // Clear the public author/contributor profile (F19.8) so no public
        // identity data survives anonymization.
        $this->isPublicAuthor = false;
        $this->credential = null;
        $this->bio = null;
        $this->sameAs = null;
        $this->avatarUrl = null;
        $this->primaryUrl = null;
        $this->publicSlug = null;
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

    /**
     * @see docs/features.md F4.16 — Lost & found deck alert
     */
    public function getDiscordUsername(): ?string
    {
        return $this->discordUsername;
    }

    /**
     * @see docs/features.md F4.16 — Lost & found deck alert
     */
    public function setDiscordUsername(?string $discordUsername): static
    {
        $this->discordUsername = $discordUsername;

        return $this;
    }

    public function isShowCardmarketExport(): bool
    {
        return $this->showCardmarketExport;
    }

    public function setShowCardmarketExport(bool $showCardmarketExport): static
    {
        $this->showCardmarketExport = $showCardmarketExport;

        return $this;
    }

    /**
     * @see docs/features.md F3.14 — iCal agenda feed
     */
    public function getCalendarToken(): ?string
    {
        return $this->calendarToken;
    }

    public function setCalendarToken(?string $calendarToken): static
    {
        $this->calendarToken = $calendarToken;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
