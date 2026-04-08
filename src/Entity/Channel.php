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
