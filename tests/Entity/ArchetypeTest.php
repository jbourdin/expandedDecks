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

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function testGetTranslationReturnsMatchingLocale(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        $translation = new ArchetypeTranslation();
        $translation->setLocale('fr');
        $translation->setName('Box Anciens');
        $archetype->addTranslation($translation);

        self::assertSame('Box Anciens', $archetype->getTranslation('fr')?->getName());
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function testGetTranslationFallsBackToEnglish(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        $translationEn = new ArchetypeTranslation();
        $translationEn->setLocale('en');
        $translationEn->setName('Ancient Box');
        $archetype->addTranslation($translationEn);

        self::assertSame('Ancient Box', $archetype->getTranslation('fr')?->getName());
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function testGetTranslationReturnsNullWhenNoTranslations(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        self::assertNull($archetype->getTranslation('fr'));
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function testGetLocalizedNameReturnsTranslatedName(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        $translation = new ArchetypeTranslation();
        $translation->setLocale('fr');
        $translation->setName('Box Anciens');
        $archetype->addTranslation($translation);

        self::assertSame('Box Anciens', $archetype->getLocalizedName('fr'));
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function testGetLocalizedNameFallsBackToCanonicalName(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        self::assertSame('Ancient Box', $archetype->getLocalizedName('fr'));
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function testGetLocalizedDescriptionReturnsTranslatedDescription(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        $translationEn = new ArchetypeTranslation();
        $translationEn->setLocale('en');
        $translationEn->setName('Ancient Box');
        $translationEn->setDescription('English description');
        $archetype->addTranslation($translationEn);

        $translationFr = new ArchetypeTranslation();
        $translationFr->setLocale('fr');
        $translationFr->setName('Box Anciens');
        $translationFr->setDescription('Description française');
        $archetype->addTranslation($translationFr);

        self::assertSame('Description française', $archetype->getLocalizedDescription('fr'));
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function testGetLocalizedDescriptionFallsBackToEnglish(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        $translationEn = new ArchetypeTranslation();
        $translationEn->setLocale('en');
        $translationEn->setName('Ancient Box');
        $translationEn->setDescription('English description');
        $archetype->addTranslation($translationEn);

        self::assertSame('English description', $archetype->getLocalizedDescription('fr'));
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function testGetLocalizedDescriptionReturnsNullWhenNoTranslations(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        self::assertNull($archetype->getLocalizedDescription('fr'));
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function testGetLocalizedMetaDescriptionFallsBackToEnglish(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        $translationEn = new ArchetypeTranslation();
        $translationEn->setLocale('en');
        $translationEn->setName('Ancient Box');
        $translationEn->setMetaDescription('English meta');
        $archetype->addTranslation($translationEn);

        self::assertSame('English meta', $archetype->getLocalizedMetaDescription('fr'));
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function testAddAndRemoveTranslation(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Ancient Box');

        $translation = new ArchetypeTranslation();
        $translation->setLocale('fr');
        $translation->setName('Box Anciens');

        $archetype->addTranslation($translation);
        self::assertCount(1, $archetype->getTranslations());
        self::assertSame($archetype, $translation->getArchetype());

        // Adding the same translation again should not duplicate
        $archetype->addTranslation($translation);
        self::assertCount(1, $archetype->getTranslations());

        $archetype->removeTranslation($translation);
        self::assertCount(0, $archetype->getTranslations());
    }
}
