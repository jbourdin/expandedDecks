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

    public function testBuildArticleIncludesArchetypeFields(): void
    {
        $builder = $this->createBuilder('Expanded Talks');

        $archetype = (new Archetype())->setName('Iron Thorns');

        $data = $builder->buildArticle($archetype, 'en', 'https://expandedtalks.wip/archetypes/iron-thorns');

        self::assertSame('Article', $data['@type']);
        self::assertSame('Iron Thorns', $data['name']);
        self::assertArrayHasKey('datePublished', $data);
        self::assertArrayHasKey('dateModified', $data);
        self::assertArrayNotHasKey('hasPart', $data);
    }

    public function testBuildArticleIncludesVariantsAsHasPart(): void
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

    public function testOrganizationUrlUsesChannelDomain(): void
    {
        $builder = $this->createBuilder('Expanded Talks', 'expandedtalks.wip');

        $archetype = (new Archetype())->setName('Test');

        $data = $builder->buildArticle($archetype, 'en', 'https://expandedtalks.wip/archetypes/test');

        self::assertSame('https://expandedtalks.wip', $data['publisher']['url']);
    }

    private function createBuilder(string $brandName = 'Expanded Decks', string $domain = 'expandeddecks.wip'): StructuredDataBuilder
    {
        $channel = (new Channel())
            ->setCode('test')
            ->setDomain($domain)
            ->setParameters(['brand_name' => $brandName]);

        $request = new Request();
        $request->attributes->set('_channel', $channel);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $channelContext = new ChannelContext($requestStack);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn(new RequestContext(scheme: 'https'));

        return new StructuredDataBuilder($channelContext, $urlGenerator);
    }
}
