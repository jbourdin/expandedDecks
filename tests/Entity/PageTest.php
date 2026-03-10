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
use App\Entity\Page;
use App\Entity\PageTranslation;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F11.1 — Content pages
 */
class PageTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $page = new Page();

        self::assertNull($page->getId());
        self::assertSame('', $page->getSlug());
        self::assertNull($page->getMenuCategory());
        self::assertFalse($page->isPublished());
        self::assertNull($page->getCanonicalUrl());
        self::assertFalse($page->isNoIndex());
        self::assertInstanceOf(\DateTimeImmutable::class, $page->getCreatedAt());
        self::assertNull($page->getUpdatedAt());
        self::assertCount(0, $page->getTranslations());
    }

    public function testSetSlug(): void
    {
        $page = new Page();
        $result = $page->setSlug('about-us');

        self::assertSame('about-us', $page->getSlug());
        self::assertSame($page, $result);
    }

    public function testSetMenuCategory(): void
    {
        $page = new Page();
        $category = new MenuCategory();

        $result = $page->setMenuCategory($category);

        self::assertSame($category, $page->getMenuCategory());
        self::assertSame($page, $result);
    }

    public function testSetMenuCategoryToNull(): void
    {
        $page = new Page();
        $category = new MenuCategory();

        $page->setMenuCategory($category);
        $page->setMenuCategory(null);

        self::assertNull($page->getMenuCategory());
    }

    public function testSetIsPublished(): void
    {
        $page = new Page();
        $result = $page->setIsPublished(true);

        self::assertTrue($page->isPublished());
        self::assertSame($page, $result);
    }

    public function testSetIsPublishedToFalse(): void
    {
        $page = new Page();
        $page->setIsPublished(true);
        $page->setIsPublished(false);

        self::assertFalse($page->isPublished());
    }

    public function testSetCanonicalUrl(): void
    {
        $page = new Page();
        $result = $page->setCanonicalUrl('https://example.com/about');

        self::assertSame('https://example.com/about', $page->getCanonicalUrl());
        self::assertSame($page, $result);
    }

    public function testSetCanonicalUrlToNull(): void
    {
        $page = new Page();
        $page->setCanonicalUrl('https://example.com');
        $page->setCanonicalUrl(null);

        self::assertNull($page->getCanonicalUrl());
    }

    public function testSetNoIndex(): void
    {
        $page = new Page();
        $result = $page->setNoIndex(true);

        self::assertTrue($page->isNoIndex());
        self::assertSame($page, $result);
    }

    public function testSetCreatedAtValueLifecycleCallback(): void
    {
        $page = new Page();
        $initialCreatedAt = $page->getCreatedAt();

        usleep(1000);
        $page->setCreatedAtValue();

        self::assertInstanceOf(\DateTimeImmutable::class, $page->getCreatedAt());
        self::assertGreaterThanOrEqual($initialCreatedAt, $page->getCreatedAt());
    }

    public function testSetUpdatedAtValueLifecycleCallback(): void
    {
        $page = new Page();
        self::assertNull($page->getUpdatedAt());

        $page->setUpdatedAtValue();

        self::assertInstanceOf(\DateTimeImmutable::class, $page->getUpdatedAt());
    }

    public function testAddTranslation(): void
    {
        $page = new Page();
        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('About Us');
        $translation->setContent('Some content.');

        $result = $page->addTranslation($translation);

        self::assertCount(1, $page->getTranslations());
        self::assertSame($page, $result);
        self::assertSame($page, $translation->getPage());
    }

    public function testAddTranslationDoesNotDuplicateExistingTranslation(): void
    {
        $page = new Page();
        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('About');

        $page->addTranslation($translation);
        $page->addTranslation($translation);

        self::assertCount(1, $page->getTranslations());
    }

    public function testRemoveTranslation(): void
    {
        $page = new Page();
        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('About');

        $page->addTranslation($translation);
        self::assertCount(1, $page->getTranslations());

        $result = $page->removeTranslation($translation);

        self::assertCount(0, $page->getTranslations());
        self::assertSame($page, $result);
    }

    public function testRemoveTranslationWithNonExistentTranslation(): void
    {
        $page = new Page();
        $translation = new PageTranslation();

        $result = $page->removeTranslation($translation);

        self::assertCount(0, $page->getTranslations());
        self::assertSame($page, $result);
    }

    public function testGetTranslationReturnsExactLocaleMatch(): void
    {
        $page = new Page();

        $englishTranslation = new PageTranslation();
        $englishTranslation->setLocale('en');
        $englishTranslation->setTitle('About');
        $page->addTranslation($englishTranslation);

        $frenchTranslation = new PageTranslation();
        $frenchTranslation->setLocale('fr');
        $frenchTranslation->setTitle('A propos');
        $page->addTranslation($frenchTranslation);

        self::assertSame($frenchTranslation, $page->getTranslation('fr'));
        self::assertSame($englishTranslation, $page->getTranslation('en'));
    }

    public function testGetTranslationFallsBackToEnglish(): void
    {
        $page = new Page();

        $englishTranslation = new PageTranslation();
        $englishTranslation->setLocale('en');
        $englishTranslation->setTitle('About');
        $page->addTranslation($englishTranslation);

        self::assertSame($englishTranslation, $page->getTranslation('de'));
    }

    public function testGetTranslationReturnsNullWhenNoTranslations(): void
    {
        $page = new Page();

        self::assertNull($page->getTranslation('en'));
        self::assertNull($page->getTranslation('fr'));
    }

    public function testGetTranslationReturnsNullWhenNoMatchAndNoEnglishFallback(): void
    {
        $page = new Page();

        $frenchTranslation = new PageTranslation();
        $frenchTranslation->setLocale('fr');
        $frenchTranslation->setTitle('A propos');
        $page->addTranslation($frenchTranslation);

        self::assertNull($page->getTranslation('de'));
    }

    public function testGetTranslationDoesNotDoubleSearchForEnglishLocale(): void
    {
        $page = new Page();

        $frenchTranslation = new PageTranslation();
        $frenchTranslation->setLocale('fr');
        $frenchTranslation->setTitle('A propos');
        $page->addTranslation($frenchTranslation);

        self::assertNull($page->getTranslation('en'));
    }

    public function testGetTitleReturnsTranslatedTitle(): void
    {
        $page = new Page();

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Privacy Policy');
        $page->addTranslation($translation);

        self::assertSame('Privacy Policy', $page->getTitle('en'));
    }

    public function testGetTitleReturnsEmptyStringWithoutTranslation(): void
    {
        $page = new Page();

        self::assertSame('', $page->getTitle('en'));
        self::assertSame('', $page->getTitle('fr'));
    }

    public function testGetTitleUsesLocaleFallback(): void
    {
        $page = new Page();

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Terms of Service');
        $page->addTranslation($translation);

        self::assertSame('Terms of Service', $page->getTitle('de'));
    }

    public function testGetTitleDefaultsToEnglishLocale(): void
    {
        $page = new Page();

        $translation = new PageTranslation();
        $translation->setLocale('en');
        $translation->setTitle('Contact');
        $page->addTranslation($translation);

        self::assertSame('Contact', $page->getTitle());
    }
}
