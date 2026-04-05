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
use App\Service\CardIdentity\CardIdentityResolver;
use App\Service\DeckList\MinifiedListGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F6.8 — Minified deck list export
 */
final class MinifiedListGeneratorTest extends TestCase
{
    private CardPrintingRepository $printingRepository;
    private CardIdentityResolver $identityResolver;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->printingRepository = $this->createStub(CardPrintingRepository::class);
        $this->identityResolver = $this->createStub(CardIdentityResolver::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    /**
     * Card with no CardPrinting falls back to original set/number.
     */
    public function testCardWithoutPrintingUsesOriginalSetAndNumber(): void
    {
        $card = $this->createDeckCard('Arceus VSTAR', 'BRS', '123', 'pokemon', 2);

        $version = $this->createVersionWithCards([$card]);

        $generator = new MinifiedListGenerator($this->printingRepository, $this->identityResolver, $this->logger);
        $output = $generator->generate($version);

        self::assertStringContainsString('2 Arceus VSTAR BRS 123', $output);
    }

    /**
     * Card with CardPrinting but findLowestRarityForIdentity returns null — falls back to default.
     */
    public function testCardWithPrintingButNoBestPrintingUsesDefault(): void
    {
        $identity = new CardIdentity();
        $identity->setName('Crobat V');
        $identity->setCategory('pokemon');

        $printing = new CardPrinting();
        $printing->setTcgdexId('swsh3-104');
        $printing->setCardIdentity($identity);
        $identity->addPrinting($printing);
        $extraPrinting = new CardPrinting();
        $extraPrinting->setTcgdexId('swsh3-105');
        $extraPrinting->setCardIdentity($identity);
        $identity->addPrinting($extraPrinting);

        $card = $this->createDeckCard('Crobat V', 'DAA', '104', 'pokemon', 1);
        $card->setCardPrinting($printing);

        $this->printingRepository = $this->createStub(CardPrintingRepository::class);
        $this->printingRepository->method('findCanonicalForIdentity')->willReturn(null);
        $this->printingRepository->method('computeCanonical')->willReturn(null);

        $version = $this->createVersionWithCards([$card]);

        $generator = new MinifiedListGenerator($this->printingRepository, $this->identityResolver, $this->logger);
        $output = $generator->generate($version);

        self::assertStringContainsString('1 Crobat V DAA 104', $output);
    }

    /**
     * Basic energy uses static default printings (Fire Energy → MEE 2).
     */
    public function testBasicEnergyUsesStaticDefaultPrintings(): void
    {
        $card = $this->createDeckCard('Fire Energy', 'SVE', '2', 'energy', 8);

        $version = $this->createVersionWithCards([$card]);

        $generator = new MinifiedListGenerator($this->printingRepository, $this->identityResolver, $this->logger);
        $output = $generator->generate($version);

        self::assertStringContainsString('8 Fire Energy MEE 2', $output);
    }

    /**
     * MINIFIED_PRINTING_OVERRIDES applied (GEN 73 → XY 129).
     */
    public function testMinifiedPrintingOverrideIsApplied(): void
    {
        $card = $this->createDeckCard('Some Card', 'GEN', '73', 'trainer', 1);

        $version = $this->createVersionWithCards([$card]);

        $generator = new MinifiedListGenerator($this->printingRepository, $this->identityResolver, $this->logger);
        $output = $generator->generate($version);

        self::assertStringContainsString('1 Some Card XY 129', $output);
    }

    /**
     * Duplicate cards are merged with summed quantities.
     */
    public function testDuplicateCardsAreMergedWithSummedQuantities(): void
    {
        $cardOne = $this->createDeckCard('Grass Energy', 'SVE', '1', 'energy', 3);
        $cardTwo = $this->createDeckCard('Grass Energy', 'SVE', '1', 'energy', 5);

        $version = $this->createVersionWithCards([$cardOne, $cardTwo]);

        $generator = new MinifiedListGenerator($this->printingRepository, $this->identityResolver, $this->logger);
        $output = $generator->generate($version);

        // Both resolve to MEE 1, quantities summed to 8
        self::assertStringContainsString('8 Grass Energy MEE 1', $output);
    }

    /**
     * Output format: section headers with counts, card lines, and total.
     */
    public function testOutputFormatIncludesSectionHeadersAndTotal(): void
    {
        $pokemonCard = $this->createDeckCard('Pikachu', 'BRS', '50', 'pokemon', 3);
        $trainerCard = $this->createDeckCard('Quick Ball', 'SSH', '179', 'trainer', 4);

        $trainerIdentity = new CardIdentity();
        $trainerIdentity->setName('Quick Ball');
        $trainerIdentity->setCategory('trainer');
        $trainerIdentity->setTrainerType('Item');

        $trainerPrinting = new CardPrinting();
        $trainerPrinting->setTcgdexId('swsh1-179');
        $trainerPrinting->setCardIdentity($trainerIdentity);
        $trainerIdentity->addPrinting($trainerPrinting);

        $trainerCard->setCardPrinting($trainerPrinting);
        $energyCard = $this->createDeckCard('Lightning Energy', 'SVE', '4', 'energy', 8);

        $version = $this->createVersionWithCards([$pokemonCard, $trainerCard, $energyCard]);

        $generator = new MinifiedListGenerator($this->printingRepository, $this->identityResolver, $this->logger);
        $output = $generator->generate($version);

        self::assertStringContainsString("Pok\u{e9}mon: 3", $output);
        self::assertStringContainsString('Trainer: 4', $output);
        self::assertStringContainsString('Energy: 8', $output);
        self::assertStringContainsString('Total Cards: 15', $output);
    }

    /**
     * When bestPrinting has an empty setCode, the original card setCode is used.
     */
    public function testBestPrintingWithEmptySetCodeFallsBackToCardSetCode(): void
    {
        $identity = new CardIdentity();
        $identity->setName('N');
        $identity->setCategory('trainer');

        $printing = new CardPrinting();
        $printing->setTcgdexId('bw4-101');
        $printing->setCardIdentity($identity);
        $identity->addPrinting($printing);
        $extraPrinting = new CardPrinting();
        $extraPrinting->setTcgdexId('bw4-102');
        $extraPrinting->setCardIdentity($identity);
        $identity->addPrinting($extraPrinting);

        $bestPrinting = new CardPrinting();
        $bestPrinting->setSetCode('');
        $bestPrinting->setCardNumber('92');

        $card = $this->createDeckCard('N', 'NVI', '101', 'trainer', 2);
        $card->setCardPrinting($printing);

        $this->printingRepository = $this->createStub(CardPrintingRepository::class);
        $this->printingRepository->method('findCanonicalForIdentity')->willReturn($bestPrinting);

        $version = $this->createVersionWithCards([$card]);

        $generator = new MinifiedListGenerator($this->printingRepository, $this->identityResolver, $this->logger);
        $output = $generator->generate($version);

        // Should use original card set code 'NVI' with best printing card number '92'
        self::assertStringContainsString('2 N NVI 92', $output);
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
