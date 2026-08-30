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

use App\Repository\ChannelRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A channel represents a distinct site served from a unique domain name
 * with its own feature set. Inspired by Sylius's Channel model.
 *
 * @see docs/features.md F18.1 — Channel entity and database schema
 */
#[ORM\Entity(repositoryClass: ChannelRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_channel_code', columns: ['code'])]
#[ORM\UniqueConstraint(name: 'uniq_channel_domain', columns: ['domain'])]
#[ORM\HasLifecycleCallbacks]
class Channel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private string $code = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $domain = '';

    #[ORM\Column]
    private bool $enableDecks = false;

    #[ORM\Column]
    private bool $enableRegister = false;

    #[ORM\Column]
    private bool $enableEvents = false;

    #[ORM\Column]
    private bool $enableBorrows = false;

    #[ORM\Column]
    private bool $enableArchetypes = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableBannedCards = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $enableStaples = false;

    /**
     * @see docs/features.md F18.28 — Per-channel theme system
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $themeName = null;

    /**
     * PUBLISHED locales of this channel: the ones the public sees (locale
     * switcher, hreflang, sitemap, robots, prefixed routes). Every existing
     * consumer of getLocales() therefore stays draft-safe by construction.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $locales = ['en'];

    /**
     * DRAFT locales (F9.16): being prepared by translators, browsable only by
     * users allowed to work on them (assigned translators, translation
     * moderators, admins) and invisible everywhere public until an admin
     * publishes them by moving them into `locales`.
     *
     * @see docs/features.md F9.16 — Channel locale management
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $draftLocales = [];

    /**
     * Arbitrary key-value parameters for template rendering (brand name, footer text, etc.).
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: 'json')]
    private array $parameters = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getEnableDecks(): bool
    {
        return $this->enableDecks;
    }

    public function setEnableDecks(bool $enableDecks): static
    {
        $this->enableDecks = $enableDecks;

        return $this;
    }

    public function getEnableRegister(): bool
    {
        return $this->enableRegister;
    }

    public function setEnableRegister(bool $enableRegister): static
    {
        $this->enableRegister = $enableRegister;

        return $this;
    }

    public function getEnableEvents(): bool
    {
        return $this->enableEvents;
    }

    public function setEnableEvents(bool $enableEvents): static
    {
        $this->enableEvents = $enableEvents;

        return $this;
    }

    public function getEnableBorrows(): bool
    {
        return $this->enableBorrows;
    }

    public function setEnableBorrows(bool $enableBorrows): static
    {
        $this->enableBorrows = $enableBorrows;

        return $this;
    }

    public function getEnableArchetypes(): bool
    {
        return $this->enableArchetypes;
    }

    public function setEnableArchetypes(bool $enableArchetypes): static
    {
        $this->enableArchetypes = $enableArchetypes;

        return $this;
    }

    public function getEnableBannedCards(): bool
    {
        return $this->enableBannedCards;
    }

    public function setEnableBannedCards(bool $enableBannedCards): static
    {
        $this->enableBannedCards = $enableBannedCards;

        return $this;
    }

    public function getEnableStaples(): bool
    {
        return $this->enableStaples;
    }

    public function setEnableStaples(bool $enableStaples): static
    {
        $this->enableStaples = $enableStaples;

        return $this;
    }

    public function getThemeName(): ?string
    {
        return $this->themeName;
    }

    public function setThemeName(?string $themeName): static
    {
        $this->themeName = $themeName;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * @param list<string> $locales
     */
    public function setLocales(array $locales): static
    {
        $this->locales = $locales;
        // Publishing a locale removes it from the draft list (F9.16).
        $this->draftLocales = array_values(array_diff($this->draftLocales, $locales));

        return $this;
    }

    /**
     * @see docs/features.md F9.16 — Channel locale management
     *
     * @return list<string>
     */
    public function getDraftLocales(): array
    {
        return $this->draftLocales;
    }

    /**
     * @see docs/features.md F9.16 — Channel locale management
     *
     * @param list<string> $draftLocales
     */
    public function setDraftLocales(array $draftLocales): static
    {
        // A locale cannot be both published and draft; published wins.
        $this->draftLocales = array_values(array_diff(array_unique($draftLocales), $this->locales));

        return $this;
    }

    /**
     * Published and draft locales together — the editorial universe of the
     * channel (admin content forms, translation tooling).
     *
     * @see docs/features.md F9.16 — Channel locale management
     *
     * @return list<string>
     */
    public function getAllLocales(): array
    {
        return array_values(array_unique([...$this->locales, ...$this->draftLocales]));
    }

    /**
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function getParameter(string $key, string $default = ''): string
    {
        return $this->parameters[$key] ?? $default;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
