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
use App\Repository\EventEngagementRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F3.1 — Create a new event
 * @see docs/features.md F3.4 — Register participation to an event
 * @see docs/features.md F3.9 — Edit an event
 * @see docs/features.md F3.10 — Cancel an event
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

    public function testEditEventDeniedForOtherOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('organizer@example.com');

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
    // F3.10 — Cancel an event
    // ---------------------------------------------------------------

    public function testCancelEventSucceeds(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        self::assertNull($event->getCancelledAt());

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $this->client->submitForm('Cancel Event');

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', \sprintf('Event "%s" has been cancelled.', $event->getName()));
        self::assertSelectorTextContains('.badge.bg-danger', 'Cancelled');

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $updated = $repo->find($event->getId());

        self::assertNotNull($updated);
        self::assertNotNull($updated->getCancelledAt());
    }

    public function testCancelEventDeniedForNonOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('borrower@example.com');

        $this->client->request('POST', \sprintf('/event/%d/cancel', $event->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testCancelEventDeniedForOtherOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('organizer@example.com');

        $this->client->request('POST', \sprintf('/event/%d/cancel', $event->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testCancelEventInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/cancel', $event->getId()), [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $updated = $repo->find($event->getId());

        self::assertNotNull($updated);
        self::assertNull($updated->getCancelledAt());
    }

    public function testCancelAlreadyCancelledEventShowsWarning(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Cancel once via the UI
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $cancelForm = $crawler->selectButton('Cancel Event')->form();
        $csrfToken = $cancelForm->get('_token')->getValue();
        self::assertNotEmpty($csrfToken);
        $this->client->submitForm('Cancel Event');
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'has been cancelled');

        // Try to cancel again with the same CSRF token
        $this->client->request('POST', \sprintf('/event/%d/cancel', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'This event is already cancelled.');
    }

    public function testCancelButtonVisibleForOrganizer(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('button.btn-outline-danger');
    }

    public function testCancelButtonHiddenForNonOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('borrower@example.com');

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('button.btn-outline-danger');
    }

    public function testCancelButtonHiddenWhenAlreadyCancelled(): void
    {
        $event = $this->getFixtureEvent();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $event->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('button.btn-outline-danger');
    }

    // ---------------------------------------------------------------
    // F3.4 — Register participation to an event
    // ---------------------------------------------------------------

    public function testParticipateAsPlayer(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'You are now registered as a player.');

        /** @var EventEngagementRepository $repo */
        $repo = static::getContainer()->get(EventEngagementRepository::class);
        $engagement = $repo->findOneBy(['event' => $event->getId()]);
        self::assertNotNull($engagement);
        self::assertSame('registered_playing', $engagement->getState()->value);
        self::assertSame('playing', $engagement->getParticipationMode()?->value);
    }

    public function testParticipateAsSpectator(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Register as Spectator')->form();
        $this->client->submit($form);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'You are now registered as a spectator.');

        /** @var EventEngagementRepository $repo */
        $repo = static::getContainer()->get(EventEngagementRepository::class);
        $engagement = $repo->findOneBy(['event' => $event->getId(), 'user' => $this->getBorrowerUserId()]);
        self::assertNotNull($engagement);
        self::assertSame('registered_spectating', $engagement->getState()->value);
        self::assertSame('spectating', $engagement->getParticipationMode()?->value);
    }

    public function testSwitchParticipationMode(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Register as player first
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();

        // Switch to spectator
        $crawler = $this->client->followRedirect();
        $form = $crawler->selectButton('Switch to Spectator')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'You are now registered as a spectator.');

        /** @var EventEngagementRepository $repo */
        $repo = static::getContainer()->get(EventEngagementRepository::class);
        $engagement = $repo->findOneBy(['event' => $event->getId(), 'user' => $this->getBorrowerUserId()]);
        self::assertNotNull($engagement);
        self::assertSame('registered_spectating', $engagement->getState()->value);
    }

    public function testWithdrawParticipation(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Register first
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();

        // Withdraw
        $crawler = $this->client->followRedirect();
        $form = $crawler->selectButton('Withdraw')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'You have withdrawn from this event.');

        /** @var EventEngagementRepository $repo */
        $repo = static::getContainer()->get(EventEngagementRepository::class);
        $engagement = $repo->findOneBy(['event' => $event->getId()]);
        // The fixture creates an engagement for admin, so check specifically for borrower
        self::assertNull($repo->findOneBy(['event' => $event->getId(), 'user' => $this->getBorrowerUserId()]));
    }

    public function testParticipateCancelledEventDenied(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $eventId = $event->getId();

        // Visit the event page to get a valid CSRF token before cancelling
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $eventId));
        $form = $crawler->selectButton('Register as Player')->form();
        $csrfToken = $form->get('_token')->getValue();

        // Cancel the event in the database (re-fetch via current EM after GET request)
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $em->find(Event::class, $eventId);
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        // Try to participate with the previously obtained token
        $this->client->request('POST', \sprintf('/event/%d/participate', $eventId), [
            '_token' => $csrfToken,
            'mode' => 'playing',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $eventId));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot register for a cancelled event.');
    }

    public function testParticipateShowsOnEventPage(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Register as player
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();

        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('.badge.bg-success', 'Registered (Playing)');
        self::assertSelectorExists('button:contains("Withdraw")');
        self::assertSelectorExists('button:contains("Switch to Spectator")');
    }

    public function testParticipateInvalidCsrfRedirects(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/participate', $event->getId()), [
            '_token' => 'invalid-token',
            'mode' => 'playing',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testParticipateInvalidModeRedirects(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Get a valid CSRF token from the page
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Register as Player')->form();
        $csrfToken = $form->get('_token')->getValue();

        $this->client->request('POST', \sprintf('/event/%d/participate', $event->getId()), [
            '_token' => $csrfToken,
            'mode' => 'invalid_mode',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid participation mode.');
    }

    public function testWithdrawInvalidCsrfRedirects(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/withdraw', $event->getId()), [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testWithdrawCancelledEventDenied(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Get a valid CSRF token from the withdraw form (register first)
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        $withdrawForm = $crawler->selectButton('Withdraw')->form();
        $csrfToken = $withdrawForm->get('_token')->getValue();

        // Cancel the event
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $em->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        // Try to withdraw
        $this->client->request('POST', \sprintf('/event/%d/withdraw', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot withdraw from a cancelled event.');
    }

    public function testWithdrawWithoutEngagementSucceeds(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Register then withdraw to get a withdraw CSRF token
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        // Withdraw once
        $withdrawForm = $crawler->selectButton('Withdraw')->form();
        $this->client->submit($withdrawForm);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'You have withdrawn from this event.');

        // Now get a fresh withdraw CSRF token and POST withdraw again (no engagement exists)
        $registerForm = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($registerForm);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        $withdrawForm = $crawler->selectButton('Withdraw')->form();
        $csrfToken = $withdrawForm->get('_token')->getValue();

        // Remove engagement directly so we test the no-engagement code path
        /** @var EventEngagementRepository $repo */
        $repo = static::getContainer()->get(EventEngagementRepository::class);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $engagement = $repo->findOneBy(['event' => $event->getId(), 'user' => $this->getBorrowerUserId()]);
        self::assertNotNull($engagement);
        $em->remove($engagement);
        $em->flush();

        // POST withdraw with valid token but no engagement
        $this->client->request('POST', \sprintf('/event/%d/withdraw', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'You have withdrawn from this event.');
    }

    public function testCancelledEventHidesParticipationControls(): void
    {
        $event = $this->getFixtureEvent();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $event->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $this->loginAs('borrower@example.com');

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('button:contains("Register as Player")');
        self::assertSelectorNotExists('button:contains("Register as Spectator")');
    }

    public function testEventShowDisplaysParticipantCounts(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Fixture has admin registered as playing
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // Should show "1 player, 0 spectators"
        self::assertSelectorExists('.card-body:contains("1 player")');
        self::assertSelectorExists('.card-body:contains("0 spectators")');
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

    private function getBorrowerUserId(): int
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'borrower@example.com']);
        self::assertNotNull($user);

        return (int) $user->getId();
    }
}
