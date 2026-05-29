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

namespace App\Service\Seo;

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\Page;

/**
 * Resolves the Open Graph image and description for a given entity + locale,
 * applying the fallback chain documented in the feature spec.
 *
 * Stateless; no dependencies injected — pure transformations over the
 * passed entity. The resolver returns a `['image' => ?string, 'description' => ?string]`
 * shape that controllers spread into their template context.
 *
 * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
 * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
 */
final class OgMetaResolver
{
    /**
     * Variant fallback chain for `Deck`:
     *   image       = deck.ogImage         ?? (variant? archetype.translation(locale).ogImage)         ?? deck.currentVersion.mosaicImageUrl
     *   description = deck.ogDescription   ?? (variant? archetype.translation(locale).ogDescription)   ?? null
     *
     * Variants are deck rows with `owner === null` and `archetype` set
     * (see {@see Deck::isArchetypeVariant()}). Non-variant decks never
     * fall through to the archetype level.
     *
     * @return array{image: string|null, description: string|null}
     *
     * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
     */
    public function resolveForDeck(Deck $deck, string $locale): array
    {
        $image = $deck->getOgImage();
        $description = $deck->getOgDescription();

        if ($deck->isArchetypeVariant()) {
            $archetype = $deck->getArchetype();
            if ($archetype instanceof Archetype) {
                $translation = $archetype->getTranslation($locale);
                $image ??= $translation?->getOgImage();
                $description ??= $translation?->getOgDescription();
            }
        }

        $image ??= $deck->getCurrentVersion()?->getMosaicImageUrl();

        return [
            'image' => $image,
            'description' => $description,
        ];
    }

    /**
     * Locale-scoped lookup with graceful fallback to the existing meta description.
     *
     *   image       = archetype.translation(locale).ogImage
     *   description = archetype.translation(locale).ogDescription ?? archetype.localizedMetaDescription(locale)
     *
     * @return array{image: string|null, description: string|null}
     *
     * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
     */
    public function resolveForArchetype(Archetype $archetype, string $locale): array
    {
        $translation = $archetype->getTranslation($locale);

        return [
            'image' => $translation?->getOgImage(),
            'description' => $translation?->getOgDescription() ?? $archetype->getLocalizedMetaDescription($locale),
        ];
    }

    /**
     * Per-locale override on top of the parent-level Page image:
     *   image       = page.translation(locale).ogImage ?? page.ogImage
     *   description = page.translation(locale).ogDescription
     *
     * @return array{image: string|null, description: string|null}
     *
     * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
     */
    public function resolveForPage(Page $page, string $locale): array
    {
        $translation = $page->getTranslation($locale);

        return [
            'image' => $translation?->getOgImage() ?? $page->getOgImage(),
            'description' => $translation?->getOgDescription(),
        ];
    }
}
