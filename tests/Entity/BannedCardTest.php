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

namespace App\Tests\Entity;

use App\Entity\BannedCard;
use App\Entity\BannedCardPrinting;
use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.5 — Banned card list management
 * @see docs/features.md F6.14 — Banned cards public page
 */
class BannedCardTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $card = new BannedCard();

        self::assertNull($card->getId());
        self::assertNull($card->getCardIdentity());
        self::assertSame('', $card->getCardName());
        self::assertNull($card->getEffectiveDate());
        self::assertNull($card->getSourceUrl());
        self::assertNull($card->getExplanation());
        self::assertNull($card->getRepresentativePrinting());
        self::assertNull($card->getDeletedAt());
        self::assertFalse($card->isDeleted());
        self::assertInstanceOf(\DateTimeImmutable::class, $card->getCreatedAt());
        self::assertCount(0, $card->getPrintings());
    }

    public function testSetCardName(): void
    {
        $card = new BannedCard();
        $card->setCardName('Lysandre\'s Trump Card');
        self::assertSame('Lysandre\'s Trump Card', $card->getCardName());
    }

    public function testSetCardIdentity(): void
    {
        $identity = new CardIdentity();
        $card = new BannedCard();
        $card->setCardIdentity($identity);
        self::assertSame($identity, $card->getCardIdentity());
    }

    public function testSetRepresentativePrinting(): void
    {
        $card = new BannedCard();
        $printing = new CardPrinting();
        $card->setRepresentativePrinting($printing);
        self::assertSame($printing, $card->getRepresentativePrinting());
    }

    public function testSoftDeleteLifecycle(): void
    {
        $card = new BannedCard();
        self::assertFalse($card->isDeleted());

        $deletedAt = new \DateTimeImmutable('2026-04-01 12:00:00');
        $card->setDeletedAt($deletedAt);
        self::assertSame($deletedAt, $card->getDeletedAt());
        self::assertTrue($card->isDeleted());

        $card->setDeletedAt(null);
        self::assertFalse($card->isDeleted());
    }

    public function testAddPrintingSetsBackReference(): void
    {
        $card = new BannedCard();
        $printing = new BannedCardPrinting();
        $printing->setSetCode('AOR');
        $printing->setCardNumber('74');

        $card->addPrinting($printing);

        self::assertCount(1, $card->getPrintings());
        self::assertSame($card, $printing->getBannedCard());
    }

    public function testRemovePrinting(): void
    {
        $card = new BannedCard();
        $printing = new BannedCardPrinting();
        $card->addPrinting($printing);

        $card->removePrinting($printing);
        self::assertCount(0, $card->getPrintings());
    }
}
