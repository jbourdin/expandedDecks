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

use App\Entity\Deck;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F2.16 — Archetype catalog
 */
class ArchetypeCatalogControllerTest extends AbstractFunctionalTest
{
    public function testCatalogIsPubliclyAccessible(): void
    {
        $this->client->request('GET', '/en/archetypes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Archetypes');
    }

    public function testCatalogDisplaysPublishedArchetypes(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes');

        self::assertResponseIsSuccessful();
        // Fixture data has published archetypes with public decks
        $cards = $crawler->filter('.card-title');
        self::assertGreaterThan(0, $cards->count());
    }

    public function testCatalogShowsArchetypeSprites(): void
    {
        $this->client->request('GET', '/en/archetypes');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.archetype-sprites');
    }

    public function testCatalogShowsDeckCount(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes');

        self::assertResponseIsSuccessful();
        // Each card should display a deck count
        $deckCounts = $crawler->filter('.card-text');
        self::assertGreaterThan(0, $deckCounts->count());
    }

    public function testCatalogLinksToDetailPage(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes');

        self::assertResponseIsSuccessful();
        $links = $crawler->filter('a[href^="/en/archetypes/"]');
        self::assertGreaterThan(0, $links->count());
    }

    public function testSortByDecks(): void
    {
        $this->client->request('GET', '/en/archetypes?sort=decks');

        self::assertResponseIsSuccessful();
    }

    public function testSortByName(): void
    {
        $this->client->request('GET', '/en/archetypes?sort=name');

        self::assertResponseIsSuccessful();
    }

    /**
     * @see docs/features.md F2.27 — Archetype publication dates
     */
    public function testSortByUpdatedAt(): void
    {
        $this->client->request('GET', '/en/archetypes?sort=updatedAt');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('option[selected]', 'Most recently updated');
    }

    public function testInvalidSortDefaultsToName(): void
    {
        $this->client->request('GET', '/en/archetypes?sort=invalid');

        self::assertResponseIsSuccessful();
    }

    public function testFilterBySingleTag(): void
    {
        $this->client->request('GET', '/en/archetypes?tags[]=Aggro');

        self::assertResponseIsSuccessful();
    }

    /**
     * @see https://github.com/jbourdin/expandedDecks/issues/548
     */
    public function testFilterByMultipleTagsUsesAndLogicByDefault(): void
    {
        // Fixture: only "Iron Thorns ex" carries both Aggressive and Spread.
        $crawler = $this->client->request('GET', '/en/archetypes?tags[]=Aggressive&tags[]=Spread');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.col-lg-4 .card-title'));
    }

    /**
     * @see https://github.com/jbourdin/expandedDecks/issues/548
     */
    public function testFilterByMultipleTagsWithOrModeMatchesAny(): void
    {
        // Fixture: Aggressive ∪ Spread covers Iron Thorns, Ancient Box, Kyurem, Salamence, Charizard Flareon.
        $crawler = $this->client->request('GET', '/en/archetypes?tags[]=Aggressive&tags[]=Spread&tagsMode=or');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(1, $crawler->filter('.col-lg-4 .card-title')->count());
    }

    /**
     * @see https://github.com/jbourdin/expandedDecks/issues/548
     */
    public function testInvalidTagsModeFallsBackToAnd(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes?tags[]=Aggressive&tags[]=Spread&tagsMode=banana');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.col-lg-4 .card-title'));
    }

    /**
     * @see https://github.com/jbourdin/expandedDecks/issues/548
     */
    public function testTagsModeToggleHiddenWhenFewerThanTwoTagsSelected(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes?tags[]=Aggressive');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.btn-group [href*="tagsMode=or"]'));
    }

    /**
     * @see https://github.com/jbourdin/expandedDecks/issues/548
     */
    public function testTagsModeToggleVisibleWithTwoOrMoreTagsSelected(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes?tags[]=Aggressive&tags[]=Spread');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.btn-group [href*="tagsMode=or"]'));
    }

    public function testFilterByNonExistentTagShowsEmptyState(): void
    {
        $this->client->request('GET', '/en/archetypes?tags[]=NonExistentTag');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-info');
    }

    public function testCatalogShowsPlaystyleTagBadges(): void
    {
        $crawler = $this->client->request('GET', '/en/archetypes');

        self::assertResponseIsSuccessful();
        $badges = $crawler->filter('.badge.bg-secondary');
        self::assertGreaterThan(0, $badges->count());
    }

    public function testVariantFeedReturnsValidRss(): void
    {
        $this->client->request('GET', '/en/archetypes/feed.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);
        self::assertSame('en', (string) $xml->channel->language);
        self::assertGreaterThan(0, \count($xml->channel->item));
    }

    public function testVariantFeedItemsLinkToArchetypeWithVariantAnchor(): void
    {
        $this->client->request('GET', '/en/archetypes/feed.xml');

        self::assertResponseIsSuccessful();
        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);

        foreach ($xml->channel->item as $item) {
            // Absolute archetype URL anchored on the variant short tag.
            self::assertMatchesRegularExpression('~^https?://[^/]+/en/archetypes/[a-z0-9-]+#\S+$~', (string) $item->link);
        }
    }

    public function testVariantFeedItemTitlesIncludeArchetypeAndVariantName(): void
    {
        $this->client->request('GET', '/en/archetypes/feed.xml');

        self::assertResponseIsSuccessful();
        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);

        $titles = [];
        foreach ($xml->channel->item as $item) {
            $titles[] = (string) $item->title;
        }

        self::assertContains('Regidrago — Alternate Regidrago', $titles);
    }

    public function testVariantFeedItemsCarryDcCreator(): void
    {
        $this->client->request('GET', '/en/archetypes/feed.xml');

        self::assertResponseIsSuccessful();
        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);
        self::assertGreaterThan(0, \count($xml->channel->item));

        foreach ($xml->channel->item as $item) {
            // Interim hardcoded author until a real content-authoring model exists.
            self::assertSame('Luby', (string) $item->children('http://purl.org/dc/elements/1.1/')->creator);
        }
    }

    public function testVariantFeedEmitsMediaContentWhenOgImageSet(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $variant = $entityManager->getRepository(Deck::class)->findOneBy(['name' => 'Alternate Regidrago']);
        self::assertInstanceOf(Deck::class, $variant);

        $variant->setOgImage('/api/editor/image/00000000-0000-0000-0000-000000000000.png');
        $entityManager->flush();

        $this->client->request('GET', '/en/archetypes/feed.xml');

        self::assertResponseIsSuccessful();
        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);

        $imageUrl = null;
        foreach ($xml->channel->item as $item) {
            if ('Regidrago — Alternate Regidrago' !== (string) $item->title) {
                continue;
            }

            $mediaContent = $item->children('http://search.yahoo.com/mrss/')->content;
            self::assertNotNull($mediaContent);
            $imageUrl = (string) $mediaContent->attributes()['url'];
        }

        self::assertNotNull($imageUrl);
        // channel_absolute_url() turns the relative ogImage path into an absolute URL.
        self::assertMatchesRegularExpression(
            '#^https?://[^/]+/api/editor/image/00000000-0000-0000-0000-000000000000\.png$#',
            $imageUrl,
        );
    }

    public function testVariantFeedOmitsMediaContentWithoutImage(): void
    {
        // Fixtures define no ogImage and no mosaic, so no item carries an image.
        $this->client->request('GET', '/en/archetypes/feed.xml');

        self::assertResponseIsSuccessful();
        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);

        foreach ($xml->channel->item as $item) {
            self::assertCount(0, $item->children('http://search.yahoo.com/mrss/'));
        }
    }
}
