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
 * Maps alternative set codes (Japanese, PTCGO legacy, etc.) to international TCGdex set IDs.
 *
 * Used to resolve deck list input that uses non-standard set codes (e.g. SM8, S6K)
 * to their international equivalent (e.g. sm8 → LOT, S6K → CRE).
 */
#[ORM\Entity(repositoryClass: TcgdexSetAliasRepository::class)]
#[ORM\Table(name: 'tcgdex_set_alias')]
class TcgdexSetAlias
{
    /** The alternative set code (e.g. "SM8", "S6K", "SV1S"). Case-insensitive lookup. */
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
