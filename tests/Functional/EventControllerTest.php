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
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F3.1 — Create a new event
 * @see docs/features.md F3.9 — Edit an event
 */
class EventControllerTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // F3.1 — Create an event
    // ---------------------------------------------------------------

    public function testCreateEventPersistsEntity(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event/new');
        $this->client->submitForm('Create Event', [
            'event_form[name]' => 'Test Tournament',
            'event_form[date]' => '2026-06-15T14:00',
            'event_form[timezone]' => 'Europe/Paris',
            'event_form[registrationLink]' => 'https://example.com/register',
            'event_form[location]' => 'Paris Game Store',
            'event_form[description]' => 'A friendly tournament',
            'event_form[tournamentStructure]' => 'swiss',
            'event_form[minAttendees]' => '4',
            'event_form[maxAttendees]' => '32',
            'event_form[roundDuration]' => '50',
            'event_form[entryFeeAmount]' => '500',
            'event_form[entryFeeCurrency]' => 'EUR',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy(['name' => 'Test Tournament']);

        self::assertNotNull($event);
        self::assertSame('Expanded', $event->getFormat());
        self::assertSame('Europe/Paris', $event->getTimezone());
        self::assertSame('https://example.com/register', $event->getRegistrationLink());
        self::assertSame('Paris Game Store', $event->getLocation());
        self::assertSame('A friendly tournament', $event->getDescription());
        self::assertSame(4, $event->getMinAttendees());
        self::assertSame(32, $event->getMaxAttendees());
        self::assertSame(50, $event->getRoundDuration());
        self::assertSame(500, $event->getEntryFeeAmount());
        self::assertSame('EUR', $event->getEntryFeeCurrency());
        self::assertNull($event->getCancelledAt());
    }

    public function testCreateEventRedirectsToShowPage(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event/new');
        $this->client->submitForm('Create Event', [
            'event_form[name]' => 'Redirect Test Event',
            'event_form[date]' => '2026-07-01T10:00',
            'event_form[timezone]' => 'UTC',
            'event_form[registrationLink]' => 'https://example.com/redirect-test',
        ]);

        self::assertResponseRedirects();

        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h5', 'Redirect Test Event');
    }

    public function testCreateEventSetsCurrentUserAsOrganizer(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event/new');
        $this->client->submitForm('Create Event', [
            'event_form[name]' => 'Organizer Check Event',
            'event_form[date]' => '2026-08-01T10:00',
            'event_form[timezone]' => 'UTC',
            'event_form[registrationLink]' => 'https://example.com/org-check',
        ]);

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy(['name' => 'Organizer Check Event']);

        self::assertNotNull($event);
        self::assertSame('admin@example.com', $event->getOrganizer()->getEmail());
    }

    public function testCreateEventShowsSuccessFlash(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event/new');
        $this->client->submitForm('Create Event', [
            'event_form[name]' => 'Flash Test Event',
            'event_form[date]' => '2026-09-01T10:00',
            'event_form[timezone]' => 'UTC',
            'event_form[registrationLink]' => 'https://example.com/flash',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Event "Flash Test Event" created.');
    }

    public function testCreateEventValidationRejectsShortName(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event/new');
        $this->client->submitForm('Create Event', [
            'event_form[name]' => 'Ab',
            'event_form[date]' => '2026-06-15T14:00',
            'event_form[timezone]' => 'Europe/Paris',
            'event_form[registrationLink]' => 'https://example.com/register',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertSelectorExists('.invalid-feedback');
    }

    public function testCreateEventWithOptionalFieldsEmpty(): void
    {
        $this->loginAs('admin@example.com');

        $this->client->request('GET', '/event/new');
        $this->client->submitForm('Create Event', [
            'event_form[name]' => 'Minimal Event',
            'event_form[date]' => '2026-06-15T14:00',
            'event_form[timezone]' => 'UTC',
            'event_form[registrationLink]' => 'https://example.com/minimal',
        ]);

        self::assertResponseRedirects();

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy(['name' => 'Minimal Event']);

        self::assertNotNull($event);
        self::assertNull($event->getLocation());
        self::assertNull($event->getDescription());
        self::assertNull($event->getEndDate());
        self::assertNull($event->getMinAttendees());
        self::assertNull($event->getMaxAttendees());
        self::assertNull($event->getRoundDuration());
        self::assertNull($event->getTopCutRoundDuration());
        self::assertNull($event->getEntryFeeAmount());
        self::assertNull($event->getEntryFeeCurrency());
        self::assertNull($event->getTournamentStructure());
        self::assertFalse($event->isDecklistMandatory());
    }

    // ---------------------------------------------------------------
    // F3.9 — Edit an event
    // ---------------------------------------------------------------

    public function testEditEventUpdatesEntity(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d/edit', $event->getId()));
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save Changes', [
            'event_form[name]' => 'Updated Event Name',
            'event_form[location]' => 'New Location',
            'event_form[description]' => 'Updated description',
            'event_form[roundDuration]' => '60',
        ]);

        self::assertResponseRedirects();

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $updated = $repo->find($event->getId());

        self::assertNotNull($updated);
        self::assertSame('Updated Event Name', $updated->getName());
        self::assertSame('New Location', $updated->getLocation());
        self::assertSame('Updated description', $updated->getDescription());
        self::assertSame(60, $updated->getRoundDuration());
    }

    public function testEditEventRedirectsToShowWithFlash(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d/edit', $event->getId()));
        $this->client->submitForm('Save Changes', [
            'event_form[name]' => 'Flash Edit Event',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-success', 'Event "Flash Edit Event" updated.');
        self::assertSelectorTextContains('h5', 'Flash Edit Event');
    }

    public function testEditEventFormIsPreFilled(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d/edit', $event->getId()));
        self::assertResponseIsSuccessful();

        $nameInput = $crawler->filter('#event_form_name');
        self::assertSame($event->getName(), $nameInput->attr('value'));

        $locationInput = $crawler->filter('#event_form_location');
        self::assertSame($event->getLocation(), $locationInput->attr('value'));
    }

    public function testEditEventValidationRejectsShortName(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d/edit', $event->getId()));
        $this->client->submitForm('Save Changes', [
            'event_form[name]' => 'Ab',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertSelectorExists('.invalid-feedback');
    }

    public function testEditEventDeniedForNonOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('borrower@example.com');

        $this->client->request('GET', \sprintf('/event/%d/edit', $event->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testEditCancelledEventRedirectsWithWarning(): void
    {
        $event = $this->getFixtureEvent();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $event->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', \sprintf('/event/%d/edit', $event->getId()));

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'A cancelled event cannot be edited.');
    }

    public function testEditEventDoesNotChangeOrganizer(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d/edit', $event->getId()));
        $this->client->submitForm('Save Changes', [
            'event_form[name]' => 'Organizer Preserved Event',
        ]);

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $updated = $repo->find($event->getId());

        self::assertNotNull($updated);
        self::assertSame('admin@example.com', $updated->getOrganizer()->getEmail());
    }

    public function testEditEventShowButtonVisibleForOrganizer(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('a.btn-outline-light');
        $editLink = $crawler->filter('a.btn-outline-light')->first();
        self::assertStringContainsString('/edit', $editLink->attr('href') ?? '');
    }

    public function testEditEventShowButtonHiddenForNonOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('borrower@example.com');

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('a.btn-outline-light');
    }

    public function testEditEventShowButtonHiddenWhenCancelled(): void
    {
        $event = $this->getFixtureEvent();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $event->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('a.btn-outline-light');
        self::assertSelectorTextContains('.badge.bg-danger', 'Cancelled');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function getFixtureEvent(): Event
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy([]);
        self::assertNotNull($event);

        return $event;
    }
}
