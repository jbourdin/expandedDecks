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
        $bannedCard = new BannedCard();

        self::assertNull($bannedCard->getId());
        self::assertSame('', $bannedCard->getCardName());
        self::assertSame('', $bannedCard->getSetCode());
        self::assertSame('', $bannedCard->getCardNumber());
        self::assertNull($bannedCard->getEffectiveDate());
        self::assertNull($bannedCard->getSourceUrl());
        self::assertNull($bannedCard->getExplanation());
        self::assertNull($bannedCard->getCardPrinting());
        self::assertNull($bannedCard->getDeletedAt());
        self::assertFalse($bannedCard->isDeleted());
        self::assertInstanceOf(\DateTimeImmutable::class, $bannedCard->getCreatedAt());
    }

    public function testSetAndGetCardName(): void
    {
        $bannedCard = new BannedCard();
        $result = $bannedCard->setCardName('Lysandre\'s Trump Card');

        self::assertSame('Lysandre\'s Trump Card', $bannedCard->getCardName());
        self::assertSame($bannedCard, $result);
    }

    public function testSetAndGetSetCode(): void
    {
        $bannedCard = new BannedCard();
        $result = $bannedCard->setSetCode('PHF');

        self::assertSame('PHF', $bannedCard->getSetCode());
        self::assertSame($bannedCard, $result);
    }

    public function testSetAndGetCardNumber(): void
    {
        $bannedCard = new BannedCard();
        $result = $bannedCard->setCardNumber('99');

        self::assertSame('99', $bannedCard->getCardNumber());
        self::assertSame($bannedCard, $result);
    }

    public function testSetAndGetEffectiveDate(): void
    {
        $bannedCard = new BannedCard();
        $effectiveDate = new \DateTimeImmutable('2025-01-15');

        $result = $bannedCard->setEffectiveDate($effectiveDate);

        self::assertSame($effectiveDate, $bannedCard->getEffectiveDate());
        self::assertSame($bannedCard, $result);
    }

    public function testSetEffectiveDateToNull(): void
    {
        $bannedCard = new BannedCard();
        $bannedCard->setEffectiveDate(new \DateTimeImmutable());
        $bannedCard->setEffectiveDate(null);

        self::assertNull($bannedCard->getEffectiveDate());
    }

    public function testSetAndGetSourceUrl(): void
    {
        $bannedCard = new BannedCard();
        $result = $bannedCard->setSourceUrl('https://pokemon.com/bans');

        self::assertSame('https://pokemon.com/bans', $bannedCard->getSourceUrl());
        self::assertSame($bannedCard, $result);
    }

    public function testSetSourceUrlToNull(): void
    {
        $bannedCard = new BannedCard();
        $bannedCard->setSourceUrl('https://example.com');
        $bannedCard->setSourceUrl(null);

        self::assertNull($bannedCard->getSourceUrl());
    }

    public function testSetAndGetExplanation(): void
    {
        $bannedCard = new BannedCard();
        $result = $bannedCard->setExplanation('Enables a turn-1 lock with **Garbodor**.');

        self::assertSame('Enables a turn-1 lock with **Garbodor**.', $bannedCard->getExplanation());
        self::assertSame($bannedCard, $result);
    }

    public function testSetAndGetCardPrinting(): void
    {
        $bannedCard = new BannedCard();
        $printing = new CardPrinting();

        $result = $bannedCard->setCardPrinting($printing);

        self::assertSame($printing, $bannedCard->getCardPrinting());
        self::assertSame($bannedCard, $result);
    }

    public function testSoftDeleteLifecycle(): void
    {
        $bannedCard = new BannedCard();
        self::assertFalse($bannedCard->isDeleted());

        $deletedAt = new \DateTimeImmutable('2026-04-01 12:00:00');
        $bannedCard->setDeletedAt($deletedAt);

        self::assertSame($deletedAt, $bannedCard->getDeletedAt());
        self::assertTrue($bannedCard->isDeleted());

        $bannedCard->setDeletedAt(null);
        self::assertNull($bannedCard->getDeletedAt());
        self::assertFalse($bannedCard->isDeleted());
    }
}
