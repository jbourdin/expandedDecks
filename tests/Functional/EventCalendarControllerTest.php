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

use App\Entity\Event;
use App\Entity\EventTag;
use App\Repository\EventRepository;
use App\Repository\EventTagRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F3.16 — Public iCal feed
 */
class EventCalendarControllerTest extends AbstractFunctionalTest
{
    public function testListIcalReturnsCalendarToAnonymous(): void
    {
        $this->client->request('GET', '/event.ics');

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertStringContainsString('text/calendar', (string) $response->headers->get('Content-Type'));
        self::assertStringStartsWith('BEGIN:VCALENDAR', (string) $response->getContent());
        self::assertStringContainsString('PRODID:-//Expanded Decks//Event Calendar//EN', (string) $response->getContent());
    }

    public function testTagIcalReturns404ForUnknownSlug(): void
    {
        $this->client->request('GET', '/event/tag/no-such-tag.ics');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTagIcalReturnsCalendarForExistingTag(): void
    {
        $tag = $this->seedTagAndAttachToFirstPublicEvent('league');

        $this->client->request('GET', \sprintf('/event/tag/%s.ics', $tag->getSlug()));

        self::assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith('BEGIN:VCALENDAR', $body);
        self::assertStringContainsString('NAME:', $body);
    }

    public function testTagListPageRendersWithTaggedEvents(): void
    {
        $tag = $this->seedTagAndAttachToFirstPublicEvent('weekend');

        $this->client->request('GET', \sprintf('/event/tag/%s', $tag->getSlug()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'weekend');
    }

    public function testTagListPageReturns404ForUnknownSlug(): void
    {
        $this->client->request('GET', '/event/tag/no-such-tag');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEventListAcceptsTagFilterQueryParam(): void
    {
        $tag = $this->seedTagAndAttachToFirstPublicEvent('league-cup');

        $this->client->request('GET', \sprintf('/event?tag=%s', $tag->getSlug()));

        self::assertResponseIsSuccessful();
    }

    private function seedTagAndAttachToFirstPublicEvent(string $name): EventTag
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var EventRepository $eventRepo */
        $eventRepo = $container->get(EventRepository::class);
        /** @var EventTagRepository $tagRepo */
        $tagRepo = $container->get(EventTagRepository::class);

        [$tag] = $tagRepo->resolveByNames([$name]);
        $em->persist($tag);

        $events = $eventRepo->findPublicUpcoming(1);
        self::assertNotEmpty($events, 'Fixture must contain at least one public upcoming event.');
        /** @var Event $event */
        $event = $events[0];
        $event->addTag($tag);

        $em->flush();

        return $tag;
    }
}
