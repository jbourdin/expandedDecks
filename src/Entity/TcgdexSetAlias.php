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

use App\Repository\TcgdexSetAliasRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Maps Asian (Japanese, Korean, etc.) set codes to their international TCGdex set equivalent.
 *
 * Japanese TCG products use different set codes and card numbering from international releases.
 * When a deck list uses an Asian set code (e.g. SM8, S6K, SV1S), this table resolves
 * it to the international set (e.g. sm8, swsh6, sv01). Card numbers do NOT transfer —
 * the enricher must search by name within the resolved set.
 */
#[ORM\Entity(repositoryClass: TcgdexSetAliasRepository::class)]
#[ORM\Table(name: 'tcgdex_asian_set_alias')]
class TcgdexSetAlias
{
    /** The Asian set code (e.g. "SM8", "S6K", "SV1S"). Stored uppercase. */
    #[ORM\Id]
    #[ORM\Column(length: 20)]
    private string $aliasCode;

    /** The international TCGdex set ID this alias resolves to (e.g. "sm8", "swsh6", "sv01"). */
    #[ORM\Column(length: 20)]
    private string $tcgdexSetId;

    public function __construct(string $aliasCode, string $tcgdexSetId)
    {
        $this->aliasCode = strtoupper($aliasCode);
        $this->tcgdexSetId = $tcgdexSetId;
    }

    public function getAliasCode(): string
    {
        return $this->aliasCode;
    }

    public function getTcgdexSetId(): string
    {
        return $this->tcgdexSetId;
    }
}
