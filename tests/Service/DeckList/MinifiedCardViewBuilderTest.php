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

namespace App\Tests\Service\DeckList;

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Repository\CardPrintingRepository;
use App\Service\DeckList\MinifiedCardViewBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.8 — Minified deck list export
 */
final class MinifiedCardViewBuilderTest extends TestCase
{
    /**
     * Card with no CardPrinting returns default (original set/number).
     */
    public function testCardWithoutPrintingReturnsOriginalSetAndNumber(): void
    {
        $card = $this->createDeckCard('Arceus VSTAR', 'BRS', '123', 'pokemon', 2);

        $version = $this->createVersionWithCards([$card]);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $builder = new MinifiedCardViewBuilder($printingRepository);

        $grouped = $builder->buildGrouped($version);

        self::assertArrayHasKey('pokemon', $grouped);
        self::assertCount(1, $grouped['pokemon']);
        self::assertSame('BRS', $grouped['pokemon'][0]->getSetCode());
        self::assertSame('123', $grouped['pokemon'][0]->getCardNumber());
        self::assertSame(2, $grouped['pokemon'][0]->getQuantity());
    }

    /**
     * Card with CardPrinting but findLowestRarityForIdentity returns null — falls back to default.
     */
    public function testCardWithPrintingButNoBestPrintingReturnsDefault(): void
    {
        $identity = new CardIdentity();
        $identity->setName('Arceus VSTAR');
        $identity->setCategory('pokemon');
        $identity->setAbilityNames('');
        $identity->setAttackNames('');

        $printing = new CardPrinting();
        $printing->setTcgdexId('swsh9-123');
        $printing->setCardIdentity($identity);
        $identity->addPrinting($printing);
        // Add a second printing so expandPrintings is not triggered
        $extraPrinting = new CardPrinting();
        $extraPrinting->setTcgdexId('swsh9-124');
        $extraPrinting->setCardIdentity($identity);
        $identity->addPrinting($extraPrinting);

        $card = $this->createDeckCard('Arceus VSTAR', 'BRS', '123', 'pokemon', 1);
        $card->setCardPrinting($printing);
        $card->setImageUrl('https://example.com/original.webp');

        $version = $this->createVersionWithCards([$card]);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findLowestRarityForIdentity')->willReturn(null);

        $builder = new MinifiedCardViewBuilder($printingRepository);
        $grouped = $builder->buildGrouped($version);

        self::assertSame('BRS', $grouped['pokemon'][0]->getSetCode());
        self::assertSame('123', $grouped['pokemon'][0]->getCardNumber());
        self::assertSame('https://example.com/original.webp', $grouped['pokemon'][0]->getImageUrl());
    }

    /**
     * Basic energy uses static default printings (e.g. Fire Energy → MEE 2).
     */
    public function testBasicEnergyUsesStaticDefaultPrintings(): void
    {
        $card = $this->createDeckCard('Fire Energy', 'SVE', '2', 'energy', 4);

        $version = $this->createVersionWithCards([$card]);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $builder = new MinifiedCardViewBuilder($printingRepository);

        $grouped = $builder->buildGrouped($version);

        self::assertArrayHasKey('energy', $grouped);
        self::assertSame('MEE', $grouped['energy'][0]->getSetCode());
        self::assertSame('2', $grouped['energy'][0]->getCardNumber());
        self::assertStringContainsString('MEE_EN_2', (string) $grouped['energy'][0]->getImageUrl());
    }

    /**
     * MINIFIED_PRINTING_OVERRIDES applied (GEN 73 → XY 129).
     */
    public function testMinifiedPrintingOverrideIsApplied(): void
    {
        $card = $this->createDeckCard('Some Card', 'GEN', '73', 'trainer', 1);

        $version = $this->createVersionWithCards([$card]);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $builder = new MinifiedCardViewBuilder($printingRepository);

        $grouped = $builder->buildGrouped($version);

        self::assertSame('XY', $grouped['trainer'][0]->getSetCode());
        self::assertSame('129', $grouped['trainer'][0]->getCardNumber());
    }

    /**
     * Card merging: duplicate name+set+number results in summed quantities.
     */
    public function testDuplicateCardsAreMergedWithSummedQuantities(): void
    {
        $cardOne = $this->createDeckCard('Water Energy', 'SVE', '3', 'energy', 2);
        $cardTwo = $this->createDeckCard('Water Energy', 'SVE', '3', 'energy', 3);

        $version = $this->createVersionWithCards([$cardOne, $cardTwo]);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $builder = new MinifiedCardViewBuilder($printingRepository);

        $grouped = $builder->buildGrouped($version);

        // Both Water Energy cards should merge (both resolve to MEE 3)
        self::assertCount(1, $grouped['energy']);
        self::assertSame(5, $grouped['energy'][0]->getQuantity());
    }

    /**
     * When bestPrinting has an empty setCode, the original card setCode is used.
     */
    public function testBestPrintingWithEmptySetCodeFallsBackToCardSetCode(): void
    {
        $identity = new CardIdentity();
        $identity->setName('Quick Ball');
        $identity->setCategory('trainer');
        $identity->setAbilityNames('');
        $identity->setAttackNames('');

        $printing = new CardPrinting();
        $printing->setTcgdexId('swsh1-179');
        $printing->setCardIdentity($identity);
        $identity->addPrinting($printing);
        $extraPrinting = new CardPrinting();
        $extraPrinting->setTcgdexId('swsh1-180');
        $extraPrinting->setCardIdentity($identity);
        $identity->addPrinting($extraPrinting);

        $bestPrinting = new CardPrinting();
        $bestPrinting->setSetCode('');
        $bestPrinting->setCardNumber('99');
        $bestPrinting->setImageUrl('https://example.com/best.webp');

        $card = $this->createDeckCard('Quick Ball', 'SSH', '179', 'trainer', 4);
        $card->setCardPrinting($printing);

        $version = $this->createVersionWithCards([$card]);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findLowestRarityForIdentity')->willReturn($bestPrinting);

        $builder = new MinifiedCardViewBuilder($printingRepository);
        $grouped = $builder->buildGrouped($version);

        // Should fall back to the original card's set code 'SSH'
        self::assertSame('SSH', $grouped['trainer'][0]->getSetCode());
        self::assertSame('99', $grouped['trainer'][0]->getCardNumber());
    }

    /**
     * Covers the success path: bestPrinting found with valid set code.
     */
    public function testBestPrintingWithValidSetCodeIsUsed(): void
    {
        $identity = new CardIdentity();
        $identity->setName('Ultra Ball');
        $identity->setCategory('trainer');
        $identity->setAbilityNames('');
        $identity->setAttackNames('');

        $printing = new CardPrinting();
        $printing->setTcgdexId('sv1-196');
        $printing->setCardIdentity($identity);
        $identity->addPrinting($printing);
        $extraPrinting = new CardPrinting();
        $extraPrinting->setTcgdexId('sv1-197');
        $extraPrinting->setCardIdentity($identity);
        $identity->addPrinting($extraPrinting);

        $bestPrinting = new CardPrinting();
        $bestPrinting->setSetCode('SUM');
        $bestPrinting->setCardNumber('135');
        $bestPrinting->setImageUrl('https://example.com/ultra-ball-common.webp');

        $card = $this->createDeckCard('Ultra Ball', 'SV1', '196', 'trainer', 4);
        $card->setCardPrinting($printing);
        $card->setImageUrl('https://example.com/original.webp');

        $version = $this->createVersionWithCards([$card]);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findLowestRarityForIdentity')->willReturn($bestPrinting);

        $builder = new MinifiedCardViewBuilder($printingRepository);
        $grouped = $builder->buildGrouped($version);

        self::assertSame('SUM', $grouped['trainer'][0]->getSetCode());
        self::assertSame('135', $grouped['trainer'][0]->getCardNumber());
        self::assertSame('https://example.com/ultra-ball-common.webp', $grouped['trainer'][0]->getImageUrl());
    }

    /**
     * Covers identity signature resolution from card printing.
     */
    public function testCardWithPrintingResolvesIdentitySignatures(): void
    {
        $identity = new CardIdentity();
        $identity->setName('Mew VMAX');
        $identity->setCategory('pokemon');
        $identity->setAbilityNames('Cross Fusion Strike');
        $identity->setAttackNames('Max Miracle');

        $printing = new CardPrinting();
        $printing->setTcgdexId('swsh8-114');
        $printing->setCardIdentity($identity);
        $identity->addPrinting($printing);
        $extraPrinting = new CardPrinting();
        $extraPrinting->setTcgdexId('swsh8-115');
        $extraPrinting->setCardIdentity($identity);
        $identity->addPrinting($extraPrinting);

        $bestPrinting = new CardPrinting();
        $bestPrinting->setSetCode('FST');
        $bestPrinting->setCardNumber('114');
        $bestPrinting->setImageUrl('https://example.com/mew.webp');

        $card = $this->createDeckCard('Mew VMAX', 'FST', '114', 'pokemon', 1);
        $card->setCardPrinting($printing);

        $version = $this->createVersionWithCards([$card]);

        $printingRepository = $this->createStub(CardPrintingRepository::class);
        $printingRepository->method('findLowestRarityForIdentity')->willReturn($bestPrinting);

        $builder = new MinifiedCardViewBuilder($printingRepository);
        $grouped = $builder->buildGrouped($version);

        self::assertSame('Cross Fusion Strike', $grouped['pokemon'][0]->getAbilityNames());
        self::assertSame('Max Miracle', $grouped['pokemon'][0]->getAttackNames());
    }

    private function createDeckCard(string $name, string $setCode, string $cardNumber, string $cardType, int $quantity): DeckCard
    {
        $card = new DeckCard();
        $card->setCardName($name);
        $card->setSetCode($setCode);
        $card->setCardNumber($cardNumber);
        $card->setCardType($cardType);
        $card->setQuantity($quantity);

        return $card;
    }

    /**
     * @param list<DeckCard> $cards
     */
    private function createVersionWithCards(array $cards): DeckVersion
    {
        $version = new DeckVersion();

        foreach ($cards as $card) {
            $version->addCard($card);
        }

        return $version;
    }
}
