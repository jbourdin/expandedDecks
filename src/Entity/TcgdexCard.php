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

use App\Repository\TcgdexCardRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A TCGdex card (individual printing in a specific set).
 *
 * This is a local mirror of TCGdex data, seeded from the tcgdex/cards-database repository.
 * JSON columns store full multilingual data; generated columns extract indexed English/French names.
 */
#[ORM\Entity(repositoryClass: TcgdexCardRepository::class)]
#[ORM\Table(name: 'tcgdex_card')]
#[ORM\UniqueConstraint(name: 'uniq_tcgdex_card_set_local', columns: ['set_id', 'local_id'])]
#[ORM\Index(name: 'idx_tcgdex_card_name_en', columns: ['name_en'])]
#[ORM\Index(name: 'idx_tcgdex_card_category', columns: ['category'])]
class TcgdexCard
{
    /** TCGdex card ID (e.g. "sv02-001", "swsh9-123"). */
    #[ORM\Id]
    #[ORM\Column(length: 30)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: TcgdexSet::class, inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false)]
    private TcgdexSet $set;

    /** Card number within the set (e.g. "001", "TG30", "SWSH177"). */
    #[ORM\Column(length: 20)]
    private string $localId;

    /**
     * Multilingual card name: {"en": "Hoppip", "fr": "Granivol", ...}.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $name = [];

    /** Generated column: English name extracted from JSON, indexed for lookups. */
    #[ORM\Column(length: 100, nullable: true, insertable: false, updatable: false,
        columnDefinition: "VARCHAR(100) GENERATED ALWAYS AS (name->>'$.en') STORED")]
    private ?string $nameEn = null;

    /** Generated column: French name extracted from JSON. */
    #[ORM\Column(length: 100, nullable: true, insertable: false, updatable: false,
        columnDefinition: "VARCHAR(100) GENERATED ALWAYS AS (name->>'$.fr') STORED")]
    private ?string $nameFr = null;

    /** Card category: "Pokemon", "Trainer", or "Energy". */
    #[ORM\Column(length: 20)]
    private string $category = '';

    #[ORM\Column(nullable: true)]
    private ?int $hp = null;

    /** Trainer subtype (e.g. "Supporter", "Item", "Tool", "Stadium", "Technical Machine"). */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $trainerType = null;

    /** Energy subtype: "Normal" or "Special". */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $energyType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $rarity = null;

    #[ORM\Column]
    private bool $isExpandedLegal = false;

    /**
     * Full multilingual ability data.
     *
     * Each element: {name: {en, fr, ...}, effect: {en, fr, ...}, type: "Ability"|"Poke-POWER"|...}
     *
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(type: 'json')]
    private array $abilities = [];

    /**
     * Full multilingual attack data.
     *
     * Each element: {name: {en, fr, ...}, effect: {en, fr, ...}, cost: [...], damage: int|string|null}
     *
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(type: 'json')]
    private array $attacks = [];

    /**
     * Multilingual card effect text (trainers/energies): {"en": "...", "fr": "..."}.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $effect = null;

    /**
     * Multilingual evolveFrom name: {"en": "Hoppip", "fr": "Granivol", ...}.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $evolveFrom = null;

    /** Pokemon stage: "Basic", "Stage1", "Stage2", "MEGA", "VMAX", etc. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $stage = null;

    /**
     * Pokemon types (e.g. ["Grass"], ["Fire", "Water"]).
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $types = [];

    #[ORM\Column(nullable: true)]
    private ?int $retreat = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $regulationMark = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $illustrator = null;

    #[ORM\Column(nullable: true)]
    private ?int $cardmarketProductId = null;

    #[ORM\Column(nullable: true)]
    private ?int $tcgplayerProductId = null;

    public function __construct(string $id, TcgdexSet $set, string $localId)
    {
        $this->id = $id;
        $this->set = $set;
        $this->localId = $localId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSet(): TcgdexSet
    {
        return $this->set;
    }

    public function getLocalId(): string
    {
        return $this->localId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getName(): array
    {
        return $this->name;
    }

    public function getLocalizedName(string $locale = 'en'): ?string
    {
        $value = $this->name[$locale] ?? $this->name['en'] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $name
     */
    public function setName(array $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function getNameFr(): ?string
    {
        return $this->nameFr;
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

    public function getHp(): ?int
    {
        return $this->hp;
    }

    public function setHp(?int $hp): static
    {
        $this->hp = $hp;

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

    public function getEnergyType(): ?string
    {
        return $this->energyType;
    }

    public function setEnergyType(?string $energyType): static
    {
        $this->energyType = $energyType;

        return $this;
    }

    public function getRarity(): ?string
    {
        return $this->rarity;
    }

    public function setRarity(?string $rarity): static
    {
        $this->rarity = $rarity;

        return $this;
    }

    /**
     * Build the TCGdex CDN image URL for this card.
     *
     * The URL is derived from the card's position in the serie/set hierarchy.
     * Note: some dotted set IDs (e.g. sm3.5) may return 404 — consumers should
     * handle fallbacks (see CardImageResolver / MosaicGenerator).
     */
    public function getImageUrl(string $resolution = 'high', string $format = 'webp'): string
    {
        return \sprintf(
            'https://assets.tcgdex.net/en/%s/%s/%s/%s.%s',
            $this->set->getSerie()->getId(),
            $this->set->getId(),
            $this->localId,
            $resolution,
            $format,
        );
    }

    public function isExpandedLegal(): bool
    {
        return $this->isExpandedLegal;
    }

    public function setIsExpandedLegal(bool $isExpandedLegal): static
    {
        $this->isExpandedLegal = $isExpandedLegal;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAbilities(): array
    {
        return $this->abilities;
    }

    /**
     * Extract English ability names.
     *
     * @return list<string>
     */
    public function getAbilityNamesEn(): array
    {
        $names = [];

        foreach ($this->abilities as $ability) {
            $nameObject = $ability['name'] ?? null;
            $name = \is_array($nameObject) ? ($nameObject['en'] ?? null) : null;

            if (\is_string($name) && '' !== $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $abilities
     */
    public function setAbilities(array $abilities): static
    {
        $this->abilities = $abilities;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAttacks(): array
    {
        return $this->attacks;
    }

    /**
     * Extract English attack names.
     *
     * @return list<string>
     */
    public function getAttackNamesEn(): array
    {
        $names = [];

        foreach ($this->attacks as $attack) {
            $nameObject = $attack['name'] ?? null;
            $name = \is_array($nameObject) ? ($nameObject['en'] ?? null) : null;

            if (\is_string($name) && '' !== $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $attacks
     */
    public function setAttacks(array $attacks): static
    {
        $this->attacks = $attacks;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEffect(): ?array
    {
        return $this->effect;
    }

    /**
     * @param array<string, mixed>|null $effect
     */
    public function setEffect(?array $effect): static
    {
        $this->effect = $effect;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEvolveFrom(): ?array
    {
        return $this->evolveFrom;
    }

    /**
     * @param array<string, mixed>|null $evolveFrom
     */
    public function setEvolveFrom(?array $evolveFrom): static
    {
        $this->evolveFrom = $evolveFrom;

        return $this;
    }

    public function getStage(): ?string
    {
        return $this->stage;
    }

    public function setStage(?string $stage): static
    {
        $this->stage = $stage;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @param list<string> $types
     */
    public function setTypes(array $types): static
    {
        $this->types = $types;

        return $this;
    }

    public function getRetreat(): ?int
    {
        return $this->retreat;
    }

    public function setRetreat(?int $retreat): static
    {
        $this->retreat = $retreat;

        return $this;
    }

    public function getRegulationMark(): ?string
    {
        return $this->regulationMark;
    }

    public function setRegulationMark(?string $regulationMark): static
    {
        $this->regulationMark = $regulationMark;

        return $this;
    }

    public function getIllustrator(): ?string
    {
        return $this->illustrator;
    }

    public function setIllustrator(?string $illustrator): static
    {
        $this->illustrator = $illustrator;

        return $this;
    }

    public function getCardmarketProductId(): ?int
    {
        return $this->cardmarketProductId;
    }

    public function setCardmarketProductId(?int $cardmarketProductId): static
    {
        $this->cardmarketProductId = $cardmarketProductId;

        return $this;
    }

    public function getTcgplayerProductId(): ?int
    {
        return $this->tcgplayerProductId;
    }

    public function setTcgplayerProductId(?int $tcgplayerProductId): static
    {
        $this->tcgplayerProductId = $tcgplayerProductId;

        return $this;
    }
}
