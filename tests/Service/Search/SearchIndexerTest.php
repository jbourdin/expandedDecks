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

namespace App\Tests\Service\Search;

use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Entity\Event;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\User;
use App\Service\Search\SearchIndexer;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F18.1 — Full-text search engine (MeiliSearch sidecar)
 */
class SearchIndexerTest extends TestCase
{
    public function testStripMarkdownRemovesArchetypeTags(): void
    {
        $result = $this->invokeStripMarkdown('Check out [[archetype:kyurem]] for details.');

        self::assertSame('Check out for details.', $result);
    }

    public function testStripMarkdownRemovesDeckTags(): void
    {
        $result = $this->invokeStripMarkdown('See [[deck:ABC123]] deck list.');

        self::assertSame('See deck list.', $result);
    }

    public function testStripMarkdownRemovesCardTags(): void
    {
        $result = $this->invokeStripMarkdown('Uses [[card:swsh9/TG30]] effectively.');

        self::assertSame('Uses effectively.', $result);
    }

    public function testStripMarkdownRemovesLinks(): void
    {
        $result = $this->invokeStripMarkdown('Visit [our site](https://example.com) for more.');

        self::assertSame('Visit our site for more.', $result);
    }

    public function testStripMarkdownRemovesImages(): void
    {
        $result = $this->invokeStripMarkdown('Look at ![card image](https://example.com/image.png) here.');

        self::assertSame('Look at here.', $result);
    }

    public function testStripMarkdownRemovesHeadings(): void
    {
        $result = $this->invokeStripMarkdown("## Strategy\nThe deck focuses on...");

        self::assertSame('Strategy The deck focuses on...', $result);
    }

    public function testStripMarkdownRemovesBoldAndItalic(): void
    {
        $result = $this->invokeStripMarkdown('This is **bold** and *italic* and __underline__.');

        self::assertSame('This is bold and italic and underline.', $result);
    }

    public function testStripMarkdownCollapsesWhitespace(): void
    {
        $result = $this->invokeStripMarkdown("Line one.\n\n\n  Line two.  \n\nLine three.");

        self::assertSame('Line one. Line two. Line three.', $result);
    }

    public function testStripMarkdownHandlesEmptyString(): void
    {
        $result = $this->invokeStripMarkdown('');

        self::assertSame('', $result);
    }

    public function testIndexConstants(): void
    {
        self::assertSame('archetypes', SearchIndexer::INDEX_ARCHETYPES);
        self::assertSame('variants', SearchIndexer::INDEX_VARIANTS);
        self::assertSame('pages', SearchIndexer::INDEX_PAGES);
        self::assertSame('events', SearchIndexer::INDEX_EVENTS);
        self::assertSame('decks', SearchIndexer::INDEX_DECKS);
    }

    // ── Mapper tests (via reflection) ──────────────────────────────────

    public function testMapArchetypeProducesDocumentsPerLocale(): void
    {
        $archetype = new Archetype();
        $archetype->setName('Regidrago');
        $this->setProperty($archetype, 'slug', 'regidrago');

        $translationEn = new ArchetypeTranslation();
        $translationEn->setLocale('en');
        $translationEn->setName('Regidrago');
        $translationEn->setDescription('A **dragon** archetype.');
        $translationEn->setArchetype($archetype);
        $archetype->addTranslation($translationEn);

        $translationFr = new ArchetypeTranslation();
        $translationFr->setLocale('fr');
        $translationFr->setName('Regidrago');
        $translationFr->setDescription('Un archétype **dragon**.');
        $translationFr->setArchetype($archetype);
        $archetype->addTranslation($translationFr);

        /** @var list<array<string, mixed>> $documents */
        $documents = $this->invokeMapper('mapArchetype', $archetype);

        self::assertCount(2, $documents);
        self::assertSame('archetype', $documents[0]['type']);
        self::assertSame('regidrago', $documents[0]['slug']);
        self::assertSame('en', $documents[0]['locale']);
        self::assertSame('fr', $documents[1]['locale']);
        // Bold markers should be stripped
        self::assertStringNotContainsString('**', $documents[0]['description']);
    }

    public function testMapPageProducesDocumentsPerLocale(): void
    {
        $page = new Page();
        $page->setSlug('welcome');

        $translationEn = new PageTranslation();
        $translationEn->setLocale('en');
        $translationEn->setTitle('Welcome');
        $translationEn->setContent('Welcome to the site.');
        $translationEn->setPage($page);
        $page->addTranslation($translationEn);

        /** @var list<array<string, mixed>> $documents */
        $documents = $this->invokeMapper('mapPage', $page);

        self::assertGreaterThanOrEqual(1, \count($documents));
        self::assertSame('page', $documents[0]['type']);
        self::assertSame('welcome', $documents[0]['slug']);
        self::assertSame('Welcome', $documents[0]['title']);
    }

    public function testMapEventProducesSingleDocument(): void
    {
        $event = new Event();
        $event->setName('Paris League');
        $event->setDate(new \DateTimeImmutable('2026-05-01'));
        $event->setLocation('Paris');
        $event->setDescription('Monthly **league** event.');
        $event->setOrganizer(new User());

        /** @var array<string, mixed> $document */
        $document = $this->invokeMapper('mapEvent', $event);

        self::assertSame('event', $document['type']);
        self::assertSame('Paris League', $document['name']);
        self::assertSame('2026-05-01', $document['date']);
        self::assertSame('Paris', $document['location']);
        self::assertStringNotContainsString('**', $document['description']);
    }

    public function testMapDeckProducesSingleDocument(): void
    {
        $deck = new Deck();
        $deck->setName('My Regidrago');

        $archetype = new Archetype();
        $archetype->setName('Regidrago');
        $deck->setArchetype($archetype);

        $owner = new User();
        $owner->setScreenName('Julien');
        $deck->setOwner($owner);

        /** @var array<string, mixed> $document */
        $document = $this->invokeMapper('mapDeck', $deck);

        self::assertSame('deck', $document['type']);
        self::assertSame('My Regidrago', $document['name']);
        self::assertSame('Regidrago', $document['archetypeName']);
        self::assertSame('Julien', $document['ownerName']);
    }

    public function testMapVariantIncludesCardNames(): void
    {
        $variant = new Deck();
        $variant->setName('Turbo Regidrago');

        $archetype = new Archetype();
        $archetype->setName('Regidrago');
        $this->setProperty($archetype, 'slug', 'regidrago');
        $variant->setArchetype($archetype);

        $version = new DeckVersion();
        $card1 = new DeckCard();
        $card1->setCardName('Regidrago VSTAR');
        $card1->setQuantity(2);
        $version->addCard($card1);

        $card2 = new DeckCard();
        $card2->setCardName('Dragonite V');
        $card2->setQuantity(1);
        $version->addCard($card2);

        $variant->setCurrentVersion($version);

        /** @var array<string, mixed> $document */
        $document = $this->invokeMapper('mapVariant', $variant);

        self::assertSame('variant', $document['type']);
        self::assertSame('Turbo Regidrago', $document['name']);
        self::assertSame('regidrago', $document['archetypeSlug']);
        self::assertStringContainsString('Regidrago VSTAR', $document['cardNames']);
        self::assertStringContainsString('Dragonite V', $document['cardNames']);
    }

    public function testMapVariantWithNoVersionHasEmptyCardNames(): void
    {
        $variant = new Deck();
        $variant->setName('Empty Variant');

        $archetype = new Archetype();
        $archetype->setName('Test');
        $this->setProperty($archetype, 'slug', 'test');
        $variant->setArchetype($archetype);

        /** @var array<string, mixed> $document */
        $document = $this->invokeMapper('mapVariant', $variant);

        self::assertSame('', $document['cardNames']);
    }

    private function invokeStripMarkdown(string $markdown): string
    {
        $reflectionMethod = new \ReflectionMethod(SearchIndexer::class, 'stripMarkdown');

        // Create a minimal instance — stripMarkdown is a pure function
        $indexer = (new \ReflectionClass(SearchIndexer::class))->newInstanceWithoutConstructor();

        /** @var string $result */
        $result = $reflectionMethod->invoke($indexer, $markdown);

        return $result;
    }

    private function invokeMapper(string $methodName, object $entity): mixed
    {
        $method = new \ReflectionMethod(SearchIndexer::class, $methodName);
        $indexer = (new \ReflectionClass(SearchIndexer::class))->newInstanceWithoutConstructor();

        return $method->invoke($indexer, $entity);
    }

    private function setProperty(object $entity, string $property, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty($entity, $property);
        $reflectionProperty->setValue($entity, $value);
    }
}
