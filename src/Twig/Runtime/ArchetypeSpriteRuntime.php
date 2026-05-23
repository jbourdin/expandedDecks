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

namespace App\Twig\Runtime;

use App\Entity\Archetype;
use App\Entity\Deck;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Renders Pokemon box sprite images for an archetype's or deck's pokemonSlugs.
 *
 * The optional `$context` argument controls rendering size:
 * - `'block'` (default): fixed pixel height for cards, table rows, page headers.
 * - `'inline'`: em-relative height for sprites inside prose (RTE-rendered archetype
 *   descriptions), so the line doesn't stretch around the image.
 *
 * @see docs/features.md F2.12 — Archetype sprite pictograms
 * @see docs/features.md F2.22 — Custom Pokemon sprites on decks
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class ArchetypeSpriteRuntime implements RuntimeExtensionInterface
{
    /**
     * Renders inline sprite images for the given archetype.
     *
     * @param 'block'|'inline' $context
     */
    public function renderSprites(Archetype $archetype, string $context = 'block'): string
    {
        return $this->renderSlugs($archetype->getPokemonSlugs(), $context);
    }

    /**
     * Renders inline sprite images for the given deck (deck-level first, archetype fallback).
     *
     * @param 'block'|'inline' $context
     *
     * @see docs/features.md F2.22 — Custom Pokemon sprites on decks
     */
    public function renderDeckSprites(Deck $deck, string $context = 'block'): string
    {
        return $this->renderSlugs($deck->getEffectivePokemonSlugs(), $context);
    }

    /**
     * @param list<string>     $slugs
     * @param 'block'|'inline' $context
     */
    private function renderSlugs(array $slugs, string $context = 'block'): string
    {
        if ([] === $slugs) {
            return '';
        }

        $images = '';
        foreach ($slugs as $slug) {
            $escapedSlug = htmlspecialchars($slug, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $name = htmlspecialchars(ucwords(str_replace('-', ' ', $slug)), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $images .= \sprintf(
                '<img src="/sprites/pokemon/%s.png" alt="%s" title="%s" class="archetype-sprite" loading="lazy">',
                $escapedSlug,
                $name,
                $name,
            );
        }

        $wrapperClass = 'inline' === $context ? 'archetype-sprites archetype-sprites--inline' : 'archetype-sprites';

        return \sprintf('<span class="%s">%s</span> ', $wrapperClass, $images);
    }
}
