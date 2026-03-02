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
use PHPUnit\Framework\TestCase;

class ArchetypeTest extends TestCase
{
    public function testSlugGeneratedOnPrePersist(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Iron Thorns ex');
        $archetype->onPrePersist();

        self::assertSame('iron-thorns-ex', $archetype->getSlug());
    }

    public function testSlugUpdatedOnPreUpdate(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');
        $archetype->onPrePersist();
        self::assertSame('ancient-box', $archetype->getSlug());

        $archetype->setName('Ancient Box V2');
        $archetype->onPreUpdate();
        self::assertSame('ancient-box-v2', $archetype->getSlug());
        self::assertNotNull($archetype->getUpdatedAt());
    }

    public function testGettersReturnExpectedValues(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Lugia Archeops');
        $archetype->onPrePersist();

        self::assertNull($archetype->getId());
        self::assertSame('Lugia Archeops', $archetype->getName());
        self::assertSame('lugia-archeops', $archetype->getSlug());
        self::assertInstanceOf(\DateTimeImmutable::class, $archetype->getCreatedAt());
        self::assertNull($archetype->getUpdatedAt());
    }

    public function testSpecialCharactersInName(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Mew VMAX / Genesect V');
        $archetype->onPrePersist();

        self::assertSame('mew-vmax-genesect-v', $archetype->getSlug());
    }
}
