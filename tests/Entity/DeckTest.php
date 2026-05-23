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
use App\Enum\DeckFormat;
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
        self::assertSame(DeckFormat::Expanded, $deck->getFormat());
        self::assertSame(DeckStatus::Available, $deck->getStatus());
        self::assertNull($deck->getArchetype());
        self::assertSame([], $deck->getLanguages());
        self::assertNull($deck->getNotes());
        self::assertFalse($deck->isPublic());
        self::assertFalse($deck->isPersonal());
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
        $result = $deck->setFormat(DeckFormat::Standard);

        self::assertSame(DeckFormat::Standard, $deck->getFormat());
        self::assertSame($deck, $result);
    }

    public function testIsStandard(): void
    {
        $deck = new Deck();

        self::assertFalse($deck->isStandard());

        $deck->setFormat(DeckFormat::Standard);
        self::assertTrue($deck->isStandard());
    }

    public function testIsLendable(): void
    {
        $deck = new Deck();

        self::assertTrue($deck->isLendable());

        $deck->setFormat(DeckFormat::Standard);
        self::assertFalse($deck->isLendable());
    }

    public function testIsEventRegisterable(): void
    {
        $deck = new Deck();

        self::assertTrue($deck->isEventRegisterable());

        $deck->setFormat(DeckFormat::Standard);
        self::assertFalse($deck->isEventRegisterable());
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

    /**
     * @see docs/features.md F2.30 — Personal deck flag
     */
    public function testSetPersonal(): void
    {
        $deck = new Deck();
        $result = $deck->setPersonal(true);

        self::assertTrue($deck->isPersonal());
        self::assertSame($deck, $result);
    }

    /**
     * @see docs/features.md F2.30 — Personal deck flag
     */
    public function testSetPersonalDoesNotMutatePublic(): void
    {
        $deck = new Deck();
        $deck->setPublic(true);

        $deck->setPersonal(true);

        self::assertTrue($deck->isPublic(), 'Personal must be orthogonal to public — toggling personal must not flip public.');
        self::assertTrue($deck->isPersonal());
    }

    /**
     * @see docs/features.md F2.30 — Personal deck flag
     */
    public function testIsLendableReturnsFalseWhenPersonal(): void
    {
        $deck = new Deck();
        self::assertTrue($deck->isLendable());

        $deck->setPersonal(true);
        self::assertFalse($deck->isLendable());
    }

    /**
     * @see docs/features.md F2.30 — Personal deck flag
     */
    public function testIsEventRegisterableReturnsFalseWhenPersonal(): void
    {
        $deck = new Deck();
        self::assertTrue($deck->isEventRegisterable());

        $deck->setPersonal(true);
        self::assertFalse($deck->isEventRegisterable());
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

    /**
     * @see docs/features.md F18.13 — Archetype variant decks
     */
    public function testOwnerIsNullableForArchetypeVariants(): void
    {
        $deck = new Deck();

        self::assertNull($deck->getOwner());
    }

    /**
     * @see docs/features.md F18.13 — Archetype variant decks
     */
    public function testCanonicalDefaultsToFalse(): void
    {
        $deck = new Deck();

        self::assertFalse($deck->isCanonical());
    }

    /**
     * @see docs/features.md F18.13 — Archetype variant decks
     */
    public function testSetCanonical(): void
    {
        $deck = new Deck();
        $deck->setCanonical(true);

        self::assertTrue($deck->isCanonical());
    }

    /**
     * @see docs/features.md F18.13 — Archetype variant decks
     */
    public function testIsArchetypeVariantWhenOwnerlessWithArchetype(): void
    {
        $deck = new Deck();
        $archetype = new Archetype();
        $deck->setArchetype($archetype);

        self::assertTrue($deck->isArchetypeVariant());
    }

    /**
     * @see docs/features.md F18.13 — Archetype variant decks
     */
    public function testIsNotArchetypeVariantWhenOwned(): void
    {
        $deck = new Deck();
        $deck->setOwner(new User());
        $deck->setArchetype(new Archetype());

        self::assertFalse($deck->isArchetypeVariant());
    }

    /**
     * @see docs/features.md F18.13 — Archetype variant decks
     */
    public function testIsNotArchetypeVariantWithoutArchetype(): void
    {
        $deck = new Deck();

        self::assertFalse($deck->isArchetypeVariant());
    }

    /**
     * @see docs/features.md F18.13 — Archetype variant decks
     */
    public function testGetOwnerOrFailReturnsOwner(): void
    {
        $deck = new Deck();
        $user = new User();
        $deck->setOwner($user);

        self::assertSame($user, $deck->getOwnerOrFail());
    }

    /**
     * @see docs/features.md F18.13 — Archetype variant decks
     */
    public function testGetOwnerOrFailThrowsForVariant(): void
    {
        $deck = new Deck();

        $this->expectException(\LogicException::class);
        $deck->getOwnerOrFail();
    }

    /**
     * @see docs/features.md F18.19 — Archetype variant ordering
     */
    public function testPositionDefaultsToZero(): void
    {
        $deck = new Deck();

        self::assertSame(0, $deck->getPosition());
    }

    /**
     * @see docs/features.md F18.19 — Archetype variant ordering
     */
    public function testSetPosition(): void
    {
        $deck = new Deck();
        $deck->setPosition(3);

        self::assertSame(3, $deck->getPosition());
    }

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function testIsOutdatedReturnsTrueWhenStatusIsOutdated(): void
    {
        $deck = new Deck();
        $deck->setStatus(DeckStatus::Outdated);

        self::assertTrue($deck->isOutdated());
    }

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function testIsOutdatedReturnsFalseForOtherStatuses(): void
    {
        $deck = new Deck();

        self::assertFalse($deck->isOutdated());

        $deck->setStatus(DeckStatus::Retired);
        self::assertFalse($deck->isOutdated());

        $deck->setStatus(DeckStatus::Available);
        self::assertFalse($deck->isOutdated());
    }

    /**
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    public function testLatestSetGetterSetter(): void
    {
        $deck = new Deck();
        self::assertNull($deck->getLatestSet());

        $set = $this->createStub(\App\Entity\TcgdexSet::class);
        $deck->setLatestSet($set);
        self::assertSame($set, $deck->getLatestSet());

        $deck->setLatestSet(null);
        self::assertNull($deck->getLatestSet());
    }
}
