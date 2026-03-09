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
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventDeckEntryRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Repository\EventEngagementRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F3.1 — Create a new event
 * @see docs/features.md F3.4 — Register participation to an event
 * @see docs/features.md F3.7 — Register played deck for event
 * @see docs/features.md F3.9 — Edit an event
 * @see docs/features.md F3.10 — Cancel an event
 * @see docs/features.md F3.20 — Mark event as finished
 * @see docs/features.md F4.8 — Staff-delegated lending
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
    // F3.20 — Mark event as finished
    // ---------------------------------------------------------------

    public function testFinishEventSucceeds(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        self::assertNull($event->getFinishedAt());

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $this->client->submitForm('Mark as Finished');

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', \sprintf('Event "%s" has been marked as finished.', $event->getName()));
        self::assertSelectorTextContains('.badge.bg-secondary', 'Finished');

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $updated = $repo->find($event->getId());

        self::assertNotNull($updated);
        self::assertNotNull($updated->getFinishedAt());
    }

    public function testFinishEventDeniedForNonOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('borrower@example.com');

        $this->client->request('POST', \sprintf('/event/%d/finish', $event->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testFinishEventInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/finish', $event->getId()), [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');

        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $updated = $repo->find($event->getId());

        self::assertNotNull($updated);
        self::assertNull($updated->getFinishedAt());
    }

    public function testFinishAlreadyFinishedEventShowsWarning(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Finish once via the UI to get a valid CSRF token
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $finishForm = $crawler->selectButton('Mark as Finished')->form();
        $csrfToken = $finishForm->get('_token')->getValue();
        self::assertNotEmpty($csrfToken);
        $this->client->submitForm('Mark as Finished');
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'has been marked as finished');

        // Try to finish again with the same CSRF token
        $this->client->request('POST', \sprintf('/event/%d/finish', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'already been marked as finished');
    }

    public function testFinishCancelledEventShowsWarning(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Load the page to get a valid CSRF token
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $finishForm = $crawler->selectButton('Mark as Finished')->form();
        $csrfToken = $finishForm->get('_token')->getValue();

        // Cancel via the UI so the session stays valid
        $this->client->submitForm('Cancel Event');
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Now try to finish the already-cancelled event
        $this->client->request('POST', \sprintf('/event/%d/finish', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot mark a cancelled event as finished');
    }

    public function testFinishButtonHiddenAfterFinishing(): void
    {
        $event = $this->getFixtureEvent();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $event->setFinishedAt(new \DateTimeImmutable());
        $em->flush();

        $this->loginAs('admin@example.com');

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('button.btn-outline-success');
    }

    // ---------------------------------------------------------------
    // F3.13 — Player engagement states
    // ---------------------------------------------------------------

    public function testMarkInterestedCreatesEngagement(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton("I'm interested")->form();
        $this->client->submit($form);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'interested');

        /** @var EventEngagementRepository $repo */
        $repo = static::getContainer()->get(EventEngagementRepository::class);
        $engagement = $repo->findOneBy(['event' => $event->getId(), 'user' => $this->getLenderUserId()]);
        self::assertNotNull($engagement);
        self::assertSame('interested', $engagement->getState()->value);
    }

    public function testMarkInterestedTwiceShowsInfo(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        // First time: mark interested via form
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton("I'm interested")->form();
        $csrfToken = $form->get('_token')->getValue();
        $this->client->submit($form);
        $this->client->followRedirect();

        // Second time: POST directly with same token (button won't be shown since already engaged)
        $this->client->request('POST', \sprintf('/event/%d/interested', $event->getId()), [
            '_token' => $csrfToken,
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-info', 'already engaged');
    }

    public function testInterestedCanUpgradeToPlayer(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        // Mark interested first
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton("I'm interested")->form();
        $this->client->submit($form);
        $this->client->followRedirect();

        // Now upgrade to player
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'registered as a player');

        /** @var EventEngagementRepository $repo */
        $repo = static::getContainer()->get(EventEngagementRepository::class);
        $engagement = $repo->findOneBy(['event' => $event->getId(), 'user' => $this->getLenderUserId()]);
        self::assertNotNull($engagement);
        self::assertSame('registered_playing', $engagement->getState()->value);
    }

    public function testInviteUserCreatesEngagement(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Load event page to establish session and get invite CSRF token
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $inviteForm = $crawler->filter('form[action*="invite"]');
        self::assertGreaterThan(0, $inviteForm->count(), 'Invite form should exist for organizer.');
        $csrfToken = $inviteForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/invite', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'lender@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'has been invited');

        /** @var EventEngagementRepository $repo */
        $repo = static::getContainer()->get(EventEngagementRepository::class);
        $engagement = $repo->findOneBy(['event' => $event->getId(), 'user' => $this->getLenderUserId()]);
        self::assertNotNull($engagement);
        self::assertSame('invited', $engagement->getState()->value);
        self::assertNotNull($engagement->getInvitedBy());
    }

    public function testInviteAlreadyEngagedUserShowsInfo(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Load event page for CSRF token
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $crawler->filter('form[action*="invite"] input[name="_token"]')->attr('value');

        // Invite once
        $this->client->request('POST', \sprintf('/event/%d/invite', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'lender@example.com',
        ]);
        $this->client->followRedirect();

        // Invite again
        $this->client->request('POST', \sprintf('/event/%d/invite', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'lender@example.com',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-info', 'already engaged');
    }

    public function testInviteDeniedForNonStaff(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Load any page to establish a session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $this->client->request('POST', \sprintf('/event/%d/invite', $event->getId()), [
            '_token' => 'any-token',
            'user_query' => 'lender@example.com',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testParticipantsListShowsBadges(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // The event should have at least one participant from fixtures
        self::assertSelectorExists('.list-group-item');
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

        // Fixture has admin + borrower + staff1 registered as playing
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // Should show "3 players, 0 spectators"
        self::assertSelectorExists('.card-body:contains("3 players")');
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

        // Lender should see only borrows involving their own decks (Regidrago delegated borrow)
        $borrowCard = $crawler->filter('h6:contains("Deck Borrowing")')->closest('.card');
        $otherBorrowHeaders = $borrowCard->filter('th:contains("Borrower")');
        if ($otherBorrowHeaders->count() > 0) {
            $otherTable = $otherBorrowHeaders->closest('table');
            $otherRows = $otherTable->filter('tbody tr');
            // Lender owns Regidrago which has a delegated borrow at this event
            self::assertSame(1, $otherRows->count(), 'Lender should see borrows involving their own decks.');
        }
    }

    public function testStaffSeesAllEventBorrows(): void
    {
        // Borrower is a staff member in fixtures — should see borrows in Deck Selection card
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // Borrower's active borrows (Iron Thorns + Ancient Box) appear in the Deck Selection card
        $selectionCard = $crawler->filter('h6:contains("Deck Selection")')->closest('.card');
        $rows = $selectionCard->filter('tbody tr');
        // "No deck selected" row + own decks + borrow rows
        self::assertGreaterThanOrEqual(3, $rows->count(), 'Staff should see borrows in Deck Selection.');
    }

    // ---------------------------------------------------------------
    // Available decks page
    // ---------------------------------------------------------------

    public function testAvailableDecksPageForParticipant(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Cancel Regidrago's delegated borrow so it appears as available
        $regidrago = $this->getAdminDeck('Regidrago');
        $this->cancelExistingBorrowsForDeck($regidrago, $event);

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

        // Admin owns Iron Thorns + Ancient Box → should see radio buttons in Deck Selection card
        self::assertSelectorExists('input[type="radio"][name="deck_id"]');
        self::assertSelectorExists('button:contains("Save selection")');

        // Should see "Deck Selection" card header
        $heading = $crawler->filter('h6:contains("Deck Selection")');
        self::assertSame(1, $heading->count(), 'Should see "Deck Selection" card header.');

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

        /** @var BorrowRepository $borrowRepo */
        $borrowRepo = static::getContainer()->get(BorrowRepository::class);
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
    // F4.8 — Deck registration & staff delegation toggles
    // ---------------------------------------------------------------

    public function testToggleRegistrationCreatesAndRemoves(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Ancient Box');

        /** @var EventDeckRegistrationRepository $regRepo */
        $regRepo = static::getContainer()->get(EventDeckRegistrationRepository::class);

        // Remove existing registration if any (from fixtures)
        $existing = $regRepo->findOneByEventAndDeck($event, $deck);
        if (null !== $existing) {
            /** @var EntityManagerInterface $em */
            $em = static::getContainer()->get('doctrine.orm.entity_manager');
            $em->remove($existing);
            $em->flush();
        }

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        $regForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-registration"]', $event->getId()))->first();
        self::assertGreaterThan(0, $regForm->count(), 'Toggle registration form should exist.');
        $csrfToken = $regForm->filter('input[name="_token"]')->attr('value');

        // Register the deck
        $this->client->request('POST', \sprintf('/event/%d/toggle-registration', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'registered for this event');

        $reg = $regRepo->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($reg);
        self::assertFalse($reg->isDelegateToStaff());

        // Cancel existing borrows so we can unregister
        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Unregister the deck (toggle again)
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $regForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-registration"]', $event->getId()))->first();
        $csrfToken = $regForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-registration', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'unregistered from this event');

        $reg = $regRepo->findOneByEventAndDeck($event, $deck);
        self::assertNull($reg);
    }

    public function testToggleDelegationTogglesOnRegisteredDeck(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        // Iron Thorns has an existing registration with delegation ON from fixtures
        $deck = $this->getAdminDeck('Iron Thorns');

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $delegationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-delegation"]', $event->getId()))->first();
        $csrfToken = $delegationForm->filter('input[name="_token"]')->attr('value');

        // Toggle OFF (currently ON from fixtures)
        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Staff delegation disabled');

        /** @var EventDeckRegistrationRepository $regRepo */
        $regRepo = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $reg = $regRepo->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($reg);
        self::assertFalse($reg->isDelegateToStaff());

        // Toggle ON again
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $delegationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-delegation"]', $event->getId()))->first();
        $csrfToken = $delegationForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Staff delegation enabled');

        $reg = $regRepo->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($reg);
        self::assertTrue($reg->isDelegateToStaff());
    }

    public function testToggleDelegationRequiresRegistration(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Ancient Box');

        /** @var EventDeckRegistrationRepository $regRepo */
        $regRepo = static::getContainer()->get(EventDeckRegistrationRepository::class);

        // Remove existing registration if any (from fixtures)
        $existing = $regRepo->findOneByEventAndDeck($event, $deck);
        if (null !== $existing) {
            /** @var EntityManagerInterface $em */
            $em = static::getContainer()->get('doctrine.orm.entity_manager');
            $em->remove($existing);
            $em->flush();
        }

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $delegationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-delegation"]', $event->getId()))->first();
        $csrfToken = $delegationForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'must be registered before enabling delegation');
    }

    public function testToggleRegistrationRequiresDeckOwnership(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        // Iron Thorns is owned by admin, not borrower
        $deck = $this->getAdminDeck('Iron Thorns');

        $this->client->request('POST', \sprintf('/event/%d/toggle-registration', $event->getId()), [
            '_token' => 'dummy',
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testAvailableDecksOnlyShowsRegistered(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Cancel existing borrows so Iron Thorns becomes available
        $ironThorns = $this->getAdminDeck('Iron Thorns');
        $this->cancelExistingBorrowsForDeck($ironThorns, $event);

        // Iron Thorns is registered at the event (from fixtures) and now has no active borrow
        // Borrower's own Lugia Archeops is excluded (own deck)
        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));
        self::assertResponseIsSuccessful();

        $pageText = $crawler->text();
        self::assertStringContainsString('Iron Thorns', $pageText);

        // Verify that Lugia Archeops (borrower's own, not registered) does NOT appear
        self::assertStringNotContainsString('Lugia Archeops', $pageText);
    }

    // ---------------------------------------------------------------
    // F4.9 — Staff deck custody tracking
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F4.9 — Staff deck custody tracking
     */
    public function testStaffCustodyCardVisibleForStaff(): void
    {
        // Borrower is staff at the today event (fixture)
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('h6:contains("Staff Custody")');
        // The delegated borrow for Regidrago should appear
        self::assertStringContainsString('Regidrago', $crawler->text());
    }

    /**
     * @see docs/features.md F4.9 — Staff deck custody tracking
     */
    public function testStaffCustodyCardHiddenForNonStaff(): void
    {
        // Lender is not staff at the today event
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('h6:contains("Staff Custody")');
    }

    /**
     * @see docs/features.md F4.9 — Staff deck custody tracking
     */
    public function testApproveFromCustodyCardRedirectsToEvent(): void
    {
        // Borrower is staff at the today event and can approve delegated borrows
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Load the event page to get the CSRF token from the rendered custody card
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        $approveForm = $crawler->filter('h6:contains("Staff Custody")')->closest('.card')->filter('form[action*="/approve"]');
        self::assertGreaterThan(0, $approveForm->count(), 'Approve form should exist in custody card.');

        $csrfToken = $approveForm->filter('input[name="_token"]')->attr('value');
        $borrowId = preg_replace('/.*\/borrow\/(\d+)\/approve/', '$1', $approveForm->attr('action'));

        $this->client->request('POST', \sprintf('/borrow/%s/approve', $borrowId), [
            '_token' => $csrfToken,
            'redirect_to' => 'event',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
    }

    /**
     * @see docs/features.md F4.9 — Staff deck custody tracking
     */
    public function testCancelFromCustodyCardRedirectsToEvent(): void
    {
        // Borrower is staff at the today event and can cancel delegated borrows
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        $custodyCard = $crawler->filter('h6:contains("Staff Custody")')->closest('.card');
        $cancelForm = $custodyCard->filter('form[action*="/cancel"]');
        self::assertGreaterThan(0, $cancelForm->count(), 'Cancel form should exist in custody card.');

        $csrfToken = $cancelForm->filter('input[name="_token"]')->attr('value');
        $borrowId = preg_replace('/.*\/borrow\/(\d+)\/cancel/', '$1', $cancelForm->attr('action'));

        $this->client->request('POST', \sprintf('/borrow/%s/cancel', $borrowId), [
            '_token' => $csrfToken,
            'redirect_to' => 'event',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Borrow has been cancelled.');
    }

    // ---------------------------------------------------------------
    // F3.21 — Clear deck selection on withdrawal
    // ---------------------------------------------------------------

    /**
     * @see docs/features.md F3.21 — Clear deck selection on withdrawal
     */
    public function testWithdrawClearsDeckEntry(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Select a deck first (borrower's own Lugia Archeops)
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $token = $this->extractSelectDeckCsrfToken($crawler);

        /** @var DeckRepository $deckRepo */
        $deckRepo = static::getContainer()->get(DeckRepository::class);
        $lugiaArcheops = $deckRepo->findOneBy(['name' => 'Lugia Archeops']);
        self::assertNotNull($lugiaArcheops);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $token,
            'deck_id' => (string) $lugiaArcheops->getId(),
        ]);
        $this->client->followRedirect();

        // Verify deck entry exists
        /** @var EventDeckEntryRepository $entryRepo */
        $entryRepo = static::getContainer()->get(EventDeckEntryRepository::class);
        $user = $this->getUser('borrower@example.com');
        self::assertNotNull($entryRepo->findOneByEventAndPlayer($event, $user));

        // Now withdraw
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $withdrawForm = $crawler->selectButton('Withdraw')->form();
        $this->client->submit($withdrawForm);
        $this->client->followRedirect();

        // Verify deck entry was cleared
        /** @var EventDeckEntryRepository $freshEntryRepo */
        $freshEntryRepo = static::getContainer()->get(EventDeckEntryRepository::class);
        $freshEvent = static::getContainer()->get(EventRepository::class)->find($event->getId());
        self::assertNotNull($freshEvent);
        self::assertNull($freshEntryRepo->findOneByEventAndPlayer($freshEvent, $user));

        self::assertSelectorTextContains('.alert-info', 'Your deck selection has been cleared.');
    }

    /**
     * @see docs/features.md F3.21 — Clear deck selection on withdrawal
     */
    public function testSwitchToSpectatorClearsDeckEntry(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Select a deck first
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $token = $this->extractSelectDeckCsrfToken($crawler);

        /** @var DeckRepository $deckRepo */
        $deckRepo = static::getContainer()->get(DeckRepository::class);
        $lugiaArcheops = $deckRepo->findOneBy(['name' => 'Lugia Archeops']);
        self::assertNotNull($lugiaArcheops);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $token,
            'deck_id' => (string) $lugiaArcheops->getId(),
        ]);
        $this->client->followRedirect();

        // Switch to spectator
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $switchForm = $crawler->selectButton('Switch to Spectator')->form();
        $this->client->submit($switchForm);
        $this->client->followRedirect();

        // Verify deck entry was cleared
        /** @var EventDeckEntryRepository $freshEntryRepo */
        $freshEntryRepo = static::getContainer()->get(EventDeckEntryRepository::class);
        $user = $this->getUser('borrower@example.com');
        $freshEvent = static::getContainer()->get(EventRepository::class)->find($event->getId());
        self::assertNotNull($freshEvent);
        self::assertNull($freshEntryRepo->findOneByEventAndPlayer($freshEvent, $user));

        self::assertSelectorTextContains('.alert-info', 'Your deck selection has been cleared.');
    }

    /**
     * @see docs/features.md F3.21 — Clear deck selection on withdrawal
     */
    public function testSwitchToPlayerDoesNotClearDeckEntry(): void
    {
        // Switch borrower to spectator first, then back to player
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Switch to spectator
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $switchForm = $crawler->selectButton('Switch to Spectator')->form();
        $this->client->submit($switchForm);
        $this->client->followRedirect();

        // Switch back to player — should NOT clear deck entries
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $switchForm = $crawler->selectButton('Switch to Player')->form();
        $this->client->submit($switchForm);
        $this->client->followRedirect();

        // No "deck selection has been cleared" flash
        self::assertSelectorNotExists('.alert-info:contains("deck selection has been cleared")');
    }

    // ---------------------------------------------------------------
    // Invitation-only events
    // ---------------------------------------------------------------

    public function testInvitationOnlyBlocksNonInvitedPlayer(): void
    {
        // Lender is not engaged in the invitational event
        $this->loginAs('lender@example.com');

        $event = $this->getInvitationalEvent();

        // Load the page to init session, then POST participate as player
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // "Register as Player" button should NOT be visible
        self::assertSelectorNotExists('button:contains("Register as Player")');

        // Direct POST should be blocked by the guard — extract CSRF from spectator form
        $spectatorForm = $crawler->selectButton('Register as Spectator')->form();
        $token = $spectatorForm->get('_token')->getValue();
        $this->client->request('POST', \sprintf('/event/%d/participate', $event->getId()), [
            '_token' => $token,
            'mode' => 'playing',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'invitation only');
    }

    public function testInvitationOnlyAllowsInvitedPlayer(): void
    {
        // Admin is invited in the fixture
        $this->loginAs('admin@example.com');

        $event = $this->getInvitationalEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // "Register as Player" button should be visible for invited users
        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'registered as a player');
    }

    public function testInvitationOnlyAllowsSpectator(): void
    {
        // Lender is not invited but should be able to spectate
        $this->loginAs('lender@example.com');

        $event = $this->getInvitationalEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        // "Register as Spectator" should be visible
        $form = $crawler->selectButton('Register as Spectator')->form();
        $this->client->submit($form);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'registered as a spectator');
    }

    public function testInvitedPlayerCanSwitchToSpectatorAndBack(): void
    {
        // Admin is invited in the fixture
        $this->loginAs('admin@example.com');

        $event = $this->getInvitationalEvent();

        // Register as player
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Register as Player')->form();
        $this->client->submit($form);
        $this->client->followRedirect();

        // Switch to spectator
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Switch to Spectator')->form();
        $this->client->submit($form);
        $this->client->followRedirect();

        // Switch back to player — should work because invitedBy is preserved
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton('Switch to Player')->form();
        $this->client->submit($form);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'registered as a player');
    }

    // ---------------------------------------------------------------
    // F3.15 — Event discovery (merged into event list with scope=public)
    // ---------------------------------------------------------------

    public function testPublicScopeShowsPublicEvents(): void
    {
        $this->loginAs('lender@example.com');

        $crawler = $this->client->request('GET', '/event?scope=public');
        self::assertResponseIsSuccessful();

        // The main fixture event is public — it should appear
        self::assertSelectorExists('.card-title');
    }

    public function testPublicScopeExcludesDraftEvents(): void
    {
        $this->loginAs('lender@example.com');

        $crawler = $this->client->request('GET', '/event?scope=public');
        self::assertResponseIsSuccessful();

        // Draft events should NOT appear in public scope
        $html = $crawler->html();
        self::assertStringNotContainsString('Draft Event', $html);
    }

    public function testPublicScopeAccessibleAnonymously(): void
    {
        $this->client->request('GET', '/event?scope=public');
        self::assertResponseIsSuccessful();
    }

    public function testDiscoverRouteRedirectsToPublicScope(): void
    {
        $this->client->request('GET', '/events/discover');
        self::assertResponseRedirects('/event?scope=public', 301);
    }

    public function testEventListHidesDraftEventsFromNonEngagedUser(): void
    {
        // Lender has no engagement on draft event
        $this->loginAs('lender@example.com');

        $crawler = $this->client->request('GET', '/event');
        self::assertResponseIsSuccessful();

        // Draft event should NOT appear in the list
        $html = $crawler->html();
        self::assertStringNotContainsString('Draft Event', $html);
    }

    public function testEventListShowsDraftEventForInvitedUser(): void
    {
        // Admin is invited to the draft event
        $this->loginAs('admin@example.com');

        $crawler = $this->client->request('GET', '/event');
        self::assertResponseIsSuccessful();

        // Should see the draft event because they have an engagement
        $html = $crawler->html();
        self::assertStringContainsString('Draft Event', $html);
    }

    public function testDraftEventAccessDeniedForNonEngagedUser(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getDraftEvent();

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testDraftEventAccessibleForInvitedUser(): void
    {
        // Admin is invited to the draft event
        $this->loginAs('admin@example.com');

        $event = $this->getDraftEvent();

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();
    }

    /**
     * @see docs/features.md F3.21 — Clear deck selection on withdrawal
     */
    public function testWithdrawWithoutDeckEntrySucceeds(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Withdraw without selecting a deck — should work normally
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $withdrawForm = $crawler->selectButton('Withdraw')->form();
        $this->client->submit($withdrawForm);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'You have withdrawn from this event.');
        self::assertSelectorNotExists('.alert-info:contains("deck selection has been cleared")');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function cancelExistingBorrowsForDeck(Deck $deck, Event $event): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var BorrowRepository $repo */
        $repo = static::getContainer()->get(BorrowRepository::class);
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

    private function getInvitationalEvent(): Event
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy(['name' => 'Invitation-Only Expanded Meetup']);
        self::assertNotNull($event);

        return $event;
    }

    private function getDraftEvent(): Event
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy(['name' => 'Draft Event — Not Yet Published']);
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

    private function getCsrfToken(string $tokenId): string
    {
        /** @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokenManager */
        $tokenManager = static::getContainer()->get('security.csrf.token_manager');

        return $tokenManager->getToken($tokenId)->getValue();
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
