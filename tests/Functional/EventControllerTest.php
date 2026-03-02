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

use App\Entity\Borrow;
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Enum\DeckStatus;
use App\Repository\DeckRepository;
use App\Repository\EventDeckEntryRepository;
use App\Repository\EventEngagementRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F3.1 — Create a new event
 * @see docs/features.md F3.4 — Register participation to an event
 * @see docs/features.md F3.7 — Register played deck for event
 * @see docs/features.md F3.9 — Edit an event
 * @see docs/features.md F3.10 — Cancel an event
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
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

        // Check specifically for the "Cancel Event" button, not borrow cancel buttons
        self::assertSelectorNotExists('button:contains("Cancel Event")');
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
        // Use lender (not a participant in fixtures)
        $this->loginAs('lender@example.com');

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
        $engagement = $repo->findOneBy(['event' => $event->getId(), 'user' => $this->getLenderUserId()]);
        self::assertNotNull($engagement);
        self::assertSame('registered_playing', $engagement->getState()->value);
        self::assertSame('playing', $engagement->getParticipationMode()?->value);
    }

    public function testParticipateAsSpectator(): void
    {
        // Use lender (not a participant in fixtures)
        $this->loginAs('lender@example.com');

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
        $engagement = $repo->findOneBy(['event' => $event->getId(), 'user' => $this->getLenderUserId()]);
        self::assertNotNull($engagement);
        self::assertSame('registered_spectating', $engagement->getState()->value);
        self::assertSame('spectating', $engagement->getParticipationMode()?->value);
    }

    public function testSwitchParticipationMode(): void
    {
        // Borrower is already registered as playing in fixtures
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Switch to spectator
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
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
        // Borrower is already registered as playing in fixtures
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Withdraw
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Withdraw')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'You have withdrawn from this event.');

        /** @var EventEngagementRepository $repo */
        $repo = static::getContainer()->get(EventEngagementRepository::class);
        self::assertNull($repo->findOneBy(['event' => $event->getId(), 'user' => $this->getBorrowerUserId()]));
    }

    public function testParticipateCancelledEventDenied(): void
    {
        // Use lender who is not yet a participant
        $this->loginAs('lender@example.com');

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
        // Borrower is already registered as playing in fixtures
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

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
        // Borrower is already registered as playing — use the "Switch to Spectator" form for CSRF token
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Switch to Spectator')->form();
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
        // Borrower is already registered as playing in fixtures
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Get a valid CSRF token from the withdraw form
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
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
        // Borrower is already registered as playing in fixtures
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Get a valid CSRF token from the withdraw form
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
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

        // Fixture has admin + borrower registered as playing
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // Should show "2 players, 0 spectators"
        self::assertSelectorExists('.card-body:contains("2 players")');
        self::assertSelectorExists('.card-body:contains("0 spectators")');
    }

    // ---------------------------------------------------------------
    // Borrow visibility — role-based filtering
    // ---------------------------------------------------------------

    public function testOrganizerSeesAllEventBorrows(): void
    {
        // Admin is the organizer — should see all borrows at the event
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // Fixtures create 2 borrows (Iron Thorns pending + Ancient Box approved)
        $borrowCard = $crawler->filter('h6:contains("Deck Borrowing")')->closest('.card');
        $rows = $borrowCard->filter('tbody tr');
        self::assertGreaterThanOrEqual(2, $rows->count(), 'Organizer should see all borrows.');
    }

    public function testParticipantSeesOnlyOwnBorrows(): void
    {
        // Lender is not a participant and doesn't own any borrowed decks at this event
        // Register lender, then check they don't see other user's borrows
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        // Register as participant first
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);
        $this->client->followRedirect();

        // Now visit the event page — lender has no borrows and doesn't own Iron Thorns/Ancient Box
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // Lender should see no borrow rows in the "other borrows" table (the non-personal one)
        // The Deck Borrowing card may contain a playable decks section with the lender's own deck
        $borrowCard = $crawler->filter('h6:contains("Deck Borrowing")')->closest('.card');
        $otherBorrowHeaders = $borrowCard->filter('th:contains("Borrower")');
        if ($otherBorrowHeaders->count() > 0) {
            $otherTable = $otherBorrowHeaders->closest('table');
            $otherRows = $otherTable->filter('tbody tr');
            self::assertSame(0, $otherRows->count(), 'Non-involved participant should see no other-user borrow rows.');
        }
    }

    public function testStaffSeesAllEventBorrows(): void
    {
        // Borrower is a staff member in fixtures — should see all borrows
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // Should see both borrows (Iron Thorns + Ancient Box)
        $borrowCard = $crawler->filter('h6:contains("Deck Borrowing")')->closest('.card');
        $rows = $borrowCard->filter('tbody tr');
        self::assertGreaterThanOrEqual(2, $rows->count(), 'Staff should see all borrows.');
    }

    // ---------------------------------------------------------------
    // Available decks page
    // ---------------------------------------------------------------

    public function testAvailableDecksPageForParticipant(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));
        self::assertResponseIsSuccessful();

        // Should show available decks with per-deck request forms
        self::assertSelectorTextContains('h4', 'Available Decks');
        self::assertSelectorExists('form[action="/borrow/request"]');
    }

    public function testAvailableDecksPageRedirectsForNonParticipant(): void
    {
        // Lender is not a participant
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Register as a participant to browse and borrow decks.');
    }

    public function testAvailableDecksPageRedirectsForCancelledEvent(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $event->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Decks cannot be browsed for a cancelled or finished event.');
    }

    public function testEventShowHasBrowseDecksButton(): void
    {
        // Borrower is a participant
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        $browseLink = $crawler->filter('a:contains("Browse available decks")');
        self::assertSame(1, $browseLink->count(), 'Participant should see "Browse available decks" button.');
    }

    public function testEventShowNoDropdownFormForParticipant(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // Old dropdown borrow form should no longer exist on event page
        $select = $crawler->filter('select[name="deck_id"]');
        self::assertSame(0, $select->count(), 'Deck dropdown should no longer be on event page.');
    }

    // ---------------------------------------------------------------
    // Dashboard — event management activity
    // ---------------------------------------------------------------

    public function testDashboardShowsEventManagementActivityForOrganizer(): void
    {
        // Admin is the organizer of the event with borrows
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        // Admin has ROLE_ADMIN (includes ROLE_ORGANIZER), so the section should be visible
        $headers = $crawler->filter('.card-header');
        $found = false;
        foreach ($headers as $header) {
            if (str_contains($header->textContent, 'Event Management Activity')) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Dashboard should show "Event Management Activity" section for organizer.');
    }

    // ---------------------------------------------------------------
    // F3.7 — Deck selection
    // ---------------------------------------------------------------

    public function testSelectOwnDeckCreatesEntry(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Playing with "Iron Thorns".');

        /** @var EventDeckEntryRepository $repo */
        $repo = static::getContainer()->get(EventDeckEntryRepository::class);
        $entry = $repo->findOneByEventAndPlayer($event, $this->getUser('admin@example.com'));
        self::assertNotNull($entry);
        self::assertSame($deck->getCurrentVersion()?->getId(), $entry->getDeckVersion()->getId());
    }

    public function testSelectBorrowedDeckCreatesEntry(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFutureEvent();

        // Register borrower as participant on the future event
        $this->registerParticipant($event);

        // Re-fetch entities after browser requests (previous references are detached)
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var Event $event */
        $event = $em->getRepository(Event::class)->findOneBy(['name' => 'Lyon Expanded Cup 2026']);
        self::assertNotNull($event);

        $deck = $this->getAdminDeck('Ancient Box');
        $borrower = $this->getUser('borrower@example.com');
        $admin = $this->getUser('admin@example.com');

        // Create an approved borrow at this event for the borrower
        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($deck->getCurrentVersion());
        $borrow->setBorrower($borrower);
        $borrow->setEvent($event);
        $borrow->setStatus(BorrowStatus::Approved);
        $borrow->setApprovedAt(new \DateTimeImmutable());
        $borrow->setApprovedBy($admin);
        $em->persist($borrow);
        $em->flush();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Playing with "Ancient Box".');

        /** @var EventDeckEntryRepository $repo */
        $repo = static::getContainer()->get(EventDeckEntryRepository::class);
        $freshBorrower = $this->getUser('borrower@example.com');
        $freshEvent = $this->getFutureEvent();
        $entry = $repo->findOneByEventAndPlayer($freshEvent, $freshBorrower);
        self::assertNotNull($entry);
    }

    public function testClearDeckSelection(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        // First select a deck
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Now clear the selection
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => '0',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Deck selection cleared.');

        /** @var EventDeckEntryRepository $repo */
        $repo = static::getContainer()->get(EventDeckEntryRepository::class);
        $entry = $repo->findOneByEventAndPlayer($event, $this->getUser('admin@example.com'));
        self::assertNull($entry);
    }

    public function testChangeDeckSelection(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();
        $ironThorns = $this->getAdminDeck('Iron Thorns');
        $ancientBox = $this->getAdminDeck('Ancient Box');

        // Select Iron Thorns
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $ironThorns->getId(),
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Change to Ancient Box
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $ancientBox->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Playing with "Ancient Box".');

        /** @var EventDeckEntryRepository $repo */
        $repo = static::getContainer()->get(EventDeckEntryRepository::class);
        $entry = $repo->findOneByEventAndPlayer($event, $this->getUser('admin@example.com'));
        self::assertNotNull($entry);
        self::assertSame($ancientBox->getCurrentVersion()?->getId(), $entry->getDeckVersion()->getId());
    }

    public function testSelectDeckAllowedFirstTimeAfterEventStart(): void
    {
        $this->loginAs('admin@example.com');

        // The "Expanded Weekly #42" event has date = today (already started)
        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        // No existing entry → first selection is allowed even after start
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Playing with "Iron Thorns".');
    }

    public function testChangeDeckDeniedAfterEventStart(): void
    {
        $this->loginAs('admin@example.com');

        // The "Expanded Weekly #42" event has date = today (already started)
        $event = $this->getFixtureEvent();
        $ironThorns = $this->getAdminDeck('Iron Thorns');
        $ancientBox = $this->getAdminDeck('Ancient Box');

        // First selection (allowed — no existing entry)
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $ironThorns->getId(),
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Playing with "Iron Thorns".');

        // Second selection (denied — entry exists and event started)
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $ancientBox->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Deck selection cannot be changed after the event has started.');
    }

    public function testSelectDeckDeniedForNonParticipant(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFutureEvent();

        // Register as participant to get a select-deck form with CSRF token
        $this->registerParticipant($event);
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        // Withdraw to become a non-participant
        $withdrawForm = $crawler->selectButton('Withdraw')->form();
        $this->client->submit($withdrawForm);
        $this->client->followRedirect();

        // Now POST with the previously obtained CSRF token
        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => '1',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'You must be a participant to select a deck.');
    }

    public function testPlayableDecksShownOnEventPage(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // Admin owns Iron Thorns + Ancient Box → should see radio buttons
        self::assertSelectorExists('input[type="radio"][name="deck_id"]');
        self::assertSelectorExists('button:contains("Save selection")');

        // Should see "Your borrows and playable decks" heading
        $heading = $crawler->filter('h6:contains("Your borrows and playable decks")');
        self::assertSame(1, $heading->count(), 'Should see "Your borrows and playable decks" heading.');

        // Should see "Own deck" badge
        $ownBadges = $crawler->filter('.badge:contains("Own deck")');
        self::assertGreaterThanOrEqual(1, $ownBadges->count());
    }

    public function testPendingBorrowRadioDisabledInTable(): void
    {
        // Borrower is a participant at the today event with a pending borrow (Iron Thorns)
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // Pending borrow (Iron Thorns) should have a disabled radio
        $disabledRadios = $crawler->filter('input[type="radio"][name="deck_id"][disabled]');
        self::assertGreaterThanOrEqual(1, $disabledRadios->count(), 'Pending borrow should have a disabled radio.');
    }

    public function testRadiosDisabledAfterEntryAndEventStart(): void
    {
        $this->loginAs('admin@example.com');

        // The "Expanded Weekly #42" event has date = today (already started)
        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        // First select a deck (allowed — no entry yet)
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);
        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Now revisit — entry exists + event started → canChangeDeck is false
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // All radios should be disabled
        $allRadios = $crawler->filter('input[type="radio"][name="deck_id"]');
        $disabledRadios = $crawler->filter('input[type="radio"][name="deck_id"][disabled]');
        self::assertGreaterThan(0, $allRadios->count());
        self::assertSame($allRadios->count(), $disabledRadios->count(), 'All radios should be disabled when entry exists and event started.');

        // Save button should NOT exist
        self::assertSelectorNotExists('button:contains("Save selection")');
    }

    // ---------------------------------------------------------------
    // F4.12 — Walk-up lending
    // ---------------------------------------------------------------

    public function testWalkUpPageAccessibleForOrganizer(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h5', 'Walk-up Lend');
    }

    public function testWalkUpPageAccessibleForStaff(): void
    {
        // borrower@example.com is staff for the fixture event
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h5', 'Walk-up Lend');
    }

    public function testWalkUpPageDeniedForRegularUser(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testWalkUpCreatesBorrowInLentState(): void
    {
        // Admin is organizer of the event
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        // Cancel existing borrow for Iron Thorns so we can walk-up lend it
        $this->cancelExistingBorrowsForDeck($deck, $event);

        $borrower = $this->getUser('lender@example.com');

        $crawler = $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/walk-up', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
            'borrower_id' => (string) $borrower->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'lent to');

        /** @var \App\Repository\BorrowRepository $borrowRepo */
        $borrowRepo = static::getContainer()->get(\App\Repository\BorrowRepository::class);
        $borrows = $borrowRepo->findByEvent($event);
        $found = false;
        foreach ($borrows as $borrow) {
            if ($borrow->isWalkUp() && BorrowStatus::Lent === $borrow->getStatus()) {
                $found = true;
                self::assertSame(DeckStatus::Lent, $borrow->getDeck()->getStatus());
                self::assertNotNull($borrow->getApprovedAt());
                self::assertNotNull($borrow->getHandedOffAt());
                break;
            }
        }
        self::assertTrue($found, 'Walk-up borrow in Lent state should exist.');
    }

    public function testWalkUpAutoRegistersParticipant(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');
        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Lender is NOT a participant of the event
        $lender = $this->getUser('lender@example.com');
        /** @var EventEngagementRepository $engRepo */
        $engRepo = static::getContainer()->get(EventEngagementRepository::class);
        $engagementBefore = $engRepo->findOneBy(['event' => $event->getId(), 'user' => $lender->getId()]);
        self::assertNull($engagementBefore, 'Lender should not be engaged in event before walk-up.');

        $crawler = $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/walk-up', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
            'borrower_id' => (string) $lender->getId(),
        ]);

        self::assertResponseRedirects();

        // Verify auto-registration
        $engagementAfter = $engRepo->findOneBy(['event' => $event->getId(), 'user' => $lender->getId()]);
        self::assertNotNull($engagementAfter, 'Lender should be auto-registered as participant after walk-up.');
    }

    public function testWalkUpDeniedForCancelledEvent(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $event->setCancelledAt(new \DateTimeImmutable());
        $em->flush();

        $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'Walk-up lending is not available');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function cancelExistingBorrowsForDeck(Deck $deck, Event $event): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var \App\Repository\BorrowRepository $repo */
        $repo = static::getContainer()->get(\App\Repository\BorrowRepository::class);
        $existing = $repo->findActiveBorrowForDeckAtEvent($deck, $event);

        if (null !== $existing) {
            $existing->setStatus(BorrowStatus::Cancelled);
            $existing->setCancelledAt(new \DateTimeImmutable());
            $em->flush();
        }
    }

    private function getFixtureEvent(): Event
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy([]);
        self::assertNotNull($event);

        return $event;
    }

    private function getFutureEvent(): Event
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy(['name' => 'Lyon Expanded Cup 2026']);
        self::assertNotNull($event);

        return $event;
    }

    private function getAdminDeck(string $name): Deck
    {
        /** @var DeckRepository $repo */
        $repo = static::getContainer()->get(DeckRepository::class);
        $deck = $repo->findOneBy(['name' => $name]);
        self::assertNotNull($deck);

        return $deck;
    }

    private function getUser(string $email): User
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }

    private function getBorrowerUserId(): int
    {
        return (int) $this->getUser('borrower@example.com')->getId();
    }

    private function getLenderUserId(): int
    {
        return (int) $this->getUser('lender@example.com')->getId();
    }

    /**
     * Registers the currently logged-in user as a player on the given event via POST.
     */
    private function registerParticipant(Event $event): void
    {
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    private function extractSelectDeckCsrfToken(\Symfony\Component\DomCrawler\Crawler $crawler): string
    {
        $form = $crawler->filter('form[action*="select-deck"]');
        self::assertGreaterThan(0, $form->count(), 'Select-deck form should exist.');

        $token = $form->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($token);

        return $token;
    }
}
