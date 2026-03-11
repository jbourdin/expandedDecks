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
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Renders Pokemon box sprite images for an archetype's pokemonSlugs.
 *
 * @see docs/features.md F2.12 — Archetype sprite pictograms
 */
class ArchetypeSpriteRuntime implements RuntimeExtensionInterface
{
    /**
     * Renders inline sprite images for the given archetype.
     */
    public function renderSprites(Archetype $archetype): string
    {
        $slugs = $archetype->getPokemonSlugs();

        if ([] === $slugs) {
            return '';
        }

        $images = '';
        foreach ($slugs as $slug) {
            $escapedSlug = htmlspecialchars($slug, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $name = htmlspecialchars(ucwords(str_replace('-', ' ', $slug)), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $images .= \sprintf(
                '<img src="/build/sprites/pokemon/%s.png" alt="%s" title="%s" class="archetype-sprite" loading="lazy">',
                $escapedSlug,
                $name,
                $name,
            );
        }

        return \sprintf('<span class="archetype-sprites">%s</span>', $images);
    }
}
