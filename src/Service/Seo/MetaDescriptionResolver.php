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
use App\Entity\Event;
use App\Entity\Page;
use App\Service\MarkdownExcerptGenerator;
use App\Twig\Extension\SeoExtension;

/**
 * Resolves the best available `<meta name="description">` for an entity + locale.
 *
 * The chain reuses {@see OgMetaResolver} (explicit field → Open Graph
 * description) and falls back to a trimmed excerpt of the entity's own body
 * copy via {@see MarkdownExcerptGenerator}. Returns null when nothing
 * meaningful exists — callers then fall through to the channel/site default in
 * the base template's `meta_description` block.
 *
 * Distinct from {@see OgMetaResolver} because the fallback differs: a missing
 * OG description should still yield a body-derived meta description rather than
 * nothing, and the result is length-bounded for the description tag.
 *
 * @see docs/features.md F19.7 — Meta descriptions on all indexable pages
 */
final readonly class MetaDescriptionResolver
{
    public function __construct(
        private OgMetaResolver $ogMetaResolver,
        private MarkdownExcerptGenerator $excerptGenerator,
    ) {
    }

    /**
     * archetype.translation(locale).ogDescription
     *   ?? archetype.localizedMetaDescription(locale)
     *   ?? excerpt(archetype.localizedDescription(locale)).
     */
    public function resolveForArchetype(Archetype $archetype, string $locale): ?string
    {
        return $this->ogMetaResolver->resolveForArchetype($archetype, $locale)['description']
            ?? $this->excerptOrNull($archetype->getLocalizedDescription($locale));
    }

    /**
     * deck.ogDescription (with variant fallback to the parent archetype)
     *   ?? excerpt(deck.notes).
     */
    public function resolveForDeck(Deck $deck, string $locale): ?string
    {
        return $this->ogMetaResolver->resolveForDeck($deck, $locale)['description']
            ?? $this->excerptOrNull($deck->getNotes());
    }

    /**
     * page.translation(locale).ogDescription ?? excerpt(page.translation(locale).content).
     */
    public function resolveForPage(Page $page, string $locale): ?string
    {
        return $this->ogMetaResolver->resolveForPage($page, $locale)['description']
            ?? $this->excerptOrNull($page->getTranslation($locale)?->getContent());
    }

    /**
     * excerpt(event.description). Events carry no OG description field; when the
     * description is empty the caller supplies a translated summary instead.
     */
    public function resolveForEvent(Event $event): ?string
    {
        return $this->excerptOrNull($event->getDescription());
    }

    /**
     * Excerpt the first body paragraph, length-bounded for the description tag,
     * or null when the source is empty.
     */
    private function excerptOrNull(?string $markdown): ?string
    {
        if (null === $markdown || '' === trim($markdown)) {
            return null;
        }

        $excerpt = $this->excerptGenerator->excerpt($markdown, SeoExtension::META_DESCRIPTION_MAX_LENGTH);

        return '' === $excerpt ? null : $excerpt;
    }
}
