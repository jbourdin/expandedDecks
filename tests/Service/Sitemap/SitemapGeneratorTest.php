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

namespace App\Tests\Service\Sitemap;

use App\Entity\Channel;
use App\Repository\ArchetypeRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\PageRepository;
use App\Service\Sitemap\SitemapGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * @see docs/features.md F18.23 — Dynamic sitemap generation
 */
final class SitemapGeneratorTest extends TestCase
{
    private Channel $appChannel;
    private Channel $contentChannel;

    protected function setUp(): void
    {
        $this->appChannel = (new Channel())
            ->setCode('app')
            ->setDomain('expandeddecks.wip')
            ->setEnableDecks(true)
            ->setEnableRegister(true)
            ->setEnableEvents(true)
            ->setEnableBorrows(true)
            ->setEnableArchetypes(false)
            ->setLocales(['en', 'fr']);

        $this->contentChannel = (new Channel())
            ->setCode('content')
            ->setDomain('expandedtalks.wip')
            ->setEnableDecks(false)
            ->setEnableRegister(false)
            ->setEnableEvents(false)
            ->setEnableBorrows(false)
            ->setEnableArchetypes(true)
            ->setLocales(['en', 'fr']);
    }

    public function testAvailableSectionsForAppChannel(): void
    {
        $generator = $this->createGenerator();

        $sections = $generator->getAvailableSections($this->appChannel);

        self::assertSame(['pages', 'decks', 'events'], $sections);
    }

    public function testAvailableSectionsForContentChannel(): void
    {
        $generator = $this->createGenerator();

        $sections = $generator->getAvailableSections($this->contentChannel);

        self::assertSame(['pages', 'archetypes'], $sections);
    }

    public function testGenerateCombinedIncludesHomepageAndPages(): void
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findPublishedForSitemap')->willReturn([
            ['slug' => 'about', 'updatedAt' => new \DateTimeImmutable('2026-04-01'), 'createdAt' => new \DateTimeImmutable('2026-01-01')],
        ]);

        $generator = $this->createGenerator(pageRepository: $pageRepository);

        $xml = $generator->generateCombined($this->contentChannel);

        self::assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        self::assertStringContainsString('https://expandedtalks.wip/en/', $xml);
        self::assertStringContainsString('https://expandedtalks.wip/fr/', $xml);
        self::assertStringContainsString('https://expandedtalks.wip/en/pages/about', $xml);
        self::assertStringContainsString('https://expandedtalks.wip/fr/pages/about', $xml);
        self::assertStringContainsString('<lastmod>2026-04-01</lastmod>', $xml);
        self::assertStringContainsString('<priority>1.0</priority>', $xml);
        self::assertStringContainsString('<priority>0.6</priority>', $xml);
    }

    public function testGenerateCombinedIncludesArchetypesOnContentChannel(): void
    {
        $archetypeRepository = $this->createStub(ArchetypeRepository::class);
        $archetypeRepository->method('findPublishedForSitemap')->willReturn([
            ['slug' => 'lugia-vstar', 'updatedAt' => null, 'createdAt' => new \DateTimeImmutable('2026-03-15')],
        ]);

        $generator = $this->createGenerator(archetypeRepository: $archetypeRepository);

        $xml = $generator->generateCombined($this->contentChannel);

        self::assertStringContainsString('https://expandedtalks.wip/en/archetypes/lugia-vstar', $xml);
        self::assertStringContainsString('https://expandedtalks.wip/fr/archetypes/lugia-vstar', $xml);
        self::assertStringContainsString('<lastmod>2026-03-15</lastmod>', $xml);
        self::assertStringContainsString('<changefreq>weekly</changefreq>', $xml);
        self::assertStringContainsString('<priority>0.8</priority>', $xml);
    }

    public function testGenerateCombinedExcludesArchetypesOnAppChannel(): void
    {
        $archetypeRepository = $this->createStub(ArchetypeRepository::class);
        $archetypeRepository->method('findPublishedForSitemap')->willReturn([
            ['slug' => 'lugia-vstar', 'updatedAt' => null, 'createdAt' => new \DateTimeImmutable('2026-03-15')],
        ]);

        $generator = $this->createGenerator(archetypeRepository: $archetypeRepository);

        $xml = $generator->generateCombined($this->appChannel);

        self::assertStringNotContainsString('archetypes/', $xml);
    }

    public function testGenerateCombinedIncludesDecksOnAppChannel(): void
    {
        $deckRepository = $this->createStub(DeckRepository::class);
        $deckRepository->method('findPublicForSitemap')->willReturn([
            ['shortTag' => 'AB3K7N', 'updatedAt' => new \DateTimeImmutable('2026-04-10'), 'createdAt' => new \DateTimeImmutable('2026-02-01')],
        ]);

        $generator = $this->createGenerator(deckRepository: $deckRepository);

        $xml = $generator->generateCombined($this->appChannel);

        self::assertStringContainsString('https://expandeddecks.wip/deck/AB3K7N', $xml);
        self::assertStringContainsString('<lastmod>2026-04-10</lastmod>', $xml);
        self::assertStringContainsString('<priority>0.5</priority>', $xml);
    }

    public function testGenerateCombinedIncludesEventsOnAppChannel(): void
    {
        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('findPublicForSitemap')->willReturn([
            ['id' => 42, 'date' => new \DateTimeImmutable('2026-05-01')],
        ]);

        $generator = $this->createGenerator(eventRepository: $eventRepository);

        $xml = $generator->generateCombined($this->appChannel);

        self::assertStringContainsString('https://expandeddecks.wip/event/42', $xml);
        self::assertStringContainsString('<lastmod>2026-05-01</lastmod>', $xml);
    }

    public function testGenerateIndexListsAvailableSections(): void
    {
        $generator = $this->createGenerator();

        $xml = $generator->generateIndex($this->appChannel);

        self::assertStringContainsString('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        self::assertStringContainsString('https://expandeddecks.wip/sitemap-pages.xml', $xml);
        self::assertStringContainsString('https://expandeddecks.wip/sitemap-decks.xml', $xml);
        self::assertStringContainsString('https://expandeddecks.wip/sitemap-events.xml', $xml);
        self::assertStringNotContainsString('sitemap-archetypes.xml', $xml);
    }

    public function testGenerateSectionIncludesHomepageEntries(): void
    {
        $generator = $this->createGenerator();

        $xml = $generator->generateSection($this->appChannel, 'pages');

        self::assertStringContainsString('https://expandeddecks.wip/en/', $xml);
        self::assertStringContainsString('https://expandeddecks.wip/fr/', $xml);
        self::assertStringContainsString('<priority>1.0</priority>', $xml);
    }

    public function testNeedsIndexReturnsFalseForSmallDatasets(): void
    {
        $generator = $this->createGenerator();

        self::assertFalse($generator->needsIndex($this->appChannel));
    }

    public function testUrlsAreXmlEscaped(): void
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findPublishedForSitemap')->willReturn([
            ['slug' => 'faq-how-to-play', 'updatedAt' => null, 'createdAt' => new \DateTimeImmutable('2026-01-01')],
        ]);

        $generator = $this->createGenerator(pageRepository: $pageRepository);

        $xml = $generator->generateCombined($this->contentChannel);

        self::assertStringContainsString('faq-how-to-play', $xml);
        // Verify valid XML structure
        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
    }

    public function testGenerateCombinedProducesValidXml(): void
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findPublishedForSitemap')->willReturn([
            ['slug' => 'about', 'updatedAt' => new \DateTimeImmutable('2026-04-01'), 'createdAt' => new \DateTimeImmutable('2026-01-01')],
            ['slug' => 'rules', 'updatedAt' => null, 'createdAt' => new \DateTimeImmutable('2026-02-15')],
        ]);

        $archetypeRepository = $this->createStub(ArchetypeRepository::class);
        $archetypeRepository->method('findPublishedForSitemap')->willReturn([
            ['slug' => 'lugia-vstar', 'updatedAt' => new \DateTimeImmutable('2026-03-01'), 'createdAt' => new \DateTimeImmutable('2025-12-01')],
        ]);

        $generator = $this->createGenerator(
            pageRepository: $pageRepository,
            archetypeRepository: $archetypeRepository,
        );

        $xml = $generator->generateCombined($this->contentChannel);

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
        self::assertSame('urlset', $document->documentElement->localName);

        $urls = $document->getElementsByTagName('url');
        // (homepage + 2 pages + 1 archetype) × 2 locales = 8
        self::assertSame(8, $urls->length);
    }

    public function testSingleLocaleChannelEmitsOnlyThatLocale(): void
    {
        $this->contentChannel->setLocales(['en']);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findPublishedForSitemap')->willReturn([
            ['slug' => 'about', 'updatedAt' => new \DateTimeImmutable('2026-04-01'), 'createdAt' => new \DateTimeImmutable('2026-01-01')],
        ]);

        $archetypeRepository = $this->createStub(ArchetypeRepository::class);
        $archetypeRepository->method('findPublishedForSitemap')->willReturn([
            ['slug' => 'lugia-vstar', 'updatedAt' => null, 'createdAt' => new \DateTimeImmutable('2026-03-15')],
        ]);

        $generator = $this->createGenerator(
            pageRepository: $pageRepository,
            archetypeRepository: $archetypeRepository,
        );

        $xml = $generator->generateCombined($this->contentChannel);

        self::assertStringContainsString('https://expandedtalks.wip/en/', $xml);
        self::assertStringContainsString('https://expandedtalks.wip/en/pages/about', $xml);
        self::assertStringContainsString('https://expandedtalks.wip/en/archetypes/lugia-vstar', $xml);
        self::assertStringNotContainsString('/fr/', $xml);

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
        // (homepage + 1 page + 1 archetype) × 1 locale = 3
        self::assertSame(3, $document->getElementsByTagName('url')->length);
    }

    public function testGenerateIndexProducesValidXml(): void
    {
        $generator = $this->createGenerator();

        $xml = $generator->generateIndex($this->contentChannel);

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
        self::assertSame('sitemapindex', $document->documentElement->localName);

        $sitemaps = $document->getElementsByTagName('sitemap');
        // pages + archetypes = 2 sections
        self::assertSame(2, $sitemaps->length);
    }

    private function createGenerator(
        ?PageRepository $pageRepository = null,
        ?ArchetypeRepository $archetypeRepository = null,
        ?DeckRepository $deckRepository = null,
        ?EventRepository $eventRepository = null,
    ): SitemapGenerator {
        $pageRepository ??= $this->createStub(PageRepository::class);
        $pageRepository->method('findPublishedForSitemap')->willReturn([]);

        $archetypeRepository ??= $this->createStub(ArchetypeRepository::class);
        $archetypeRepository->method('findPublishedForSitemap')->willReturn([]);

        $deckRepository ??= $this->createStub(DeckRepository::class);
        $deckRepository->method('findPublicForSitemap')->willReturn([]);

        $eventRepository ??= $this->createStub(EventRepository::class);
        $eventRepository->method('findPublicForSitemap')->willReturn([]);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn(new RequestContext(scheme: 'https'));
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $routeName, array $parameters = []): string {
                $locale = $parameters['_locale'] ?? null;

                return match ($routeName) {
                    'app_home_localized' => '/'.$locale.'/',
                    'app_page_show' => '/'.$locale.'/pages/'.$parameters['slug'],
                    'app_archetype_show' => '/'.$locale.'/archetypes/'.$parameters['slug'],
                    'app_deck_show' => '/deck/'.$parameters['short_tag'],
                    'app_event_show' => '/event/'.$parameters['id'],
                    'app_sitemap_section' => '/sitemap-'.$parameters['section'].'.xml',
                    default => '/'.$routeName,
                };
            },
        );

        return new SitemapGenerator(
            $pageRepository,
            $archetypeRepository,
            $deckRepository,
            $eventRepository,
            $urlGenerator,
        );
    }
}
