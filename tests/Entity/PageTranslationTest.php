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

use App\Entity\Page;
use App\Entity\PageTranslation;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F11.1 — Content pages
 */
class PageTranslationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $translation = new PageTranslation();

        self::assertNull($translation->getId());
        self::assertSame('en', $translation->getLocale());
        self::assertSame('', $translation->getTitle());
        self::assertNull($translation->getSlug());
        self::assertSame('', $translation->getContent());
        self::assertNull($translation->getMetaTitle());
        self::assertNull($translation->getMetaDescription());
        self::assertNull($translation->getOgImage());
    }

    public function testSetPage(): void
    {
        $translation = new PageTranslation();
        $page = new Page();

        $result = $translation->setPage($page);

        self::assertSame($page, $translation->getPage());
        self::assertSame($translation, $result);
    }

    public function testSetLocale(): void
    {
        $translation = new PageTranslation();
        $result = $translation->setLocale('fr');

        self::assertSame('fr', $translation->getLocale());
        self::assertSame($translation, $result);
    }

    public function testSetTitle(): void
    {
        $translation = new PageTranslation();
        $result = $translation->setTitle('About Us');

        self::assertSame('About Us', $translation->getTitle());
        self::assertSame($translation, $result);
    }

    public function testSetSlug(): void
    {
        $translation = new PageTranslation();
        $result = $translation->setSlug('about-us');

        self::assertSame('about-us', $translation->getSlug());
        self::assertSame($translation, $result);
    }

    public function testSetSlugToNull(): void
    {
        $translation = new PageTranslation();
        $translation->setSlug('about-us');
        $translation->setSlug(null);

        self::assertNull($translation->getSlug());
    }

    public function testSetContent(): void
    {
        $translation = new PageTranslation();
        $result = $translation->setContent('<p>Hello World</p>');

        self::assertSame('<p>Hello World</p>', $translation->getContent());
        self::assertSame($translation, $result);
    }

    public function testSetMetaTitle(): void
    {
        $translation = new PageTranslation();
        $result = $translation->setMetaTitle('Page Meta Title');

        self::assertSame('Page Meta Title', $translation->getMetaTitle());
        self::assertSame($translation, $result);
    }

    public function testSetMetaTitleToNull(): void
    {
        $translation = new PageTranslation();
        $translation->setMetaTitle('Some Title');
        $translation->setMetaTitle(null);

        self::assertNull($translation->getMetaTitle());
    }

    public function testSetMetaDescription(): void
    {
        $translation = new PageTranslation();
        $result = $translation->setMetaDescription('A description of the page.');

        self::assertSame('A description of the page.', $translation->getMetaDescription());
        self::assertSame($translation, $result);
    }

    public function testSetMetaDescriptionToNull(): void
    {
        $translation = new PageTranslation();
        $translation->setMetaDescription('A description');
        $translation->setMetaDescription(null);

        self::assertNull($translation->getMetaDescription());
    }

    public function testSetOgImage(): void
    {
        $translation = new PageTranslation();
        $result = $translation->setOgImage('https://example.com/image.jpg');

        self::assertSame('https://example.com/image.jpg', $translation->getOgImage());
        self::assertSame($translation, $result);
    }

    public function testSetOgImageToNull(): void
    {
        $translation = new PageTranslation();
        $translation->setOgImage('https://example.com/image.jpg');
        $translation->setOgImage(null);

        self::assertNull($translation->getOgImage());
    }
}
