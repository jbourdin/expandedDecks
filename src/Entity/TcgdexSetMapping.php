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

use App\Repository\TcgdexSetMappingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Persistent cache of TCGdex set ID ↔ PTCG code mappings.
 *
 * Populated asynchronously via BuildSetMappingsMessage and wiped
 * only by an explicit admin action (no automatic expiration).
 */
#[ORM\Entity(repositoryClass: TcgdexSetMappingRepository::class)]
#[ORM\Table(name: 'tcgdex_set_mapping')]
class TcgdexSetMapping
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $tcgdexSetId;

    #[ORM\Column(length: 16)]
    private string $ptcgCode;

    public function __construct(string $tcgdexSetId, string $ptcgCode)
    {
        $this->tcgdexSetId = $tcgdexSetId;
        $this->ptcgCode = $ptcgCode;
    }

    public function getTcgdexSetId(): string
    {
        return $this->tcgdexSetId;
    }

    public function getPtcgCode(): string
    {
        return $this->ptcgCode;
    }

    public function setPtcgCode(string $ptcgCode): static
    {
        $this->ptcgCode = $ptcgCode;

        return $this;
    }
}
