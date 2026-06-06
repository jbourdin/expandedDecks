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

namespace App\Service\CardIdentity;

use App\Entity\CardPrinting;
use App\Service\Tcgdex\TcgdexApiClient;

/**
 * Resolves a user-typed card code (e.g. "LOR-093", "SV08 128") into a CardPrinting.
 *
 * Canonical home of the code parsing rule shared by the staple-card admin and
 * the OG image builder.
 *
 * @see docs/features.md F18.32 — Card-fan OG image builder
 */
class CardCodeResolver
{
    public function __construct(
        private readonly TcgdexApiClient $apiClient,
        private readonly CardIdentityResolver $identityResolver,
    ) {
    }

    /**
     * Parses a code like "LOR-093" / "LOR 093" / "LOR_093" into [setCode, cardNumber].
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parseCode(string $code): ?array
    {
        $code = trim($code);
        if (1 === preg_match('/^([A-Za-z0-9]+)[\s\-_]+([A-Za-z0-9]+)$/', $code, $matches)) {
            return [strtoupper($matches[1]), $matches[2]];
        }

        return null;
    }

    /**
     * Resolve a card code to a CardPrinting, or null when the code cannot be
     * parsed or the (setCode, cardNumber) pair is unknown to TCGdex.
     */
    public function resolve(string $code): ?CardPrinting
    {
        $parsed = self::parseCode($code);
        if (null === $parsed) {
            return null;
        }
        [$setCode, $cardNumber] = $parsed;

        $tcgdexCard = $this->apiClient->findCard($setCode, $cardNumber);
        if (null === $tcgdexCard) {
            return null;
        }

        return $this->identityResolver->resolveFromTcgdexCard($tcgdexCard);
    }
}
