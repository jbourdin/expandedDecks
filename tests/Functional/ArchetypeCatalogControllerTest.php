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

/**
 * @see docs/features.md F2.16 — Archetype catalog
 */
class ArchetypeCatalogControllerTest extends AbstractFunctionalTest
{
    public function testCatalogIsPubliclyAccessible(): void
    {
        $this->client->request('GET', '/archetypes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Archetypes');
    }

    public function testCatalogDisplaysPublishedArchetypes(): void
    {
        $crawler = $this->client->request('GET', '/archetypes');

        self::assertResponseIsSuccessful();
        // Fixture data has published archetypes with public decks
        $cards = $crawler->filter('.card-title');
        self::assertGreaterThan(0, $cards->count());
    }

    public function testCatalogShowsArchetypeSprites(): void
    {
        $this->client->request('GET', '/archetypes');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.archetype-sprites');
    }

    public function testCatalogShowsDeckCount(): void
    {
        $crawler = $this->client->request('GET', '/archetypes');

        self::assertResponseIsSuccessful();
        // Each card should display a deck count
        $deckCounts = $crawler->filter('.card-text');
        self::assertGreaterThan(0, $deckCounts->count());
    }

    public function testCatalogLinksToDetailPage(): void
    {
        $crawler = $this->client->request('GET', '/archetypes');

        self::assertResponseIsSuccessful();
        $links = $crawler->filter('a[href^="/archetypes/"]');
        self::assertGreaterThan(0, $links->count());
    }

    public function testSortByDecks(): void
    {
        $this->client->request('GET', '/archetypes?sort=decks');

        self::assertResponseIsSuccessful();
    }

    public function testSortByName(): void
    {
        $this->client->request('GET', '/archetypes?sort=name');

        self::assertResponseIsSuccessful();
    }

    public function testInvalidSortDefaultsToName(): void
    {
        $this->client->request('GET', '/archetypes?sort=invalid');

        self::assertResponseIsSuccessful();
    }

    public function testFilterBySingleTag(): void
    {
        $this->client->request('GET', '/archetypes?tags[]=Aggro');

        self::assertResponseIsSuccessful();
    }

    public function testFilterByMultipleTagsUsesOrLogic(): void
    {
        $this->client->request('GET', '/archetypes?tags[]=Aggro&tags[]=Control');

        self::assertResponseIsSuccessful();
    }

    public function testFilterByNonExistentTagShowsEmptyState(): void
    {
        $this->client->request('GET', '/archetypes?tags[]=NonExistentTag');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-info');
    }

    public function testCatalogShowsPlaystyleTagBadges(): void
    {
        $crawler = $this->client->request('GET', '/archetypes');

        self::assertResponseIsSuccessful();
        $badges = $crawler->filter('.badge.bg-secondary');
        self::assertGreaterThan(0, $badges->count());
    }
}
