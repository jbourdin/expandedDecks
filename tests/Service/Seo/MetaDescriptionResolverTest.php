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
use App\Entity\Event;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Service\MarkdownExcerptGenerator;
use App\Service\Seo\MetaDescriptionResolver;
use App\Service\Seo\OgMetaResolver;
use PHPUnit\Framework\TestCase;

/**
 * Uses the real (stateless) OgMetaResolver and MarkdownExcerptGenerator so the
 * test exercises the genuine resolution chain rather than a mocked one.
 *
 * @see docs/features.md F19.7 — Meta descriptions on all indexable pages
 */
class MetaDescriptionResolverTest extends TestCase
{
    private MetaDescriptionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new MetaDescriptionResolver(new OgMetaResolver(), new MarkdownExcerptGenerator());
    }

    public function testArchetypeOgDescriptionWinsOverBodyExcerpt(): void
    {
        $archetype = $this->archetypeWith(ogDescription: 'Curated OG blurb.', description: 'A long body paragraph.');

        self::assertSame('Curated OG blurb.', $this->resolver->resolveForArchetype($archetype, 'en'));
    }

    public function testArchetypeFallsBackToMetaDescriptionThenExcerpt(): void
    {
        $withMeta = $this->archetypeWith(metaDescription: 'SEO meta line.', description: 'Body paragraph.');
        self::assertSame('SEO meta line.', $this->resolver->resolveForArchetype($withMeta, 'en'));

        $excerptOnly = $this->archetypeWith(description: 'Just the body paragraph here.');
        self::assertSame('Just the body paragraph here.', $this->resolver->resolveForArchetype($excerptOnly, 'en'));
    }

    public function testArchetypeReturnsNullWhenNothingAvailable(): void
    {
        self::assertNull($this->resolver->resolveForArchetype($this->archetypeWith(), 'en'));
    }

    public function testDeckOgDescriptionWinsOverNotesExcerpt(): void
    {
        $deck = (new Deck())->setName('My Deck')->setOgDescription('Deck OG blurb.')->setNotes('Some deck notes here.');

        self::assertSame('Deck OG blurb.', $this->resolver->resolveForDeck($deck, 'en'));
    }

    public function testDeckFallsBackToNotesExcerpt(): void
    {
        $deck = (new Deck())->setName('My Deck')->setNotes('These tournament notes describe the deck.');

        self::assertSame('These tournament notes describe the deck.', $this->resolver->resolveForDeck($deck, 'en'));
    }

    public function testDeckReturnsNullWhenEmpty(): void
    {
        self::assertNull($this->resolver->resolveForDeck((new Deck())->setName('Bare'), 'en'));
    }

    public function testPageOgDescriptionWinsOverContentExcerpt(): void
    {
        $page = $this->pageWith(ogDescription: 'Page OG blurb.', content: '# Heading

Body paragraph.');

        self::assertSame('Page OG blurb.', $this->resolver->resolveForPage($page, 'en'));
    }

    public function testPageFallsBackToContentExcerptSkippingHeadings(): void
    {
        $page = $this->pageWith(content: '# Big Heading

The first real paragraph of content.');

        self::assertSame('The first real paragraph of content.', $this->resolver->resolveForPage($page, 'en'));
    }

    public function testEventUsesDescriptionExcerpt(): void
    {
        $event = (new Event())->setName('League Cup')->setDescription('A monthly Expanded tournament for all levels.');

        self::assertSame('A monthly Expanded tournament for all levels.', $this->resolver->resolveForEvent($event));
    }

    public function testEventReturnsNullWithoutDescription(): void
    {
        self::assertNull($this->resolver->resolveForEvent((new Event())->setName('Bare Event')));
    }

    private function archetypeWith(
        ?string $ogDescription = null,
        ?string $metaDescription = null,
        ?string $description = null,
    ): Archetype {
        $archetype = (new Archetype())->setName('Test Archetype');

        $translation = (new ArchetypeTranslation())
            ->setLocale('en')
            ->setName('Test Archetype');
        $translation->setOgDescription($ogDescription);
        $translation->setMetaDescription($metaDescription);
        $translation->setDescription($description);

        $archetype->addTranslation($translation);

        return $archetype;
    }

    private function pageWith(?string $ogDescription = null, string $content = ''): Page
    {
        $page = new Page();

        $translation = (new PageTranslation())
            ->setLocale('en')
            ->setTitle('Test Page')
            ->setContent($content);
        $translation->setOgDescription($ogDescription);

        $page->addTranslation($translation);

        return $page;
    }
}
