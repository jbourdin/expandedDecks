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

use App\Repository\TcgdexSetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A TCGdex set (e.g. "Paldea Evolved", "Brilliant Stars").
 *
 * This is a local mirror of TCGdex data, seeded from the tcgdex/cards-database repository.
 */
#[ORM\Entity(repositoryClass: TcgdexSetRepository::class)]
#[ORM\Table(name: 'tcgdex_set')]
#[ORM\Index(name: 'idx_tcgdex_set_ptcg_code', columns: ['ptcg_code'])]
class TcgdexSet
{
    /** TCGdex set ID (e.g. "sv02", "swsh9", "sm3.5"). */
    #[ORM\Id]
    #[ORM\Column(length: 20)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: TcgdexSerie::class, inversedBy: 'sets')]
    #[ORM\JoinColumn(nullable: false)]
    private TcgdexSerie $serie;

    /**
     * Multilingual name: {"en": "Paldea Evolved", "fr": "Évolutions à Paldea", ...}.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $name = [];

    /** PTCG official abbreviation or PTCGO code (e.g. "PAL", "BRS"). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $ptcgCode = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $releaseDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $officialCardCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $cardmarketId = null;

    #[ORM\Column(nullable: true)]
    private ?int $tcgplayerId = null;

    /** Set logo URL from TCGdex API. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoUrl = null;

    /** Set symbol/icon URL from TCGdex API. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $symbolUrl = null;

    /** @var Collection<int, TcgdexCard> */
    #[ORM\OneToMany(targetEntity: TcgdexCard::class, mappedBy: 'set')]
    private Collection $cards;

    public function __construct(string $id, TcgdexSerie $serie)
    {
        $this->id = $id;
        $this->serie = $serie;
        $this->cards = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSerie(): TcgdexSerie
    {
        return $this->serie;
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

    public function getPtcgCode(): ?string
    {
        return $this->ptcgCode;
    }

    public function setPtcgCode(?string $ptcgCode): static
    {
        $this->ptcgCode = $ptcgCode;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeImmutable $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getOfficialCardCount(): ?int
    {
        return $this->officialCardCount;
    }

    public function setOfficialCardCount(?int $officialCardCount): static
    {
        $this->officialCardCount = $officialCardCount;

        return $this;
    }

    public function getCardmarketId(): ?int
    {
        return $this->cardmarketId;
    }

    public function setCardmarketId(?int $cardmarketId): static
    {
        $this->cardmarketId = $cardmarketId;

        return $this;
    }

    public function getTcgplayerId(): ?int
    {
        return $this->tcgplayerId;
    }

    public function setTcgplayerId(?int $tcgplayerId): static
    {
        $this->tcgplayerId = $tcgplayerId;

        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): static
    {
        $this->logoUrl = $logoUrl;

        return $this;
    }

    public function getSymbolUrl(): ?string
    {
        return $this->symbolUrl;
    }

    public function setSymbolUrl(?string $symbolUrl): static
    {
        $this->symbolUrl = $symbolUrl;

        return $this;
    }

    /**
     * @return Collection<int, TcgdexCard>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }
}
