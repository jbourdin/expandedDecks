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

use App\Entity\CardIdentity;
use App\Entity\CardPrinting;
use App\Entity\TcgdexCard as TcgdexCardEntity;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.10 — Card identity and printing model
 */
class CardPrintingTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $printing = new CardPrinting();

        self::assertNull($printing->getId());
        self::assertNull($printing->getTcgdexCard());
        self::assertFalse($printing->isCanonical());
        self::assertSame('', $printing->getSetCode());
        self::assertSame('', $printing->getCardNumber());
        self::assertNull($printing->getRarity());
        self::assertSame(6, $printing->getRarityTier());
        self::assertNull($printing->getImageUrl());
        self::assertNull($printing->getSetReleaseDate());
        self::assertNull($printing->getPriceInCents());
        self::assertFalse($printing->isExpandedLegal());
        self::assertNull($printing->getCardmarketProductId());
        self::assertNull($printing->getTcgplayerProductId());
    }

    public function testTcgdexCardGetterSetter(): void
    {
        $printing = new CardPrinting();
        $tcgdexCard = $this->createStub(TcgdexCardEntity::class);

        $result = $printing->setTcgdexCard($tcgdexCard);

        self::assertSame($tcgdexCard, $printing->getTcgdexCard());
        self::assertSame($printing, $result, 'Setter should return $this for fluent API.');
    }

    public function testTcgdexCardAcceptsNull(): void
    {
        $printing = new CardPrinting();
        $tcgdexCard = $this->createStub(TcgdexCardEntity::class);
        $printing->setTcgdexCard($tcgdexCard);

        $printing->setTcgdexCard(null);

        self::assertNull($printing->getTcgdexCard());
    }

    public function testIsCanonicalGetterSetter(): void
    {
        $printing = new CardPrinting();

        $result = $printing->setIsCanonical(true);

        self::assertTrue($printing->isCanonical());
        self::assertSame($printing, $result, 'Setter should return $this for fluent API.');
    }

    public function testIsCanonicalCanBeSetBackToFalse(): void
    {
        $printing = new CardPrinting();
        $printing->setIsCanonical(true);

        $printing->setIsCanonical(false);

        self::assertFalse($printing->isCanonical());
    }

    public function testSetCardIdentity(): void
    {
        $printing = new CardPrinting();
        $identity = new CardIdentity();
        $identity->setName('Pikachu');

        $result = $printing->setCardIdentity($identity);

        self::assertSame($identity, $printing->getCardIdentity());
        self::assertSame($printing, $result);
    }
}
