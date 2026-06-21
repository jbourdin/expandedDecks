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
use App\Entity\Channel;
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\User;
use App\Service\Channel\ChannelContext;
use App\Service\Seo\StructuredDataBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F18.27 — JSON-LD structured data
 */
final class StructuredDataBuilderTest extends TestCase
{
    public function testBuildWebSiteUsesChannelBrandName(): void
    {
        $builder = $this->createBuilder('Expanded Talks');

        $data = $builder->buildWebSite('https://expandedtalks.wip/');

        self::assertSame('https://schema.org', $data['@context']);
        self::assertSame('WebSite', $data['@type']);
        self::assertSame('Expanded Talks', $data['name']);
        self::assertSame('https://expandedtalks.wip/', $data['url']);
    }

    public function testBuildWebPageIncludesPublisherAndDate(): void
    {
        $builder = $this->createBuilder('Expanded Decks');

        $page = new Page();
        $translation = new PageTranslation();
        $translation->setTitle('About Us');

        $data = $builder->buildWebPage($translation, $page, 'https://expandeddecks.wip/pages/about');

        self::assertSame('WebPage', $data['@type']);
        self::assertSame('About Us', $data['name']);
        self::assertSame('https://expandeddecks.wip/pages/about', $data['url']);
        self::assertArrayHasKey('dateModified', $data);
        self::assertSame('Organization', $data['publisher']['@type']);
        self::assertSame('Expanded Decks', $data['publisher']['name']);
    }

    public function testBuildWebPageEmitsBothDatesWhenPagePublished(): void
    {
        $builder = $this->createBuilder('Expanded Decks');

        $page = new Page();
        $reflection = new \ReflectionClass($page);
        $firstField = $reflection->getProperty('firstPublishedAt');
        $lastField = $reflection->getProperty('lastPublishedAt');
        $publishedOn = new \DateTimeImmutable('2026-01-15T09:00:00+00:00');
        $updatedOn = new \DateTimeImmutable('2026-03-02T15:30:00+00:00');
        $firstField->setValue($page, $publishedOn);
        $lastField->setValue($page, $updatedOn);

        $translation = new PageTranslation();
        $translation->setTitle('Release Notes');

        $data = $builder->buildWebPage($translation, $page, 'https://expandeddecks.wip/pages/release-notes');

        self::assertSame($publishedOn->format('c'), $data['datePublished']);
        self::assertSame($updatedOn->format('c'), $data['dateModified']);
    }

    public function testBuildArticleIncludesHeadlineGenreAndAbout(): void
    {
        $builder = $this->createBuilder('Expanded Talks');

        $archetype = (new Archetype())->setName('Iron Thorns');

        $data = $builder->buildArticle($archetype, 'en', 'https://expandedtalks.wip/archetypes/iron-thorns');

        self::assertSame('Article', $data['@type']);
        self::assertSame('Iron Thorns', $data['name']);
        self::assertSame('Iron Thorns — Pokémon TCG Expanded Deck Archetype', $data['headline']);
        self::assertSame('Pokémon TCG Expanded', $data['genre']);
        self::assertSame('Game', $data['about']['@type']);
        self::assertSame('Pokémon Trading Card Game', $data['about']['name']);
        self::assertArrayHasKey('datePublished', $data);
        self::assertArrayHasKey('dateModified', $data);
        self::assertArrayNotHasKey('hasPart', $data);
    }

    public function testBuildArticleUsesPublicationTimestampsWhenAvailable(): void
    {
        $builder = $this->createBuilder('Expanded Talks');

        $archetype = (new Archetype())->setName('Iron Thorns');
        $reflection = new \ReflectionClass($archetype);
        $firstField = $reflection->getProperty('firstPublishedAt');
        $lastField = $reflection->getProperty('lastPublishedAt');
        $publishedOn = new \DateTimeImmutable('2026-02-01T08:00:00+00:00');
        $updatedOn = new \DateTimeImmutable('2026-04-10T12:00:00+00:00');
        $firstField->setValue($archetype, $publishedOn);
        $lastField->setValue($archetype, $updatedOn);

        $data = $builder->buildArticle($archetype, 'en', 'https://expandedtalks.wip/archetypes/iron-thorns');

        self::assertSame($publishedOn->format('c'), $data['datePublished']);
        self::assertSame($updatedOn->format('c'), $data['dateModified']);
    }

    public function testBuildArticleVariantsHaveGenreAndDescription(): void
    {
        $builder = $this->createBuilder('Expanded Talks');

        $archetype = (new Archetype())->setName('Iron Thorns');
        $variants = [
            ['name' => 'Spread variant', 'url' => 'https://expandedtalks.wip/archetypes/iron-thorns#98QPPD'],
            ['name' => 'Control variant', 'url' => 'https://expandedtalks.wip/archetypes/iron-thorns#MQN93L'],
        ];

        $data = $builder->buildArticle($archetype, 'en', 'https://expandedtalks.wip/archetypes/iron-thorns', $variants);

        self::assertArrayHasKey('hasPart', $data);
        self::assertCount(2, $data['hasPart']);
        self::assertSame('CreativeWork', $data['hasPart'][0]['@type']);
        self::assertSame('Spread variant', $data['hasPart'][0]['name']);
        self::assertSame('Pokémon TCG Expanded', $data['hasPart'][0]['genre']);
        self::assertSame('Deck list variant for the Spread variant archetype', $data['hasPart'][0]['description']);
        self::assertStringContainsString('#98QPPD', $data['hasPart'][0]['url']);
    }

    public function testBuildEventIncludesLocationAndOrganizer(): void
    {
        $builder = $this->createBuilder('Expanded Decks');

        $organizer = (new User())->setScreenName('AshKetchum');
        $event = new Event();
        $event->setOrganizer($organizer);
        $event->setName('June Expanded Cup');
        $event->setDate(new \DateTimeImmutable('2026-06-15T10:00:00+02:00'));
        $event->setLocation('Paris Game Center');

        $data = $builder->buildEvent($event, 'https://expandeddecks.wip/event/42');

        self::assertSame('Event', $data['@type']);
        self::assertSame('June Expanded Cup', $data['name']);
        self::assertSame('https://schema.org/EventScheduled', $data['eventStatus']);
        self::assertSame('AshKetchum', $data['organizer']['name']);
        self::assertSame('Paris Game Center', $data['location']['name']);
    }

    public function testBuildEventMarksAsCancelledWhenApplicable(): void
    {
        $builder = $this->createBuilder('Expanded Decks');

        $organizer = (new User())->setScreenName('AshKetchum');
        $event = new Event();
        $event->setOrganizer($organizer);
        $event->setName('Cancelled Cup');
        $event->setDate(new \DateTimeImmutable('2026-06-15'));
        $event->setCancelledAt(new \DateTimeImmutable());

        $data = $builder->buildEvent($event, 'https://expandeddecks.wip/event/99');

        self::assertSame('https://schema.org/EventCancelled', $data['eventStatus']);
    }

    public function testBuildCreativeWorkIncludesOwner(): void
    {
        $builder = $this->createBuilder('Expanded Decks');

        $owner = (new User())->setScreenName('MistyWater');
        $deck = new Deck();
        $deck->setName('Lugia VSTAR');
        $deck->setOwner($owner);

        $data = $builder->buildCreativeWork($deck, 'https://expandeddecks.wip/deck/AB3K7N');

        self::assertSame('CreativeWork', $data['@type']);
        self::assertSame('Lugia VSTAR', $data['name']);
        self::assertSame('MistyWater', $data['author']['name']);
        self::assertArrayHasKey('dateCreated', $data);
        self::assertArrayHasKey('dateModified', $data);
    }

    public function testBuildCreativeWorkIncludesGenre(): void
    {
        $builder = $this->createBuilder('Expanded Decks');

        $deck = new Deck();
        $deck->setName('Test Deck');

        $data = $builder->buildCreativeWork($deck, 'https://expandeddecks.wip/deck/AB3K7N');

        self::assertSame('Pokémon TCG Expanded', $data['genre']);
    }

    public function testBuildCollectionPageWithItems(): void
    {
        $builder = $this->createBuilder('Expanded Talks');

        $items = [
            ['name' => 'Iron Thorns', 'url' => 'https://expandedtalks.wip/archetypes/iron-thorns'],
            ['name' => 'Lugia VSTAR', 'url' => 'https://expandedtalks.wip/archetypes/lugia-vstar'],
        ];

        $data = $builder->buildCollectionPage('Archetypes', 'https://expandedtalks.wip/archetypes', $items);

        self::assertSame('CollectionPage', $data['@type']);
        self::assertSame('Archetypes', $data['name']);
        self::assertSame('ItemList', $data['mainEntity']['@type']);
        self::assertCount(2, $data['mainEntity']['itemListElement']);
        self::assertSame(1, $data['mainEntity']['itemListElement'][0]['position']);
        self::assertSame('Iron Thorns', $data['mainEntity']['itemListElement'][0]['name']);
        self::assertSame(2, $data['mainEntity']['itemListElement'][1]['position']);
    }

    public function testBuildCollectionPageEmptyList(): void
    {
        $builder = $this->createBuilder('Expanded Decks');

        $data = $builder->buildCollectionPage('Decks', 'https://expandeddecks.wip/deck', []);

        self::assertSame('CollectionPage', $data['@type']);
        self::assertSame([], $data['mainEntity']['itemListElement']);
    }

    public function testOrganizationUrlUsesChannelDomain(): void
    {
        $builder = $this->createBuilder('Expanded Talks', 'expandedtalks.wip');

        $archetype = (new Archetype())->setName('Test');

        $data = $builder->buildArticle($archetype, 'en', 'https://expandedtalks.wip/archetypes/test');

        self::assertSame('https://expandedtalks.wip', $data['publisher']['url']);
    }

    public function testBuildArticleAuthorIsPersonAndNeverLeaksEmailOrLegalName(): void
    {
        $builder = $this->createBuilder('Expanded Talks');

        $author = (new User())
            ->setEmail('private-email@example.test')
            ->setFirstName('Privatefirstname')
            ->setLastName('Privatelastname')
            ->setScreenName('TestAuthor')
            ->setIsPublicAuthor(true)
            ->setCredential('Format specialist')
            ->setSameAs(['https://example.test/profile'])
            ->setPrimaryUrl('https://example.test/profile')
            ->setAvatarUrl('https://example.test/avatar.png');
        $archetype = (new Archetype())->setName('Iron Thorns')->setAuthor($author);

        $data = $builder->buildArticle($archetype, 'en', 'https://expandedtalks.wip/archetypes/iron-thorns');

        self::assertSame('Person', $data['author']['@type']);
        self::assertSame('TestAuthor', $data['author']['name']);
        self::assertSame('https://example.test/profile', $data['author']['url']);
        self::assertSame('Format specialist', $data['author']['description']);
        self::assertContains('https://example.test/profile', $data['author']['sameAs']);

        // The login email and legal name must NEVER appear anywhere in the output.
        $json = json_encode($data, \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('private-email@example.test', $json);
        self::assertStringNotContainsString('Privatefirstname', $json);
        self::assertStringNotContainsString('Privatelastname', $json);
    }

    public function testBuildArticleNonPublicAuthorExposesNameOnly(): void
    {
        $builder = $this->createBuilder('Expanded Talks');

        $author = (new User())
            ->setEmail('owner@example.com')
            ->setScreenName('SomeOwner')
            ->setCredential('hidden')
            ->setSameAs(['https://example.com/x']);
        // isPublicAuthor defaults to false.
        $archetype = (new Archetype())->setName('Iron Thorns')->setAuthor($author);

        $data = $builder->buildArticle($archetype, 'en', 'https://expandedtalks.wip/archetypes/iron-thorns');

        self::assertSame('Person', $data['author']['@type']);
        self::assertSame('SomeOwner', $data['author']['name']);
        self::assertArrayNotHasKey('description', $data['author']);
        self::assertArrayNotHasKey('sameAs', $data['author']);
        self::assertArrayNotHasKey('url', $data['author']);
    }

    public function testBuildArticleAuthorFallsBackToOrganizationWhenUnset(): void
    {
        $builder = $this->createBuilder('Expanded Talks');

        $archetype = (new Archetype())->setName('Iron Thorns');

        $data = $builder->buildArticle($archetype, 'en', 'https://expandedtalks.wip/archetypes/iron-thorns');

        self::assertSame('Organization', $data['author']['@type']);
        self::assertSame('Expanded Talks', $data['author']['name']);
    }

    public function testBuildArticleEmitsTranslatorForLocale(): void
    {
        $builder = $this->createBuilder('Expanded Talks');

        $translator = (new User())->setScreenName('Frodo')->setIsPublicAuthor(true);
        $archetype = (new Archetype())->setName('Iron Thorns');
        $translation = (new ArchetypeTranslation())->setLocale('fr')->setName('Iron Thorns')->setTranslator($translator);
        $archetype->addTranslation($translation);

        $data = $builder->buildArticle($archetype, 'fr', 'https://expandedtalks.wip/fr/archetypes/iron-thorns');

        self::assertArrayHasKey('translator', $data);
        self::assertSame('Person', $data['translator']['@type']);
        self::assertSame('Frodo', $data['translator']['name']);
    }

    public function testOrganizationPublisherIncludesLogoAndSameAs(): void
    {
        $builder = $this->createBuilder('Expanded Talks', 'expandedtalks.wip', [
            'org_logo' => '/images/logo.png',
            'org_same_as' => "https://bsky.app/profile/x\nhttps://github.com/y",
        ]);

        $archetype = (new Archetype())->setName('Iron Thorns');

        $data = $builder->buildArticle($archetype, 'en', 'https://expandedtalks.wip/archetypes/iron-thorns');

        $publisher = $data['publisher'];
        self::assertSame('Organization', $publisher['@type']);
        self::assertSame('ImageObject', $publisher['logo']['@type']);
        self::assertSame('https://expandedtalks.wip/images/logo.png', $publisher['logo']['url']);
        self::assertSame(['https://bsky.app/profile/x', 'https://github.com/y'], $publisher['sameAs']);
    }

    /**
     * @param array<string, string> $extraParams
     */
    private function createBuilder(string $brandName = 'Expanded Decks', string $domain = 'expandeddecks.wip', array $extraParams = []): StructuredDataBuilder
    {
        $channel = (new Channel())
            ->setCode('test')
            ->setDomain($domain)
            ->setParameters(array_merge(['brand_name' => $brandName], $extraParams));

        $request = new Request();
        $request->attributes->set('_channel', $channel);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $channelContext = new ChannelContext($requestStack);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn(new RequestContext(scheme: 'https'));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = []): string => match ($id) {
                'app.seo.tcg_genre' => 'Pokémon TCG Expanded',
                'app.seo.tcg_game_name' => 'Pokémon Trading Card Game',
                'app.seo.archetype_headline' => ($parameters['%name%'] ?? '').' — Pokémon TCG Expanded Deck Archetype',
                'app.seo.variant_description' => 'Deck list variant for the '.($parameters['%name%'] ?? '').' archetype',
                default => $id,
            },
        );

        return new StructuredDataBuilder($channelContext, $urlGenerator, $translator);
    }
}
