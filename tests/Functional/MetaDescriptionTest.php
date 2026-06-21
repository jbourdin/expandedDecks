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

namespace App\Tests\Functional;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Every indexable public page must emit exactly one <meta name="description">,
 * sourced through the F19.7 fallback chain. noindex pages are exempt.
 *
 * @see docs/features.md F19.7 — Meta descriptions on all indexable pages
 */
class MetaDescriptionTest extends AbstractFunctionalTest
{
    public function testArchetypeCatalogHasListDescription(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes');
        self::assertResponseIsSuccessful();

        self::assertStringContainsString('deck archetypes', strtolower($this->singleMetaDescription($crawler)));
    }

    public function testArchetypeDetailUsesArchetypeDescription(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes');
        $hrefs = array_filter(
            $crawler->filter('a[href*="/en/archetypes/"]')->each(static fn ($link) => (string) $link->attr('href')),
            // Skip the RSS autodiscovery/subscribe link to the feed.
            static fn (string $href): bool => !str_contains($href, 'feed.xml'),
        );
        self::assertNotEmpty($hrefs, 'Catalog should link to at least one archetype detail page.');
        $href = (string) reset($hrefs);

        $detail = $this->client->request('GET', $href);
        self::assertResponseIsSuccessful();

        // Exactly one description tag, and it is non-empty.
        self::assertNotSame('', $this->singleMetaDescription($detail));
    }

    public function testDeckCatalogHasListDescription(): void
    {
        $crawler = $this->client->request('GET', '/deck');
        self::assertResponseIsSuccessful();

        self::assertStringContainsString('library', strtolower($this->singleMetaDescription($crawler)));
    }

    public function testEventListHasListDescription(): void
    {
        $crawler = $this->client->request('GET', '/event');
        self::assertResponseIsSuccessful();

        self::assertNotSame('', $this->singleMetaDescription($crawler));
    }

    public function testHomepageHasDescription(): void
    {
        $crawler = $this->client->request('GET', '/en/');
        self::assertResponseIsSuccessful();

        // Default host resolves to the app channel, which carries no
        // meta_description param, so this exercises the translatable site
        // default (the ultimate fallback in the chain).
        self::assertNotSame('', $this->singleMetaDescription($crawler));
    }

    public function testContentChannelDefaultComesFromChannelParam(): void
    {
        // dowsingmachine-style content channel carries a configured
        // meta_description param. The homepage sets no page-level description,
        // so it falls through to that channel default.
        $home = $this->client->request('GET', '/en/', server: ['HTTP_HOST' => 'expandedtalks.wip']);
        self::assertResponseIsSuccessful();

        $description = strtolower($this->singleMetaDescription($home));
        self::assertStringContainsString('strategy', $description);
    }

    public function testSearchResultsAreExemptAndNoIndexed(): void
    {
        $crawler = $this->client->request('GET', '/en/search?q=test');
        self::assertResponseIsSuccessful();

        // noindex robots tag present, and no description tag at all.
        self::assertGreaterThan(0, $crawler->filter('meta[name="robots"][content="noindex"]')->count());
        self::assertCount(0, $crawler->filter('meta[name="description"]'));
    }

    /**
     * Assert exactly one <meta name="description"> exists and return its content.
     */
    private function singleMetaDescription(Crawler $crawler): string
    {
        $tags = $crawler->filter('meta[name="description"]');
        self::assertCount(1, $tags, 'Expected exactly one <meta name="description"> on the page.');

        return (string) $tags->attr('content');
    }
}
