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

use App\Entity\HomepageLayout;
use App\Entity\HomepageLayoutTranslation;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F10.3 — HomepageLayout entity and data model
 */
class HomepageLayoutTranslationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $translation = new HomepageLayoutTranslation();

        self::assertNull($translation->getId());
        self::assertSame('en', $translation->getLocale());
        self::assertSame([], $translation->getBlockTranslations());
        self::assertNull($translation->getTitle());
        self::assertNull($translation->getOgDescription());
    }

    public function testSetTitle(): void
    {
        $translation = new HomepageLayoutTranslation();
        $translation->setTitle('Welcome to the dowsing machine');

        self::assertSame('Welcome to the dowsing machine', $translation->getTitle());
    }

    public function testSetOgDescription(): void
    {
        $translation = new HomepageLayoutTranslation();
        $translation->setOgDescription('Browse the shared deck library, find archetypes, borrow for events.');

        self::assertSame('Browse the shared deck library, find archetypes, borrow for events.', $translation->getOgDescription());
    }

    public function testSetLocale(): void
    {
        $translation = new HomepageLayoutTranslation();
        $translation->setLocale('fr');

        self::assertSame('fr', $translation->getLocale());
    }

    public function testSetHomepageLayout(): void
    {
        $layout = new HomepageLayout();
        $translation = new HomepageLayoutTranslation();
        $translation->setHomepageLayout($layout);

        self::assertSame($layout, $translation->getHomepageLayout());
    }

    public function testSetBlockTranslations(): void
    {
        $translation = new HomepageLayoutTranslation();
        $data = [
            '0' => ['title' => 'Hello'],
            '2' => ['content' => 'World'],
        ];
        $translation->setBlockTranslations($data);

        self::assertSame($data, $translation->getBlockTranslations());
    }

    public function testGetBlockTranslationReturnsCorrectData(): void
    {
        $translation = new HomepageLayoutTranslation();
        $translation->setBlockTranslations([
            0 => ['title' => 'Hero Title'],
            2 => ['content' => 'Some markdown'],
        ]);

        self::assertSame(['title' => 'Hero Title'], $translation->getBlockTranslation(0));
        self::assertSame(['content' => 'Some markdown'], $translation->getBlockTranslation(2));
    }

    public function testGetBlockTranslationReturnsEmptyArrayForMissingIndex(): void
    {
        $translation = new HomepageLayoutTranslation();
        $translation->setBlockTranslations([
            0 => ['title' => 'Hero Title'],
        ]);

        self::assertSame([], $translation->getBlockTranslation(5));
    }

    public function testSetBlockTranslation(): void
    {
        $translation = new HomepageLayoutTranslation();
        $translation->setBlockTranslation(0, ['title' => 'First']);
        $translation->setBlockTranslation(3, ['title' => 'Fourth']);

        self::assertSame(['title' => 'First'], $translation->getBlockTranslation(0));
        self::assertSame(['title' => 'Fourth'], $translation->getBlockTranslation(3));
    }

    public function testSetBlockTranslationOverwritesExisting(): void
    {
        $translation = new HomepageLayoutTranslation();
        $translation->setBlockTranslation(0, ['title' => 'Original']);
        $translation->setBlockTranslation(0, ['title' => 'Updated']);

        self::assertSame(['title' => 'Updated'], $translation->getBlockTranslation(0));
    }
}
