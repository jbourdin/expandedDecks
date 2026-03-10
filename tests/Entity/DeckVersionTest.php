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

use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F2.3 — Import deck list (PTCG text format)
 */
class DeckVersionTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $version = new DeckVersion();

        self::assertNull($version->getId());
        self::assertSame(1, $version->getVersionNumber());
        self::assertNull($version->getEstimatedValueAmount());
        self::assertNull($version->getEstimatedValueCurrency());
        self::assertNull($version->getRawList());
        self::assertSame('pending', $version->getEnrichmentStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $version->getCreatedAt());
        self::assertCount(0, $version->getCards());
    }

    public function testSetDeck(): void
    {
        $version = new DeckVersion();
        $deck = new Deck();

        $result = $version->setDeck($deck);

        self::assertSame($deck, $version->getDeck());
        self::assertSame($version, $result);
    }

    public function testSetVersionNumber(): void
    {
        $version = new DeckVersion();
        $result = $version->setVersionNumber(3);

        self::assertSame(3, $version->getVersionNumber());
        self::assertSame($version, $result);
    }

    public function testSetEstimatedValueAmount(): void
    {
        $version = new DeckVersion();
        $result = $version->setEstimatedValueAmount(5000);

        self::assertSame(5000, $version->getEstimatedValueAmount());
        self::assertSame($version, $result);
    }

    public function testSetEstimatedValueAmountToNull(): void
    {
        $version = new DeckVersion();
        $version->setEstimatedValueAmount(5000);
        $version->setEstimatedValueAmount(null);

        self::assertNull($version->getEstimatedValueAmount());
    }

    public function testSetEstimatedValueCurrency(): void
    {
        $version = new DeckVersion();
        $result = $version->setEstimatedValueCurrency('EUR');

        self::assertSame('EUR', $version->getEstimatedValueCurrency());
        self::assertSame($version, $result);
    }

    public function testSetEstimatedValueCurrencyToNull(): void
    {
        $version = new DeckVersion();
        $version->setEstimatedValueCurrency('USD');
        $version->setEstimatedValueCurrency(null);

        self::assertNull($version->getEstimatedValueCurrency());
    }

    public function testSetRawList(): void
    {
        $version = new DeckVersion();
        $rawList = "4 Pikachu V BRS 43\n2 Raichu V BRS 44";
        $result = $version->setRawList($rawList);

        self::assertSame($rawList, $version->getRawList());
        self::assertSame($version, $result);
    }

    public function testSetRawListToNull(): void
    {
        $version = new DeckVersion();
        $version->setRawList('some list');
        $version->setRawList(null);

        self::assertNull($version->getRawList());
    }

    #[DataProvider('enrichmentStatusProvider')]
    public function testSetEnrichmentStatus(string $status): void
    {
        $version = new DeckVersion();
        $result = $version->setEnrichmentStatus($status);

        self::assertSame($status, $version->getEnrichmentStatus());
        self::assertSame($version, $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function enrichmentStatusProvider(): iterable
    {
        yield 'pending' => ['pending'];
        yield 'enriching' => ['enriching'];
        yield 'done' => ['done'];
        yield 'failed' => ['failed'];
    }

    public function testAddCard(): void
    {
        $version = new DeckVersion();
        $card = new DeckCard();

        $result = $version->addCard($card);

        self::assertCount(1, $version->getCards());
        self::assertSame($version, $card->getDeckVersion());
        self::assertSame($version, $result);
    }

    public function testAddCardDoesNotDuplicateExistingCard(): void
    {
        $version = new DeckVersion();
        $card = new DeckCard();

        $version->addCard($card);
        $version->addCard($card);

        self::assertCount(1, $version->getCards());
    }

    public function testRemoveCard(): void
    {
        $version = new DeckVersion();
        $card = new DeckCard();

        $version->addCard($card);
        self::assertCount(1, $version->getCards());

        $result = $version->removeCard($card);

        self::assertCount(0, $version->getCards());
        self::assertSame($version, $result);
    }

    public function testRemoveCardWithNonExistentCard(): void
    {
        $version = new DeckVersion();
        $card = new DeckCard();

        $result = $version->removeCard($card);

        self::assertCount(0, $version->getCards());
        self::assertSame($version, $result);
    }

    public function testOnPrePersistSetsCreatedAt(): void
    {
        $version = new DeckVersion();
        $initialCreatedAt = $version->getCreatedAt();

        usleep(1000);
        $version->onPrePersist();

        self::assertInstanceOf(\DateTimeImmutable::class, $version->getCreatedAt());
        self::assertGreaterThanOrEqual($initialCreatedAt, $version->getCreatedAt());
    }
}
