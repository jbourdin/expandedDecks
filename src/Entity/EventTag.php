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

use App\Repository\EventTagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see docs/features.md F3.12 — Event tags
 */
#[ORM\Entity(repositoryClass: EventTagRepository::class)]
#[ORM\HasLifecycleCallbacks]
class EventTag
{
    public const int MAX_NAME_LENGTH = 50;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: self::MAX_NAME_LENGTH, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: self::MAX_NAME_LENGTH)]
    private string $name = '';

    #[ORM\Column(length: self::MAX_NAME_LENGTH, unique: true)]
    private string $slug = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\ManyToMany(targetEntity: Event::class, mappedBy: 'tags')]
    private Collection $events;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ('' === $this->slug && '' !== $this->name) {
            $this->slug = self::slugify($this->name);
        }
    }

    public static function slugify(string $name): string
    {
        $name = trim($name);

        if ('' === $name) {
            return '';
        }

        $slug = mb_strtolower($name);
        $slug = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug);
        $slug = trim($slug, '-');

        if (mb_strlen($slug) > self::MAX_NAME_LENGTH) {
            $slug = mb_substr($slug, 0, self::MAX_NAME_LENGTH);
            $slug = trim($slug, '-');
        }

        return $slug;
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
        $this->name = trim($name);
        $this->slug = self::slugify($this->name);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }
}
