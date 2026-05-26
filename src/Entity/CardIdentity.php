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

use App\Repository\CardIdentityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Groups all printings of the same functional card across sets.
 *
 * For Pokemon: identity = name + HP + ability signature + attack signature + Pokemon type.
 * Pokemon type disambiguates mechanically-identical cards printed with different elemental
 * types (e.g. Dialga GX exists in both Metal and Dragon variants with the same attacks).
 * For Trainers/Energy: identity = name only (hp=0, abilitySignature='', attackSignature='', pokemonType='').
 *
 * @see docs/features.md F6.10 — Card identity and printing model
 */
#[ORM\Entity(repositoryClass: CardIdentityRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_card_identity', columns: ['name', 'category', 'hp', 'ability_signature', 'attack_signature', 'pokemon_type'])]
class CardIdentity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 20)]
    private string $category;

    /** 0 for non-Pokemon cards (sentinel for unique constraint). */
    #[ORM\Column]
    private int $hp = 0;

    /** Sorted comma-joined ability names. Empty string for non-Pokemon cards. */
    #[ORM\Column(length: 255)]
    private string $abilitySignature = '';

    /** Comma-joined ability names in original card order (for Cardmarket export). */
    #[ORM\Column(length: 255)]
    private string $abilityNames = '';

    /** Sorted comma-joined attack names. Empty string for non-Pokemon cards. */
    #[ORM\Column(length: 255)]
    private string $attackSignature = '';

    /** Comma-joined attack names in original card order (for Cardmarket export). */
    #[ORM\Column(length: 255)]
    private string $attackNames = '';

    /** Sorted comma-joined Pokemon types (e.g. "Metal", "Dragon", "Fire,Water"). Empty string for non-Pokemon cards. */
    #[ORM\Column(length: 100)]
    private string $pokemonType = '';

    /** Trainer subtype (e.g. "Supporter", "Item", "Tool", "Stadium"). Null for non-Trainers. */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $trainerType = null;

    /** Rulebox type from {@see \App\Constants\RuleboxType}. Null for regular cards without a rule box. */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $ruleboxType = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, CardPrinting> */
    #[ORM\OneToMany(targetEntity: CardPrinting::class, mappedBy: 'cardIdentity', cascade: ['persist', 'remove'])]
    private Collection $printings;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->printings = new ArrayCollection();
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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getHp(): int
    {
        return $this->hp;
    }

    public function setHp(int $hp): static
    {
        $this->hp = $hp;

        return $this;
    }

    public function getAbilitySignature(): string
    {
        return $this->abilitySignature;
    }

    public function setAbilitySignature(string $abilitySignature): static
    {
        $this->abilitySignature = $abilitySignature;

        return $this;
    }

    public function getAbilityNames(): string
    {
        return $this->abilityNames;
    }

    public function setAbilityNames(string $abilityNames): static
    {
        $this->abilityNames = $abilityNames;

        return $this;
    }

    public function getAttackSignature(): string
    {
        return $this->attackSignature;
    }

    public function setAttackSignature(string $attackSignature): static
    {
        $this->attackSignature = $attackSignature;

        return $this;
    }

    public function getAttackNames(): string
    {
        return $this->attackNames;
    }

    public function setAttackNames(string $attackNames): static
    {
        $this->attackNames = $attackNames;

        return $this;
    }

    public function getPokemonType(): string
    {
        return $this->pokemonType;
    }

    public function setPokemonType(string $pokemonType): static
    {
        $this->pokemonType = $pokemonType;

        return $this;
    }

    public function getTrainerType(): ?string
    {
        return $this->trainerType;
    }

    public function setTrainerType(?string $trainerType): static
    {
        $this->trainerType = $trainerType;

        return $this;
    }

    public function getRuleboxType(): ?string
    {
        return $this->ruleboxType;
    }

    public function setRuleboxType(?string $ruleboxType): static
    {
        $this->ruleboxType = $ruleboxType;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, CardPrinting>
     */
    public function getPrintings(): Collection
    {
        return $this->printings;
    }

    public function addPrinting(CardPrinting $printing): static
    {
        if (!$this->printings->contains($printing)) {
            $this->printings->add($printing);
            $printing->setCardIdentity($this);
        }

        return $this;
    }
}
