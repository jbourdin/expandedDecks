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
use Doctrine\ORM\EntityManagerInterface;

/**
 * Covers the event-form ↔ tag pipeline introduced for F3.12.
 *
 * @see docs/features.md F3.12 — Event tags
 */
class EventControllerTagsTest extends AbstractFunctionalTest
{
    public function testCreateEventWithNewAndExistingTagsUpsertsTags(): void
    {
        $em = $this->em();
        $existing = new EventTag();
        $existing->setName('Regional');
        $em->persist($existing);
        $em->flush();

        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/event/new');
        $this->client->submitForm('Create Event', [
            'event_form[name]' => 'Tagged Event',
            'event_form[date]' => '2026-09-15T14:00',
            'event_form[timezone]' => 'Europe/Paris',
            'event_form[registrationLink]' => 'https://example.com/tagged',
            'event_form[tagsInput]' => json_encode(['regional', 'League Cup', '']),
        ]);

        self::assertResponseRedirects();

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        /** @var Event|null $event */
        $event = $repo->findOneBy(['name' => 'Tagged Event']);
        self::assertNotNull($event);

        $tagSlugs = array_map(static fn (EventTag $tag): string => $tag->getSlug(), $event->getTags()->toArray());
        sort($tagSlugs);
        self::assertSame(['league-cup', 'regional'], $tagSlugs);
    }

    public function testEditEventReplacesTagSet(): void
    {
        $em = $this->em();

        $event = new Event();
        $event->setName('Editable Tag Event');
        $event->setDate(new \DateTimeImmutable('+10 days'));
        $event->setTimezone('UTC');
        $event->setOrganizer($this->getAdmin());
        $event->setRegistrationLink('https://example.com/editable');
        $event->setFormat('Expanded');

        $oldTag = new EventTag();
        $oldTag->setName('Old');
        $event->addTag($oldTag);

        $em->persist($oldTag);
        $em->persist($event);
        $em->flush();

        $eventId = $event->getId();
        self::assertNotNull($eventId);

        $this->loginAs('admin@example.com');
        $this->client->request('GET', \sprintf('/event/%d/edit', $eventId));
        $this->client->submitForm('Save', [
            'event_form[tagsInput]' => json_encode(['Brand New']),
        ]);

        self::assertResponseRedirects();

        // Reload from DB to confirm the tag set actually changed.
        $em->clear();
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        /** @var Event $reloaded */
        $reloaded = $repo->find($eventId);

        $names = array_map(static fn (EventTag $tag): string => $tag->getName(), $reloaded->getTags()->toArray());
        self::assertSame(['Brand New'], $names);
    }

    public function testCreateEventWithEmptyTagsInputClearsTags(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/event/new');
        $this->client->submitForm('Create Event', [
            'event_form[name]' => 'No-Tag Event',
            'event_form[date]' => '2026-10-15T14:00',
            'event_form[timezone]' => 'UTC',
            'event_form[registrationLink]' => 'https://example.com/no-tag',
            'event_form[tagsInput]' => '',
        ]);

        self::assertResponseRedirects();

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        /** @var Event|null $event */
        $event = $repo->findOneBy(['name' => 'No-Tag Event']);
        self::assertNotNull($event);
        self::assertCount(0, $event->getTags());
    }

    public function testCreateEventWithMalformedTagsJsonClearsTags(): void
    {
        $this->loginAs('admin@example.com');
        $this->client->request('GET', '/event/new');
        $this->client->submitForm('Create Event', [
            'event_form[name]' => 'Garbled Tags Event',
            'event_form[date]' => '2026-10-20T14:00',
            'event_form[timezone]' => 'UTC',
            'event_form[registrationLink]' => 'https://example.com/garbled',
            'event_form[tagsInput]' => 'not-json',
        ]);

        self::assertResponseRedirects();

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        /** @var Event|null $event */
        $event = $repo->findOneBy(['name' => 'Garbled Tags Event']);
        self::assertNotNull($event);
        self::assertCount(0, $event->getTags());
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    private function getAdmin(): \App\Entity\User
    {
        /** @var \App\Repository\UserRepository $repo */
        $repo = static::getContainer()->get(\App\Repository\UserRepository::class);
        $admin = $repo->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin);

        return $admin;
    }
}
