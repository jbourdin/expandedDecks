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

        self::assertStringContainsString('src="/build/sprites/pokemon/iron-thorns.png"', $html);
        self::assertStringContainsString('alt="iron-thorns"', $html);
        self::assertStringContainsString('class="archetype-sprite"', $html);
        self::assertStringContainsString('width="34"', $html);
        self::assertStringContainsString('height="28"', $html);
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

    public function testRenderSpritesEscapesSpecialCharacters(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['test<script>']);

        $html = $this->runtime->renderSprites($archetype);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('test&lt;script&gt;', $html);
    }
}
