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

use App\Entity\DeckVersion;
use App\Entity\Event;
use App\Entity\EventDeckEntry;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F3.7 — Register deck for tournament
 */
class EventDeckEntryTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $entry = new EventDeckEntry();

        self::assertNull($entry->getId());
        self::assertNull($entry->getFinalPlacement());
        self::assertNull($entry->getMatchRecord());
        self::assertInstanceOf(\DateTimeImmutable::class, $entry->getCreatedAt());
    }

    public function testSetAndGetEvent(): void
    {
        $entry = new EventDeckEntry();
        $event = new Event();

        $result = $entry->setEvent($event);

        self::assertSame($event, $entry->getEvent());
        self::assertSame($entry, $result);
    }

    public function testSetAndGetPlayer(): void
    {
        $entry = new EventDeckEntry();
        $player = new User();

        $result = $entry->setPlayer($player);

        self::assertSame($player, $entry->getPlayer());
        self::assertSame($entry, $result);
    }

    public function testSetAndGetDeckVersion(): void
    {
        $entry = new EventDeckEntry();
        $deckVersion = new DeckVersion();

        $result = $entry->setDeckVersion($deckVersion);

        self::assertSame($deckVersion, $entry->getDeckVersion());
        self::assertSame($entry, $result);
    }

    public function testSetAndGetFinalPlacement(): void
    {
        $entry = new EventDeckEntry();
        $result = $entry->setFinalPlacement(3);

        self::assertSame(3, $entry->getFinalPlacement());
        self::assertSame($entry, $result);
    }

    public function testSetFinalPlacementToNull(): void
    {
        $entry = new EventDeckEntry();
        $entry->setFinalPlacement(1);
        $entry->setFinalPlacement(null);

        self::assertNull($entry->getFinalPlacement());
    }

    public function testSetAndGetMatchRecord(): void
    {
        $entry = new EventDeckEntry();
        $result = $entry->setMatchRecord('3-1-0');

        self::assertSame('3-1-0', $entry->getMatchRecord());
        self::assertSame($entry, $result);
    }

    public function testSetMatchRecordToNull(): void
    {
        $entry = new EventDeckEntry();
        $entry->setMatchRecord('2-2-0');
        $entry->setMatchRecord(null);

        self::assertNull($entry->getMatchRecord());
    }

    public function testOnPrePersistResetsCreatedAt(): void
    {
        $entry = new EventDeckEntry();
        $initialCreatedAt = $entry->getCreatedAt();

        usleep(1000);
        $entry->onPrePersist();

        self::assertInstanceOf(\DateTimeImmutable::class, $entry->getCreatedAt());
        self::assertGreaterThanOrEqual($initialCreatedAt, $entry->getCreatedAt());
    }
}
