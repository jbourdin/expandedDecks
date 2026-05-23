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
use App\Entity\Deck;
use App\Twig\Runtime\ArchetypeSpriteRuntime;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F2.12 — Archetype sprite pictograms
 * @see docs/features.md F2.22 — Custom Pokemon sprites on decks
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
        self::assertStringContainsString('src="/sprites/pokemon/iron-thorns.png"', $html);
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

    public function testRenderDeckSpritesWithDeckSlugs(): void
    {
        $deck = new Deck();
        $deck->setPokemonSlugs(['lugia', 'archeops']);

        $html = $this->runtime->renderDeckSprites($deck);

        self::assertStringContainsString('lugia.png', $html);
        self::assertStringContainsString('archeops.png', $html);
    }

    public function testRenderDeckSpritesFallsBackToArchetype(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['iron-thorns']);

        $deck = new Deck();
        $deck->setArchetype($archetype);

        $html = $this->runtime->renderDeckSprites($deck);

        self::assertStringContainsString('iron-thorns.png', $html);
    }

    public function testRenderDeckSpritesPrefersOwnSlugsOverArchetype(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['iron-thorns']);

        $deck = new Deck();
        $deck->setArchetype($archetype);
        $deck->setPokemonSlugs(['lugia']);

        $html = $this->runtime->renderDeckSprites($deck);

        self::assertStringContainsString('lugia.png', $html);
        self::assertStringNotContainsString('iron-thorns.png', $html);
    }

    public function testRenderDeckSpritesWithNoSlugsAndNoArchetype(): void
    {
        $deck = new Deck();

        self::assertSame('', $this->runtime->renderDeckSprites($deck));
    }

    /**
     * @see docs/features.md F2.12 — Archetype sprite pictograms
     */
    public function testRenderSpritesDefaultContextOmitsInlineModifier(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['lugia']);

        $html = $this->runtime->renderSprites($archetype);

        self::assertStringContainsString('class="archetype-sprites"', $html);
        self::assertStringNotContainsString('archetype-sprites--inline', $html);
    }

    /**
     * @see docs/features.md F2.12 — Archetype sprite pictograms
     */
    public function testRenderSpritesInlineContextAddsModifier(): void
    {
        $archetype = new Archetype();
        $archetype->setPokemonSlugs(['lugia']);

        $html = $this->runtime->renderSprites($archetype, 'inline');

        self::assertStringContainsString('class="archetype-sprites archetype-sprites--inline"', $html);
    }

    /**
     * @see docs/features.md F2.22 — Custom Pokemon sprites on decks
     */
    public function testRenderDeckSpritesInlineContextAddsModifier(): void
    {
        $deck = new Deck();
        $deck->setPokemonSlugs(['lugia']);

        $html = $this->runtime->renderDeckSprites($deck, 'inline');

        self::assertStringContainsString('class="archetype-sprites archetype-sprites--inline"', $html);
    }
}
