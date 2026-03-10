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

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\DeckVersion;
use App\Entity\User;
use App\Enum\DeckStatus;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F2.1 — Register a new deck (owner)
 */
class DeckTest extends TestCase
{
    public function testShortTagGeneratedOnConstruction(): void
    {
        $deck = new Deck();
        self::assertNotSame('', $deck->getShortTag());
        self::assertSame(6, \strlen($deck->getShortTag()));
    }

    public function testShortTagNotOverwrittenOnPrePersist(): void
    {
        $deck = new Deck();
        $deck->onPrePersist();
        $firstTag = $deck->getShortTag();

        $deck->onPrePersist();

        self::assertSame($firstTag, $deck->getShortTag());
    }

    public function testShortTagCharsetValid(): void
    {
        $deck = new Deck();
        $deck->onPrePersist();

        self::assertMatchesRegularExpression('/^[A-HJ-NP-Z0-9]{6}$/', $deck->getShortTag());
    }

    public function testGenerateShortTagLength(): void
    {
        // Generate multiple tags to check length consistency
        for ($i = 0; $i < 20; ++$i) {
            $deck = new Deck();
            $deck->onPrePersist();
            self::assertSame(6, \strlen($deck->getShortTag()));
        }
    }

    public function testOnPrePersistSetsCreatedAt(): void
    {
        $deck = new Deck();
        $initialCreatedAt = $deck->getCreatedAt();

        usleep(1000);
        $deck->onPrePersist();

        self::assertInstanceOf(\DateTimeImmutable::class, $deck->getCreatedAt());
        self::assertGreaterThanOrEqual($initialCreatedAt, $deck->getCreatedAt());
    }

    public function testOnPrePersistRegeneratesEmptyShortTag(): void
    {
        $deck = new Deck();

        // Use reflection to clear the shortTag to simulate an empty state
        $reflection = new \ReflectionProperty(Deck::class, 'shortTag');
        $reflection->setValue($deck, '');

        $deck->onPrePersist();

        self::assertNotSame('', $deck->getShortTag());
        self::assertSame(6, \strlen($deck->getShortTag()));
        self::assertMatchesRegularExpression('/^[A-HJ-NP-Z0-9]{6}$/', $deck->getShortTag());
    }

    public function testOnPreUpdateSetsUpdatedAt(): void
    {
        $deck = new Deck();
        self::assertNull($deck->getUpdatedAt());

        $deck->onPreUpdate();

        self::assertInstanceOf(\DateTimeImmutable::class, $deck->getUpdatedAt());
    }

    public function testDefaultValues(): void
    {
        $deck = new Deck();

        self::assertNull($deck->getId());
        self::assertSame('', $deck->getName());
        self::assertSame('Expanded', $deck->getFormat());
        self::assertSame(DeckStatus::Available, $deck->getStatus());
        self::assertNull($deck->getArchetype());
        self::assertSame([], $deck->getLanguages());
        self::assertNull($deck->getNotes());
        self::assertFalse($deck->isPublic());
        self::assertNull($deck->getCurrentVersion());
        self::assertInstanceOf(\DateTimeImmutable::class, $deck->getCreatedAt());
        self::assertNull($deck->getUpdatedAt());
        self::assertCount(0, $deck->getVersions());
        self::assertCount(0, $deck->getBorrows());
        self::assertCount(0, $deck->getEventRegistrations());
    }

    public function testSetName(): void
    {
        $deck = new Deck();
        $result = $deck->setName('Lugia Archeops');

        self::assertSame('Lugia Archeops', $deck->getName());
        self::assertSame($deck, $result);
    }

    public function testSetOwner(): void
    {
        $deck = new Deck();
        $owner = new User();

        $result = $deck->setOwner($owner);

        self::assertSame($owner, $deck->getOwner());
        self::assertSame($deck, $result);
    }

    public function testSetFormat(): void
    {
        $deck = new Deck();
        $result = $deck->setFormat('Standard');

        self::assertSame('Standard', $deck->getFormat());
        self::assertSame($deck, $result);
    }

    public function testSetStatus(): void
    {
        $deck = new Deck();
        $result = $deck->setStatus(DeckStatus::Lent);

        self::assertSame(DeckStatus::Lent, $deck->getStatus());
        self::assertSame($deck, $result);
    }

    public function testSetArchetype(): void
    {
        $deck = new Deck();
        $archetype = new Archetype();

        $result = $deck->setArchetype($archetype);

        self::assertSame($archetype, $deck->getArchetype());
        self::assertSame($deck, $result);
    }

    public function testSetArchetypeToNull(): void
    {
        $deck = new Deck();
        $archetype = new Archetype();

        $deck->setArchetype($archetype);
        $deck->setArchetype(null);

        self::assertNull($deck->getArchetype());
    }

    public function testSetLanguages(): void
    {
        $deck = new Deck();
        $result = $deck->setLanguages(['en', 'fr']);

        self::assertSame(['en', 'fr'], $deck->getLanguages());
        self::assertSame($deck, $result);
    }

    public function testSetNotes(): void
    {
        $deck = new Deck();
        $result = $deck->setNotes('Missing one card.');

        self::assertSame('Missing one card.', $deck->getNotes());
        self::assertSame($deck, $result);
    }

    public function testSetNotesToNull(): void
    {
        $deck = new Deck();
        $deck->setNotes('Some notes');
        $deck->setNotes(null);

        self::assertNull($deck->getNotes());
    }

    public function testSetPublic(): void
    {
        $deck = new Deck();
        $result = $deck->setPublic(true);

        self::assertTrue($deck->isPublic());
        self::assertSame($deck, $result);
    }

    public function testSetCurrentVersion(): void
    {
        $deck = new Deck();
        $version = new DeckVersion();

        $result = $deck->setCurrentVersion($version);

        self::assertSame($version, $deck->getCurrentVersion());
        self::assertSame($deck, $result);
    }

    public function testSetCurrentVersionToNull(): void
    {
        $deck = new Deck();
        $version = new DeckVersion();

        $deck->setCurrentVersion($version);
        $deck->setCurrentVersion(null);

        self::assertNull($deck->getCurrentVersion());
    }
}
