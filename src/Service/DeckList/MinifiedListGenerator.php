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
use App\Service\DeckListParser;
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
    public function __construct(
        private readonly CardPrintingRepository $printingRepository,
        private readonly CardIdentityResolver $identityResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Generate the minified PTCGL text for a DeckVersion.
     */
    private const array SECTION_LABELS = ['pokemon' => 'Pokémon', 'trainer' => 'Trainer', 'energy' => 'Energy'];
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
        $currentType = null;

        foreach ($entries as $entry) {
            $type = $entry['cardType'];

            if ($type !== $currentType) {
                if (null !== $currentType) {
                    $lines[] = '';
                }

                $sectionCount = $this->countSectionCards($entries, $type);
                $lines[] = \sprintf('%s: %d', self::SECTION_LABELS[$type] ?? ucfirst($type), $sectionCount);
                $currentType = $type;
            }

            $lines[] = $this->formatLine($entry['quantity'], $entry['name'], $entry['setCode'], $entry['cardNumber']);
        }

        $lines[] = '';
        $lines[] = \sprintf('Total Cards: %d', $this->countTotalCards($entries));

        return implode("\n", $lines);
    }

    /**
     * @return array{quantity: int, name: string, setCode: string, cardNumber: string, cardType: string, trainerSubtype: ?string}
     */
    private function resolveMinifiedCard(DeckCard $card): array
    {
        // Static overrides for known TCGdex data issues
        $overrideKey = strtoupper($card->getSetCode()).'|'.$card->getCardNumber();
        $override = DeckListParser::MINIFIED_PRINTING_OVERRIDES[$overrideKey] ?? null;

        if (null !== $override) {
            $this->logger->debug('Minified {card}: {original} → {minified} (override)', [
                'card' => $card->getCardName(),
                'original' => \sprintf('%s %s', $card->getSetCode(), $card->getCardNumber()),
                'minified' => \sprintf('%s %s', $override['setCode'], $override['cardNumber']),
            ]);

            return [
                'quantity' => $card->getQuantity(),
                'name' => $card->getCardName(),
                'setCode' => $override['setCode'],
                'cardNumber' => $override['cardNumber'],
                'cardType' => $card->getCardType(),
                'trainerSubtype' => $card->getTrainerSubtype(),
            ];
        }

        // Basic energies always use the default printing (MEE for standard types, SUM for Fairy)
        $energyDefault = DeckListParser::DEFAULT_BASIC_ENERGY_PRINTINGS[$card->getCardName()] ?? null;

        if (null !== $energyDefault) {
            $this->logger->debug('Minified {card}: {original} → {minified}', [
                'card' => $card->getCardName(),
                'original' => \sprintf('%s %s', $card->getSetCode(), $card->getCardNumber()),
                'minified' => \sprintf('%s %s', $energyDefault['setCode'], $energyDefault['cardNumber']),
            ]);

            return [
                'quantity' => $card->getQuantity(),
                'name' => $card->getCardName(),
                'setCode' => $energyDefault['setCode'],
                'cardNumber' => $energyDefault['cardNumber'],
                'cardType' => $card->getCardType(),
                'trainerSubtype' => $card->getTrainerSubtype(),
            ];
        }

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

        $bestPrinting = $this->printingRepository->findLowestRarityForIdentity($identity);

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

    /**
     * @param list<array{quantity: int, name: string, setCode: string, cardNumber: string, cardType: string, trainerSubtype: ?string}> $entries
     */
    private function countSectionCards(array $entries, string $type): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            if ($entry['cardType'] === $type) {
                $count += $entry['quantity'];
            }
        }

        return $count;
    }

    /**
     * @param list<array{quantity: int, name: string, setCode: string, cardNumber: string, cardType: string, trainerSubtype: ?string}> $entries
     */
    private function countTotalCards(array $entries): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            $count += $entry['quantity'];
        }

        return $count;
    }

    private function formatLine(int $quantity, string $name, string $setCode, string $cardNumber): string
    {
        return \sprintf('%d %s %s %s', $quantity, $name, $setCode, $cardNumber);
    }
}
