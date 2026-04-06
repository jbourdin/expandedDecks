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
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F2.3 — Import deck list (PTCG text format)
 * @see docs/features.md F6.1 — Parse PTCG text format
 */
class DeckCardTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $card = new DeckCard();

        self::assertNull($card->getId());
        self::assertSame('', $card->getCardName());
        self::assertSame('', $card->getSetCode());
        self::assertSame('', $card->getCardNumber());
        self::assertSame(1, $card->getQuantity());
        self::assertSame('', $card->getCardType());
        self::assertSame('en', $card->getCardLocale());
        self::assertNull($card->getCardPrinting());
    }

    public function testSetDeckVersion(): void
    {
        $card = new DeckCard();
        $version = new DeckVersion();

        $result = $card->setDeckVersion($version);

        self::assertSame($version, $card->getDeckVersion());
        self::assertSame($card, $result);
    }

    public function testSetCardName(): void
    {
        $card = new DeckCard();
        $result = $card->setCardName('Pikachu V');

        self::assertSame('Pikachu V', $card->getCardName());
        self::assertSame($card, $result);
    }

    public function testSetSetCode(): void
    {
        $card = new DeckCard();
        $result = $card->setSetCode('BRS');

        self::assertSame('BRS', $card->getSetCode());
        self::assertSame($card, $result);
    }

    public function testSetCardNumber(): void
    {
        $card = new DeckCard();
        $result = $card->setCardNumber('43');

        self::assertSame('43', $card->getCardNumber());
        self::assertSame($card, $result);
    }

    public function testSetQuantity(): void
    {
        $card = new DeckCard();
        $result = $card->setQuantity(4);

        self::assertSame(4, $card->getQuantity());
        self::assertSame($card, $result);
    }

    public function testSetCardType(): void
    {
        $card = new DeckCard();
        $result = $card->setCardType('Pokémon');

        self::assertSame('Pokémon', $card->getCardType());
        self::assertSame($card, $result);
    }

    public function testSetCardLocale(): void
    {
        $card = new DeckCard();
        $result = $card->setCardLocale('fr');

        self::assertSame('fr', $card->getCardLocale());
        self::assertSame($card, $result);
    }

    public function testComputedAccessorsReturnNullWithoutCardPrinting(): void
    {
        $card = new DeckCard();

        self::assertNull($card->getImageUrl());
        self::assertNull($card->getTrainerSubtype());
        self::assertNull($card->getTcgdexId());
    }

    public function testComputedAccessorsDelegateToCardPrinting(): void
    {
        $cardIdentity = $this->createStub(CardIdentity::class);
        $cardIdentity->method('getTrainerType')->willReturn('Supporter');

        $cardPrinting = $this->createStub(CardPrinting::class);
        $cardPrinting->method('getImageUrl')->willReturn('https://assets.tcgdex.net/en/xy/xy9/107/high.webp');
        $cardPrinting->method('getCardIdentity')->willReturn($cardIdentity);
        $cardPrinting->method('getTcgdexId')->willReturn('bkp-107');

        $card = new DeckCard();
        $card->setCardPrinting($cardPrinting);

        self::assertSame('https://assets.tcgdex.net/en/xy/xy9/107/high.webp', $card->getImageUrl());
        self::assertSame('Supporter', $card->getTrainerSubtype());
        self::assertSame('bkp-107', $card->getTcgdexId());
    }

    public function testFullCardSetup(): void
    {
        $version = new DeckVersion();
        $card = new DeckCard();

        $card->setDeckVersion($version)
            ->setCardName('Professor Sycamore')
            ->setSetCode('BKP')
            ->setCardNumber('107')
            ->setQuantity(4)
            ->setCardType('Trainer')
            ->setCardLocale('fr');

        self::assertSame($version, $card->getDeckVersion());
        self::assertSame('Professor Sycamore', $card->getCardName());
        self::assertSame('BKP', $card->getSetCode());
        self::assertSame('107', $card->getCardNumber());
        self::assertSame(4, $card->getQuantity());
        self::assertSame('Trainer', $card->getCardType());
        self::assertSame('fr', $card->getCardLocale());
    }
}
