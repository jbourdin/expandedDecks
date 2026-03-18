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
    private const array TYPE_ORDER = ['pokemon' => 0, 'trainer' => 1, 'energy' => 2];
    private const array TRAINER_SUBTYPE_ORDER = ['supporter' => 0, 'item' => 1, 'tool' => 2, 'stadium' => 3];

    public function generate(DeckVersion $version): string
    {
        // Key: "name|setCode|cardNumber" → aggregated data
        /** @var array<string, array{quantity: int, name: string, setCode: string, cardNumber: string, cardType: string, trainerSubtype: ?string}> $merged */
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

        // Sort: Pokemon → Trainer (supporter, item, tool, stadium) → Energy, then qty desc, name asc
        $entries = array_values($merged);
        usort($entries, static function (array $entryA, array $entryB): int {
            $typeA = self::TYPE_ORDER[$entryA['cardType']] ?? 3;
            $typeB = self::TYPE_ORDER[$entryB['cardType']] ?? 3;

            if ($typeA !== $typeB) {
                return $typeA <=> $typeB;
            }

            if ('trainer' === $entryA['cardType']) {
                $subtypeA = self::TRAINER_SUBTYPE_ORDER[strtolower((string) $entryA['trainerSubtype'])] ?? 4;
                $subtypeB = self::TRAINER_SUBTYPE_ORDER[strtolower((string) $entryB['trainerSubtype'])] ?? 4;

                if ($subtypeA !== $subtypeB) {
                    return $subtypeA <=> $subtypeB;
                }
            }

            if ($entryA['quantity'] !== $entryB['quantity']) {
                return $entryB['quantity'] <=> $entryA['quantity'];
            }

            return $entryA['name'] <=> $entryB['name'];
        });

        $lines = [];

        foreach ($entries as $entry) {
            $lines[] = $this->formatLine($entry['quantity'], $entry['name'], $entry['setCode'], $entry['cardNumber']);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{quantity: int, name: string, setCode: string, cardNumber: string, cardType: string, trainerSubtype: ?string}
     */
    private function resolveMinifiedCard(DeckCard $card): array
    {
        $default = [
            'quantity' => $card->getQuantity(),
            'name' => $card->getCardName(),
            'setCode' => $card->getSetCode(),
            'cardNumber' => $card->getCardNumber(),
            'cardType' => $card->getCardType(),
            'trainerSubtype' => $card->getTrainerSubtype(),
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
            'cardType' => $card->getCardType(),
            'trainerSubtype' => $card->getTrainerSubtype(),
        ];
    }

    private function formatLine(int $quantity, string $name, string $setCode, string $cardNumber): string
    {
        return \sprintf('%d %s %s %s', $quantity, $name, $setCode, $cardNumber);
    }
}
