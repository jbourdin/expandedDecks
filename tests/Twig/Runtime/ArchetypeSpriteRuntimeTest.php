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

namespace App\Tests\Twig\Runtime;

use App\Entity\Archetype;
use App\Twig\Runtime\ArchetypeSpriteRuntime;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F2.12 — Archetype sprite pictograms
 */
class ArchetypeSpriteRuntimeTest extends TestCase
{
    private ArchetypeSpriteRuntime $runtime;

    protected function setUp(): void
    {
        $this->runtime = new ArchetypeSpriteRuntime();
    }

    public function testRenderSpritesWithEmptySlugs(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs([]);

        self::assertSame('', $this->runtime->renderSprites($archetype));
    }

    public function testRenderSpritesWithSingleSlug(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['iron-thorns']);

        $html = $this->runtime->renderSprites($archetype);

        self::assertStringContainsString('<span class="archetype-sprites">', $html);
        self::assertStringContainsString('src="/build/sprites/pokemon/iron-thorns.png"', $html);
        self::assertStringContainsString('alt="Iron Thorns"', $html);
        self::assertStringContainsString('title="Iron Thorns"', $html);
        self::assertStringContainsString('class="archetype-sprite"', $html);
    }

    public function testRenderSpritesWithMultipleSlugs(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['lugia', 'archeops']);

        $html = $this->runtime->renderSprites($archetype);

        self::assertStringContainsString('lugia.png', $html);
        self::assertStringContainsString('archeops.png', $html);
        self::assertSame(2, substr_count($html, '<img '));
    }

    public function testRenderSpritesConvertsSlugToReadableName(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['flutter-mane']);

        $html = $this->runtime->renderSprites($archetype);

        self::assertStringContainsString('alt="Flutter Mane"', $html);
        self::assertStringContainsString('title="Flutter Mane"', $html);
    }

    public function testRenderSpritesWithSingleWordSlug(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['lugia']);

        $html = $this->runtime->renderSprites($archetype);

        self::assertStringContainsString('alt="Lugia"', $html);
        self::assertStringContainsString('title="Lugia"', $html);
    }

    public function testRenderSpritesMultipleSlugsHaveCorrectTitles(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['roaring-moon', 'flutter-mane']);

        $html = $this->runtime->renderSprites($archetype);

        self::assertStringContainsString('title="Roaring Moon"', $html);
        self::assertStringContainsString('title="Flutter Mane"', $html);
    }

    public function testRenderSpritesEscapesSpecialCharacters(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['test<script>']);

        $html = $this->runtime->renderSprites($archetype);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('test&lt;script&gt;', $html);
    }
}
