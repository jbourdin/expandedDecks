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
use App\Entity\ArchetypeTranslation;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F9.6 — Archetype localization
 */
class ArchetypeTranslationTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        $translation = new ArchetypeTranslation();
        $translation->setArchetype($archetype);
        $translation->setLocale('fr');
        $translation->setName('Box Anciens');
        $translation->setDescription('Description française');
        $translation->setMetaDescription('Méta description');

        self::assertNull($translation->getId());
        self::assertSame($archetype, $translation->getArchetype());
        self::assertSame('fr', $translation->getLocale());
        self::assertSame('Box Anciens', $translation->getName());
        self::assertSame('Description française', $translation->getDescription());
        self::assertSame('Méta description', $translation->getMetaDescription());
    }

    public function testDefaultValues(): void
    {
        $translation = new ArchetypeTranslation();

        self::assertSame('en', $translation->getLocale());
        self::assertSame('', $translation->getName());
        self::assertNull($translation->getDescription());
        self::assertNull($translation->getMetaDescription());
    }
}
