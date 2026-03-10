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
class MenuCategoryTranslationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $translation = new MenuCategoryTranslation();

        self::assertNull($translation->getId());
        self::assertSame('en', $translation->getLocale());
        self::assertSame('', $translation->getName());
    }

    public function testSetAndGetMenuCategory(): void
    {
        $translation = new MenuCategoryTranslation();
        $category = new MenuCategory();

        $result = $translation->setMenuCategory($category);

        self::assertSame($category, $translation->getMenuCategory());
        self::assertSame($translation, $result);
    }

    public function testSetAndGetLocale(): void
    {
        $translation = new MenuCategoryTranslation();
        $result = $translation->setLocale('fr');

        self::assertSame('fr', $translation->getLocale());
        self::assertSame($translation, $result);
    }

    public function testSetAndGetName(): void
    {
        $translation = new MenuCategoryTranslation();
        $result = $translation->setName('Tournaments');

        self::assertSame('Tournaments', $translation->getName());
        self::assertSame($translation, $result);
    }
}
