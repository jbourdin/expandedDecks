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
 * When multiple deck entries resolve to the same minified printing, their quantities
 * are summed into a single line.
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
        // Key: "name|setCode|cardNumber" → aggregated quantity
        /** @var array<string, array{quantity: int, name: string, setCode: string, cardNumber: string}> $merged */
        $merged = [];

        foreach ($version->getCards() as $card) {
            $resolved = $this->resolveMinifiedCard($card);
            $key = \sprintf('%s|%s|%s', $resolved['name'], $resolved['setCode'], $resolved['cardNumber']);

            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $resolved['quantity'];
            } else {
                $merged[$key] = $resolved;
            }
        }

        $lines = [];

        foreach ($merged as $entry) {
            $lines[] = $this->formatLine($entry['quantity'], $entry['name'], $entry['setCode'], $entry['cardNumber']);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{quantity: int, name: string, setCode: string, cardNumber: string}
     */
    private function resolveMinifiedCard(DeckCard $card): array
    {
        $default = [
            'quantity' => $card->getQuantity(),
            'name' => $card->getCardName(),
            'setCode' => $card->getSetCode(),
            'cardNumber' => $card->getCardNumber(),
        ];

        $printing = $card->getCardPrinting();

        if (null === $printing) {
            return $default;
        }

        $identity = $printing->getCardIdentity();

        // Expand printings if not yet done (lazy)
        if ($identity->getPrintings()->count() <= 1) {
            $this->identityResolver->expandPrintings($identity);
        }

        if (\in_array($card->getCardName(), self::BASIC_ENERGY_NAMES, true)) {
            $bestPrinting = $this->printingRepository->findLatestForIdentity($identity);
        } else {
            $bestPrinting = $this->printingRepository->findLowestRarityForIdentity($identity);
        }

        if (null === $bestPrinting) {
            return $default;
        }

        $setCode = $bestPrinting->getSetCode();

        if ('' === $setCode) {
            $setCode = $card->getSetCode();
        }

        $this->logger->debug('Minified {card}: {original} → {minified}', [
            'card' => $card->getCardName(),
            'original' => \sprintf('%s %s', $card->getSetCode(), $card->getCardNumber()),
            'minified' => \sprintf('%s %s', $setCode, $bestPrinting->getCardNumber()),
        ]);

        return [
            'quantity' => $card->getQuantity(),
            'name' => $card->getCardName(),
            'setCode' => $setCode,
            'cardNumber' => $bestPrinting->getCardNumber(),
        ];
    }

    private function formatLine(int $quantity, string $name, string $setCode, string $cardNumber): string
    {
        return \sprintf('%d %s %s %s', $quantity, $name, $setCode, $cardNumber);
    }
}
