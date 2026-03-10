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

use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F11.2 — Menu categories
 */
class MenuCategoryTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $category = new MenuCategory();

        self::assertNull($category->getId());
        self::assertSame(0, $category->getPosition());
        self::assertInstanceOf(\DateTimeImmutable::class, $category->getCreatedAt());
        self::assertNull($category->getUpdatedAt());
        self::assertCount(0, $category->getTranslations());
        self::assertCount(0, $category->getPages());
    }

    public function testSetPosition(): void
    {
        $category = new MenuCategory();
        $result = $category->setPosition(5);

        self::assertSame(5, $category->getPosition());
        self::assertSame($category, $result, 'setPosition should return the same instance for fluent API');
    }

    public function testSetCreatedAtValueLifecycleCallback(): void
    {
        $category = new MenuCategory();
        $initialCreatedAt = $category->getCreatedAt();

        // Simulate a small delay so the timestamp differs
        usleep(1000);
        $category->setCreatedAtValue();

        self::assertInstanceOf(\DateTimeImmutable::class, $category->getCreatedAt());
        self::assertGreaterThanOrEqual($initialCreatedAt, $category->getCreatedAt());
    }

    public function testSetUpdatedAtValueLifecycleCallback(): void
    {
        $category = new MenuCategory();
        self::assertNull($category->getUpdatedAt());

        $category->setUpdatedAtValue();

        self::assertInstanceOf(\DateTimeImmutable::class, $category->getUpdatedAt());
    }

    public function testAddTranslation(): void
    {
        $category = new MenuCategory();
        $translation = new MenuCategoryTranslation();
        $translation->setLocale('en');
        $translation->setName('Decks');

        $result = $category->addTranslation($translation);

        self::assertCount(1, $category->getTranslations());
        self::assertSame($category, $result, 'addTranslation should return the same instance');
        self::assertSame($category, $translation->getMenuCategory());
    }

    public function testAddTranslationDoesNotDuplicateExistingTranslation(): void
    {
        $category = new MenuCategory();
        $translation = new MenuCategoryTranslation();
        $translation->setLocale('en');
        $translation->setName('Decks');

        $category->addTranslation($translation);
        $category->addTranslation($translation);

        self::assertCount(1, $category->getTranslations());
    }

    public function testRemoveTranslation(): void
    {
        $category = new MenuCategory();
        $translation = new MenuCategoryTranslation();
        $translation->setLocale('en');
        $translation->setName('Decks');

        $category->addTranslation($translation);
        self::assertCount(1, $category->getTranslations());

        $result = $category->removeTranslation($translation);

        self::assertCount(0, $category->getTranslations());
        self::assertSame($category, $result, 'removeTranslation should return the same instance');
    }

    public function testRemoveTranslationWithNonExistentTranslation(): void
    {
        $category = new MenuCategory();
        $translation = new MenuCategoryTranslation();

        $result = $category->removeTranslation($translation);

        self::assertCount(0, $category->getTranslations());
        self::assertSame($category, $result);
    }

    public function testGetTranslationReturnsExactLocaleMatch(): void
    {
        $category = new MenuCategory();

        $englishTranslation = new MenuCategoryTranslation();
        $englishTranslation->setLocale('en');
        $englishTranslation->setName('Decks');
        $category->addTranslation($englishTranslation);

        $frenchTranslation = new MenuCategoryTranslation();
        $frenchTranslation->setLocale('fr');
        $frenchTranslation->setName('Decks FR');
        $category->addTranslation($frenchTranslation);

        self::assertSame($frenchTranslation, $category->getTranslation('fr'));
        self::assertSame($englishTranslation, $category->getTranslation('en'));
    }

    public function testGetTranslationFallsBackToEnglish(): void
    {
        $category = new MenuCategory();

        $englishTranslation = new MenuCategoryTranslation();
        $englishTranslation->setLocale('en');
        $englishTranslation->setName('Decks');
        $category->addTranslation($englishTranslation);

        self::assertSame($englishTranslation, $category->getTranslation('de'));
    }

    public function testGetTranslationReturnsNullWhenNoTranslations(): void
    {
        $category = new MenuCategory();

        self::assertNull($category->getTranslation('en'));
        self::assertNull($category->getTranslation('fr'));
    }

    public function testGetTranslationReturnsNullWhenNoMatchAndNoEnglishFallback(): void
    {
        $category = new MenuCategory();

        $frenchTranslation = new MenuCategoryTranslation();
        $frenchTranslation->setLocale('fr');
        $frenchTranslation->setName('Decks FR');
        $category->addTranslation($frenchTranslation);

        self::assertNull($category->getTranslation('de'));
    }

    public function testGetTranslationDoesNotDoubleSearchForEnglishLocale(): void
    {
        $category = new MenuCategory();

        // Only a French translation exists; requesting 'en' should return null (no fallback loop)
        $frenchTranslation = new MenuCategoryTranslation();
        $frenchTranslation->setLocale('fr');
        $frenchTranslation->setName('Decks FR');
        $category->addTranslation($frenchTranslation);

        self::assertNull($category->getTranslation('en'));
    }

    public function testGetNameReturnsTranslatedName(): void
    {
        $category = new MenuCategory();

        $translation = new MenuCategoryTranslation();
        $translation->setLocale('en');
        $translation->setName('Events');
        $category->addTranslation($translation);

        self::assertSame('Events', $category->getName('en'));
    }

    public function testGetNameReturnsEmptyStringWithoutTranslation(): void
    {
        $category = new MenuCategory();

        self::assertSame('', $category->getName('en'));
        self::assertSame('', $category->getName('fr'));
    }

    public function testGetNameUsesLocaleFallback(): void
    {
        $category = new MenuCategory();

        $translation = new MenuCategoryTranslation();
        $translation->setLocale('en');
        $translation->setName('Tournaments');
        $category->addTranslation($translation);

        self::assertSame('Tournaments', $category->getName('de'));
    }

    public function testGetNameDefaultsToEnglishLocale(): void
    {
        $category = new MenuCategory();

        $translation = new MenuCategoryTranslation();
        $translation->setLocale('en');
        $translation->setName('Rules');
        $category->addTranslation($translation);

        self::assertSame('Rules', $category->getName());
    }
}
