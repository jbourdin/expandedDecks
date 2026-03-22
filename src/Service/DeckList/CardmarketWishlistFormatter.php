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

namespace App\Service\DeckList;

use App\Entity\DeckVersion;
use App\Service\DeckListParser;

/**
 * Generates a Cardmarket-compatible wishlist text from a DeckVersion.
 *
 * Format: one line per card, `{qty}x {name} {expansion name}`.
 * Basic energies are excluded. Uses lowest-rarity printings via MinifiedCardViewBuilder.
 *
 * @see docs/features.md F6.11 — Export deck list for Cardmarket wishlist
 */
class CardmarketWishlistFormatter
{
    /** @var array<string, true> */
    private readonly array $basicEnergyNames;

    public function __construct(
        private readonly MinifiedCardViewBuilder $minifiedCardViewBuilder,
        private readonly CardmarketExpansionNameMapper $expansionNameMapper,
    ) {
        $names = [];

        foreach (array_keys(DeckListParser::DEFAULT_BASIC_ENERGY_PRINTINGS) as $name) {
            $names[$name] = true;
        }

        $this->basicEnergyNames = $names;
    }

    /**
     * Generate the Cardmarket wishlist text for a DeckVersion.
     *
     * @return string|null The formatted text, or null if minified card data is not available
     */
    public function format(DeckVersion $version): ?string
    {
        if ($version->getCards()->isEmpty()) {
            return null;
        }

        $groupedCards = $this->minifiedCardViewBuilder->buildGrouped($version);

        if ([] === $groupedCards) {
            return null;
        }

        $lines = [];

        foreach ($groupedCards as $cards) {
            foreach ($cards as $card) {
                if ($this->isBasicEnergy($card->getCardName())) {
                    continue;
                }

                $expansionName = $this->expansionNameMapper->getExpansionName($card->getSetCode());

                if (null === $expansionName) {
                    // Fall back to the raw PTCG set code when no mapping exists
                    $expansionName = $card->getSetCode();
                }

                $lines[] = \sprintf('%dx %s %s', $card->getQuantity(), $card->getCardName(), $expansionName);
            }
        }

        if ([] === $lines) {
            return null;
        }

        return implode("\n", $lines);
    }

    private function isBasicEnergy(string $cardName): bool
    {
        return isset($this->basicEnergyNames[$cardName]);
    }
}
