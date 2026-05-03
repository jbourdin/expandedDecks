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

namespace App\Tests\Service;

use App\Entity\BannedCard;
use App\Entity\BannedCardPrinting;
use App\Repository\BannedCardRepository;
use App\Service\BannedCardSeedData;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardSeedDataTest extends TestCase
{
    public function testApplyToFillsAllNullFieldsForKnownCardName(): void
    {
        $card = $this->buildCard('Archeops', [['NVI', '67']]);

        $seedData = new BannedCardSeedData(
            $this->buildRepository([]),
            $this->createStub(EntityManagerInterface::class),
        );
        $seedData->applyTo($card);

        self::assertNotNull($card->getEffectiveDate());
        self::assertNotNull($card->getSourceUrl());
        self::assertNotNull($card->getExplanation());
        self::assertSame('2017-08-18', $card->getEffectiveDate()->format('Y-m-d'));
    }

    public function testApplyToPreservesExistingValues(): void
    {
        $card = $this->buildCard('Archeops', [['NVI', '67']]);
        $card->setEffectiveDate(new \DateTimeImmutable('2099-01-01'));
        $card->setSourceUrl('https://admin.example.com/manual');
        $card->setExplanation('Manual override');

        $seedData = new BannedCardSeedData(
            $this->buildRepository([]),
            $this->createStub(EntityManagerInterface::class),
        );
        $seedData->applyTo($card);

        self::assertSame('2099-01-01', $card->getEffectiveDate()->format('Y-m-d'));
        self::assertSame('https://admin.example.com/manual', $card->getSourceUrl());
        self::assertSame('Manual override', $card->getExplanation());
    }

    public function testApplyToNoOpsForUnknownCardName(): void
    {
        $card = $this->buildCard('Phantom Card', [['XYZ', '1']]);

        $seedData = new BannedCardSeedData(
            $this->buildRepository([]),
            $this->createStub(EntityManagerInterface::class),
        );
        $seedData->applyTo($card);

        self::assertNull($card->getEffectiveDate());
        self::assertNull($card->getSourceUrl());
        self::assertNull($card->getExplanation());
    }

    public function testApplyToUsesPerPrintingSeedForUnownLot90(): void
    {
        // LOT 90 → DAMAGE Unown (banned 2019-02-15)
        $card = $this->buildCard('Unown', [['LOT', '90']]);

        $seedData = new BannedCardSeedData(
            $this->buildRepository([]),
            $this->createStub(EntityManagerInterface::class),
        );
        $seedData->applyTo($card);

        self::assertNotNull($card->getEffectiveDate());
        self::assertSame('2019-02-15', $card->getEffectiveDate()->format('Y-m-d'));
        self::assertStringContainsString('DAMAGE', $card->getExplanation() ?? '');
    }

    public function testApplyToUsesPerPrintingSeedForUnownLot91(): void
    {
        // LOT 91 → distinct ban date (Cosmic Eclipse rationale)
        $card = $this->buildCard('Unown', [['LOT', '91']]);

        $seedData = new BannedCardSeedData(
            $this->buildRepository([]),
            $this->createStub(EntityManagerInterface::class),
        );
        $seedData->applyTo($card);

        self::assertNotNull($card->getEffectiveDate());
        self::assertSame('2019-11-15', $card->getEffectiveDate()->format('Y-m-d'));
    }

    public function testApplyAllReturnsFilledAndSkippedCountsAndFlushesOnce(): void
    {
        $known = $this->buildCard('Archeops', [['NVI', '67']]);
        $unknown = $this->buildCard('Phantom Card', [['XYZ', '1']]);
        $alreadyFilled = $this->buildCard('Ghetsis', [['PLF', '101']]);
        $alreadyFilled->setEffectiveDate(new \DateTimeImmutable('2099-01-01'));
        $alreadyFilled->setSourceUrl('https://admin.example.com');
        $alreadyFilled->setExplanation('Manual');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $seedData = new BannedCardSeedData(
            $this->buildRepository([$known, $unknown, $alreadyFilled]),
            $entityManager,
        );

        [$filled, $skipped] = $seedData->applyAll();

        self::assertSame(1, $filled);
        self::assertSame(2, $skipped);
        self::assertNotNull($known->getEffectiveDate());
    }

    public function testApplyAllSkipsCardsWithSeedButAllFieldsAlreadyFilled(): void
    {
        $card = $this->buildCard('Archeops', [['NVI', '67']]);
        $card->setEffectiveDate(new \DateTimeImmutable('2017-08-18'));
        $card->setSourceUrl('https://www.pokemon.com/seed');
        $card->setExplanation('seed text');

        $seedData = new BannedCardSeedData(
            $this->buildRepository([$card]),
            $this->createStub(EntityManagerInterface::class),
        );

        [$filled, $skipped] = $seedData->applyAll();

        self::assertSame(0, $filled);
        self::assertSame(1, $skipped);
    }

    /**
     * @param list<array{0: string, 1: string}> $printings
     */
    private function buildCard(string $name, array $printings): BannedCard
    {
        $card = new BannedCard();
        $card->setCardName($name);

        foreach ($printings as [$setCode, $cardNumber]) {
            $printing = new BannedCardPrinting();
            $printing->setSetCode($setCode);
            $printing->setCardNumber($cardNumber);
            $card->addPrinting($printing);
        }

        return $card;
    }

    /**
     * @param list<BannedCard> $cards
     */
    private function buildRepository(array $cards): BannedCardRepository
    {
        $repository = $this->createStub(BannedCardRepository::class);
        $repository->method('findActiveOrderedByEffectiveDate')->willReturn($cards);

        return $repository;
    }
}
