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
 * Uses the minified card list (cheapest printings) as the base.
 * Cardmarket identifies cards by name + abilities + attacks (for Pokemon)
 * or by name only (for Trainers and Energy). Basic energies are excluded.
 *
 * Format:
 *   Pokemon:        {qty}x {name} {ability1} {ability2} {attack1} {attack2}
 *   Trainer/Energy: {qty}x {name}
 *
 * @see docs/features.md F6.11 — Export deck list for Cardmarket wishlist
 */
class CardmarketWishlistFormatter
{
    /**
     * Card names that Cardmarket cannot resolve without a more specific name.
     * Maps the PTCG card name to the Cardmarket-compatible name.
     *
     * @var array<string, string>
     */
    private const array CARDMARKET_NAME_OVERRIDES = [
        "Professor's Research" => "Professor's Research - Professor Sada",
    ];

    /** @var array<string, true> */
    private readonly array $basicEnergyNames;

    public function __construct(
        private readonly MinifiedCardViewBuilder $minifiedCardViewBuilder,
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
        if (null !== $version->getMinifiedCardViews()) {
            $groupedCards = MinifiedCardView::deserializeGrouped($version->getMinifiedCardViews());
        } else {
            // Fallback for deck versions not yet re-enriched with the new column
            $groupedCards = $this->minifiedCardViewBuilder->buildGrouped($version);
        }

        if ([] === $groupedCards) {
            return null;
        }

        $lines = [];

        foreach ($groupedCards as $cards) {
            foreach ($cards as $card) {
                if ($this->isBasicEnergy($card->getCardName())) {
                    continue;
                }

                $cardName = self::CARDMARKET_NAME_OVERRIDES[$card->getCardName()] ?? $card->getCardName();
                $line = \sprintf('%dx %s', $card->getQuantity(), $cardName);

                // For Pokemon cards, append ability and attack names in original card order
                if ('pokemon' === $card->getCardType()) {
                    $suffixParts = [];

                    if ('' !== $card->getAbilityNames()) {
                        $suffixParts = array_merge($suffixParts, explode(',', $card->getAbilityNames()));
                    }

                    if ('' !== $card->getAttackNames()) {
                        $suffixParts = array_merge($suffixParts, explode(',', $card->getAttackNames()));
                    }

                    if ([] !== $suffixParts) {
                        $line .= ' '.implode(' ', $suffixParts);
                    }
                }

                $lines[] = $line;
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
