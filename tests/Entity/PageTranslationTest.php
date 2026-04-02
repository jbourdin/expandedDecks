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
        self::assertSame('', $translation->getContent());
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

    public function testSetContent(): void
    {
        $translation = new PageTranslation();
        $result = $translation->setContent('<p>Hello World</p>');

        self::assertSame('<p>Hello World</p>', $translation->getContent());
        self::assertSame($translation, $result);
    }
}
