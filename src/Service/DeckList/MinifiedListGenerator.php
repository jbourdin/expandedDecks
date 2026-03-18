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

use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Repository\CardPrintingRepository;
use App\Service\CardIdentity\CardIdentityResolver;
use Psr\Log\LoggerInterface;

/**
 * Generates a minified PTCGL deck list using the lowest-rarity printing of each card.
 *
 * @see docs/features.md F6.8 — Minified deck list export
 */
class MinifiedListGenerator
{
    private const array BASIC_ENERGY_NAMES = [
        'Grass Energy',
        'Fire Energy',
        'Water Energy',
        'Lightning Energy',
        'Psychic Energy',
        'Fighting Energy',
        'Darkness Energy',
        'Metal Energy',
        'Fairy Energy',
    ];

    public function __construct(
        private readonly CardPrintingRepository $printingRepository,
        private readonly CardIdentityResolver $identityResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Generate the minified PTCGL text for a DeckVersion.
     */
    public function generate(DeckVersion $version): string
    {
        $lines = [];

        foreach ($version->getCards() as $card) {
            $line = $this->resolveMinifiedLine($card);
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function resolveMinifiedLine(DeckCard $card): string
    {
        $printing = $card->getCardPrinting();

        if (null === $printing) {
            // No card printing linked — use original data
            return $this->formatLine($card->getQuantity(), $card->getCardName(), $card->getSetCode(), $card->getCardNumber());
        }

        $identity = $printing->getCardIdentity();

        // Expand printings if not yet done (lazy)
        if ($identity->getPrintings()->count() <= 1) {
            $this->identityResolver->expandPrintings($identity);
        }

        // For basic energies, use the most recent printing
        if (\in_array($card->getCardName(), self::BASIC_ENERGY_NAMES, true)) {
            $bestPrinting = $this->printingRepository->findLatestForIdentity($identity);
        } else {
            $bestPrinting = $this->printingRepository->findLowestRarityForIdentity($identity);
        }

        if (null === $bestPrinting) {
            // No Expanded-legal printing found — use original
            return $this->formatLine($card->getQuantity(), $card->getCardName(), $card->getSetCode(), $card->getCardNumber());
        }

        $setCode = $bestPrinting->getSetCode();

        // If the set code is empty (TCGdex doesn't have it), use the original
        if ('' === $setCode) {
            $setCode = $card->getSetCode();
        }

        $this->logger->debug('Minified {card}: {original} → {minified}', [
            'card' => $card->getCardName(),
            'original' => \sprintf('%s %s', $card->getSetCode(), $card->getCardNumber()),
            'minified' => \sprintf('%s %s', $setCode, $bestPrinting->getCardNumber()),
        ]);

        return $this->formatLine($card->getQuantity(), $card->getCardName(), $setCode, $bestPrinting->getCardNumber());
    }

    private function formatLine(int $quantity, string $name, string $setCode, string $cardNumber): string
    {
        return \sprintf('%d %s %s %s', $quantity, $name, $setCode, $cardNumber);
    }
}
