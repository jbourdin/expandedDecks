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

namespace App\Tests\Service\Seo;

use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Entity\Deck;
use App\Entity\DeckVersion;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Service\Seo\OgMetaResolver;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
 * @see docs/features.md F18.31 — Editor-defined OG image and description on Banned & Staple Cards pages
 */
final class OgMetaResolverTest extends TestCase
{
    public function testDeckOwnFieldsWinOverEverything(): void
    {
        $resolver = new OgMetaResolver();
        $deck = $this->buildDeckWithVersion(mosaicUrl: '/uploads/mosaic.png');
        $deck->setOgImage('/uploads/deck-share.png');
        $deck->setOgDescription('Custom deck description.');

        $deck->setArchetype($this->buildArchetypeWithTranslation('en', ogImage: '/uploads/archetype.png', ogDescription: 'Archetype desc'));

        // Deck has an owner-defined OG image and description — those win.
        $deck->setOwner(null); // would be variant…
        $result = $resolver->resolveForDeck($deck, 'en');

        self::assertSame('/uploads/deck-share.png', $result['image']);
        self::assertSame('Custom deck description.', $result['description']);
    }

    public function testVariantDeckFallsBackToArchetypeTranslation(): void
    {
        $resolver = new OgMetaResolver();
        $deck = $this->buildDeckWithVersion(mosaicUrl: '/uploads/mosaic.png');
        // Owner is null and an archetype is set => variant.
        $deck->setOwner(null);
        $deck->setArchetype($this->buildArchetypeWithTranslation('en', ogImage: '/uploads/archetype.png', ogDescription: 'Archetype desc'));

        $result = $resolver->resolveForDeck($deck, 'en');

        self::assertSame('/uploads/archetype.png', $result['image']);
        self::assertSame('Archetype desc', $result['description']);
    }

    public function testVariantDeckFallsThroughToMosaicWhenArchetypeBlank(): void
    {
        $resolver = new OgMetaResolver();
        $deck = $this->buildDeckWithVersion(mosaicUrl: '/uploads/mosaic.png');
        $deck->setOwner(null);
        $deck->setArchetype($this->buildArchetypeWithTranslation('en', ogImage: null, ogDescription: null));

        $result = $resolver->resolveForDeck($deck, 'en');

        // No archetype OG image, no deck OG image → falls back to the deck mosaic.
        self::assertSame('/uploads/mosaic.png', $result['image']);
        // No fallback chain for description — stays null.
        self::assertNull($result['description']);
    }

    public function testNonVariantDeckDoesNotCrossIntoArchetype(): void
    {
        $resolver = new OgMetaResolver();
        $deck = $this->buildDeckWithVersion(mosaicUrl: '/uploads/mosaic.png');
        // An owner makes this a player deck (non-variant), even with an archetype set.
        $deck->setOwner(new \App\Entity\User());
        $deck->setArchetype($this->buildArchetypeWithTranslation('en', ogImage: '/uploads/archetype.png', ogDescription: 'Archetype desc'));

        $result = $resolver->resolveForDeck($deck, 'en');

        // Non-variant: archetype values are NOT used. Falls straight to mosaic.
        self::assertSame('/uploads/mosaic.png', $result['image']);
        self::assertNull($result['description']);
    }

    public function testDeckWithoutCurrentVersionReturnsNullImage(): void
    {
        $resolver = new OgMetaResolver();
        $deck = new Deck();

        $result = $resolver->resolveForDeck($deck, 'en');

        self::assertNull($result['image']);
        self::assertNull($result['description']);
    }

    public function testArchetypeUsesTranslationValues(): void
    {
        $resolver = new OgMetaResolver();
        $archetype = $this->buildArchetypeWithTranslation('fr', ogImage: '/uploads/fr.png', ogDescription: 'Description FR');

        $result = $resolver->resolveForArchetype($archetype, 'fr');

        self::assertSame('/uploads/fr.png', $result['image']);
        self::assertSame('Description FR', $result['description']);
    }

    public function testArchetypeDescriptionFallsBackToMetaDescription(): void
    {
        $resolver = new OgMetaResolver();
        $archetype = $this->buildArchetypeWithTranslation(
            'en',
            ogImage: null,
            ogDescription: null,
            metaDescription: 'SEO meta description',
        );

        $result = $resolver->resolveForArchetype($archetype, 'en');

        self::assertNull($result['image']);
        // OG description blank → falls back to localized meta description.
        self::assertSame('SEO meta description', $result['description']);
    }

    public function testArchetypeWithNoTranslationReturnsNulls(): void
    {
        $resolver = new OgMetaResolver();
        $archetype = new Archetype();

        $result = $resolver->resolveForArchetype($archetype, 'en');

        self::assertNull($result['image']);
        self::assertNull($result['description']);
    }

    public function testArchetypeFallsBackToEnglishTranslation(): void
    {
        $resolver = new OgMetaResolver();
        // Build with EN only; query FR → translation lookup falls back to EN via getTranslation().
        $archetype = $this->buildArchetypeWithTranslation('en', ogImage: '/uploads/en.png', ogDescription: 'EN desc');

        $result = $resolver->resolveForArchetype($archetype, 'fr');

        self::assertSame('/uploads/en.png', $result['image']);
        self::assertSame('EN desc', $result['description']);
    }

    public function testPageTranslationOverridesParentImage(): void
    {
        $resolver = new OgMetaResolver();
        $page = $this->buildPageWithTranslation('en', ogImage: '/uploads/page-en.png', ogDescription: 'EN page desc');
        $page->setOgImage('/uploads/page-parent.png');

        $result = $resolver->resolveForPage($page, 'en');

        // Per-locale image wins over the parent default.
        self::assertSame('/uploads/page-en.png', $result['image']);
        self::assertSame('EN page desc', $result['description']);
    }

    public function testPageFallsBackToParentImageWhenTranslationBlank(): void
    {
        $resolver = new OgMetaResolver();
        $page = $this->buildPageWithTranslation('en', ogImage: null, ogDescription: null);
        $page->setOgImage('/uploads/page-parent.png');

        $result = $resolver->resolveForPage($page, 'en');

        self::assertSame('/uploads/page-parent.png', $result['image']);
        self::assertNull($result['description']);
    }

    public function testPageWithNoTranslationAndNoParentImage(): void
    {
        $resolver = new OgMetaResolver();
        $page = new Page();

        $result = $resolver->resolveForPage($page, 'en');

        self::assertNull($result['image']);
        self::assertNull($result['description']);
    }

    public function testPageFrenchFallsBackToEnglishTranslation(): void
    {
        $resolver = new OgMetaResolver();
        $page = $this->buildPageWithTranslation('en', ogImage: '/uploads/en.png', ogDescription: 'EN desc');

        $result = $resolver->resolveForPage($page, 'fr');

        // PageTranslation.getTranslation() falls back to EN; the resolver inherits that.
        self::assertSame('/uploads/en.png', $result['image']);
        self::assertSame('EN desc', $result['description']);
    }

    private function buildDeckWithVersion(?string $mosaicUrl): Deck
    {
        $deck = new Deck();

        if (null !== $mosaicUrl) {
            $version = new DeckVersion();
            $version->setMosaicImageUrl($mosaicUrl);
            $deck->setCurrentVersion($version);
        }

        return $deck;
    }

    private function buildArchetypeWithTranslation(
        string $locale,
        ?string $ogImage,
        ?string $ogDescription,
        ?string $metaDescription = null,
    ): Archetype {
        $archetype = new Archetype();
        $translation = new ArchetypeTranslation();
        $translation->setLocale($locale);
        $translation->setName('Some Archetype');
        $translation->setOgImage($ogImage);
        $translation->setOgDescription($ogDescription);
        $translation->setMetaDescription($metaDescription);
        $archetype->addTranslation($translation);

        return $archetype;
    }

    private function buildPageWithTranslation(
        string $locale,
        ?string $ogImage,
        ?string $ogDescription,
    ): Page {
        $page = new Page();
        $translation = new PageTranslation();
        $translation->setLocale($locale);
        $translation->setTitle('Some Page');
        $translation->setContent('content');
        $translation->setOgImage($ogImage);
        $translation->setOgDescription($ogDescription);
        $page->addTranslation($translation);

        return $page;
    }
}
