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
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Repository\EventRepository;
use App\Repository\EventStaffRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Additional coverage tests for EventController methods not fully covered
 * by EventControllerTest.
 *
 * @see docs/features.md F3.5  — Assign event staff team
 * @see docs/features.md F3.10 — Cancel an event
 * @see docs/features.md F3.13 — Player engagement states
 * @see docs/features.md F3.20 — Mark event as finished
 * @see docs/features.md F4.8  — Staff-delegated lending
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
class EventControllerCoverageTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // F3.5 — Assign staff
    // ---------------------------------------------------------------

    public function testAssignStaffSucceeds(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $staffForm = $crawler->filter('form[action*="assign-staff"]');
        self::assertGreaterThan(0, $staffForm->count(), 'Assign staff form should exist for organizer.');
        $csrfToken = $staffForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'lender@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'has been added to the staff team');

        /** @var EventStaffRepository $staffRepository */
        $staffRepository = static::getContainer()->get(EventStaffRepository::class);
        $lender = $this->getUser('lender@example.com');
        $staffMember = $staffRepository->findOneBy(['event' => $event->getId(), 'user' => $lender->getId()]);
        self::assertNotNull($staffMember);
    }

    public function testAssignStaffDeniedForNonOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('borrower@example.com');

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => 'any-token',
            'user_query' => 'lender@example.com',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAssignStaffInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => 'invalid-token',
            'user_query' => 'lender@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testAssignStaffCancelledEventDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $staffForm = $crawler->filter('form[action*="assign-staff"]');
        $csrfToken = $staffForm->filter('input[name="_token"]')->attr('value');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'lender@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot assign staff to a cancelled event.');
    }

    public function testAssignStaffUserNotFoundShowsWarning(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $staffForm = $crawler->filter('form[action*="assign-staff"]');
        $csrfToken = $staffForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'nonexistent@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'not found');
    }

    public function testAssignOrganizerAsStaffDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $staffForm = $crawler->filter('form[action*="assign-staff"]');
        $csrfToken = $staffForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'admin@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'organizer cannot be assigned as staff');
    }

    public function testAssignAlreadyStaffShowsWarning(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // borrower is already staff from fixtures
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $staffForm = $crawler->filter('form[action*="assign-staff"]');
        $csrfToken = $staffForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/assign-staff', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'borrower@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'is already a staff member');
    }

    // ---------------------------------------------------------------
    // F3.5 — Remove staff
    // ---------------------------------------------------------------

    public function testRemoveStaffSucceeds(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Load event page and extract remove-staff CSRF token from rendered form
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $removeForm = $crawler->filter('form[action*="remove-staff"]')->first();
        self::assertGreaterThan(0, $removeForm->count(), 'Remove staff form should exist for organizer.');
        $csrfToken = $removeForm->filter('input[name="_token"]')->attr('value');
        $actionUrl = $removeForm->attr('action');

        $this->client->request('POST', $actionUrl, [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'has been removed from the staff team');
    }

    public function testRemoveStaffDeniedForNonOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('borrower@example.com');

        $this->client->request('POST', \sprintf('/event/%d/remove-staff/1', $event->getId()), [
            '_token' => 'any-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRemoveStaffInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        /** @var EventStaffRepository $staffRepository */
        $staffRepository = static::getContainer()->get(EventStaffRepository::class);
        $borrower = $this->getUser('borrower@example.com');
        $staffMember = $staffRepository->findOneBy(['event' => $event->getId(), 'user' => $borrower->getId()]);
        self::assertNotNull($staffMember);

        $this->client->request('POST', \sprintf('/event/%d/remove-staff/%d', $event->getId(), $staffMember->getId()), [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testRemoveStaffCancelledEventDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Load event page and extract remove-staff CSRF token from rendered form
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $removeForm = $crawler->filter('form[action*="remove-staff"]')->first();
        $csrfToken = $removeForm->filter('input[name="_token"]')->attr('value');
        $actionUrl = $removeForm->attr('action');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', $actionUrl, [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot remove staff from a cancelled event.');
    }

    public function testRemoveStaffNotFoundThrows404(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Initialize session so getCsrfToken() can generate a token for the nonexistent staff ID
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('remove-staff-999999');

        $this->client->request('POST', \sprintf('/event/%d/remove-staff/999999', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    // ---------------------------------------------------------------
    // F3.13 — Interested — edge cases
    // ---------------------------------------------------------------

    public function testInterestedInvalidCsrfRedirects(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/interested', $event->getId()), [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testInterestedCancelledEventDenied(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton("I'm interested")->form();
        $csrfToken = $form->get('_token')->getValue();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/interested', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot register for a cancelled event.');
    }

    public function testInterestedFinishedEventDenied(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->selectButton("I'm interested")->form();
        $csrfToken = $form->get('_token')->getValue();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setFinishedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/interested', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot register for a cancelled event.');
    }

    // ---------------------------------------------------------------
    // F3.13 — Invite — edge cases
    // ---------------------------------------------------------------

    public function testInviteInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Initialize session with a GET request first
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $this->client->request('POST', \sprintf('/event/%d/invite', $event->getId()), [
            '_token' => 'invalid-token',
            'user_query' => 'lender@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testInviteCancelledEventDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $inviteForm = $crawler->filter('form[action*="invite"]');
        $csrfToken = $inviteForm->filter('input[name="_token"]')->attr('value');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/invite', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'lender@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot invite participants');
    }

    public function testInviteFinishedEventDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $inviteForm = $crawler->filter('form[action*="invite"]');
        $csrfToken = $inviteForm->filter('input[name="_token"]')->attr('value');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setFinishedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/invite', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'lender@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot invite participants');
    }

    public function testInviteUserNotFoundShowsWarning(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $inviteForm = $crawler->filter('form[action*="invite"]');
        $csrfToken = $inviteForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/invite', $event->getId()), [
            '_token' => $csrfToken,
            'user_query' => 'nonexistent@example.com',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'not found');
    }

    // ---------------------------------------------------------------
    // F3.7 — Select deck — edge cases
    // ---------------------------------------------------------------

    public function testSelectDeckInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => 'invalid-token',
            'deck_id' => '1',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testSelectDeckCancelledEventDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => '1',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Deck selection is not available');
    }

    public function testSelectDeckNonexistentDeckShowsDanger(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => '999999',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Deck not found.');
    }

    public function testSelectDeckUnavailableDeckShowsDanger(): void
    {
        // Borrower is a participant; try to select a deck they don't own and don't have an active borrow for
        $this->loginAs('borrower@example.com');

        $event = $this->getFutureEvent();

        // Register borrower as participant first
        $this->registerParticipant($event);

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        // Regidrago is owned by lender, borrower has no borrow for it at the future event
        $regidrago = $this->getDeckByName('Regidrago');

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $regidrago->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'This deck is not available for selection.');
    }

    // ---------------------------------------------------------------
    // F4.8 — Toggle registration / delegation — edge cases
    // ---------------------------------------------------------------

    public function testToggleRegistrationInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/toggle-registration', $event->getId()), [
            '_token' => 'invalid-token',
            'deck_id' => '1',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testToggleRegistrationCancelledEventDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        // Use Ancient Box and cancel its borrows so the cancelled-event guard is hit
        $deck = $this->getAdminDeck('Ancient Box');
        $this->cancelExistingBorrowsForDeck($deck, $event);

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $registrationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-registration"]', $event->getId()))->first();
        $csrfToken = $registrationForm->filter('input[name="_token"]')->attr('value');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/toggle-registration', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot change registration for a cancelled or finished event.');
    }

    public function testToggleRegistrationNonexistentDeckShowsDanger(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $registrationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-registration"]', $event->getId()))->first();
        $csrfToken = $registrationForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-registration', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => '999999',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Deck not found.');
    }

    public function testToggleDelegationInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => 'invalid-token',
            'deck_id' => '1',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testToggleDelegationCancelledEventDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $delegationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-delegation"]', $event->getId()))->first();
        $csrfToken = $delegationForm->filter('input[name="_token"]')->attr('value');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot change delegation for a cancelled or finished event.');
    }

    public function testToggleDelegationNonexistentDeckShowsDanger(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $delegationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-delegation"]', $event->getId()))->first();
        $csrfToken = $delegationForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => '999999',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Deck not found.');
    }

    public function testToggleDelegationOtherOwnerDeckDenied(): void
    {
        // Borrower tries to toggle delegation on admin's deck
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        // Borrower owns Lugia Archeops, so delegation form exists on the page; extract CSRF token
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $delegationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-delegation"]', $event->getId()))->first();
        $csrfToken = $delegationForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'You can only manage delegation for your own decks.');
    }

    public function testToggleDelegationCannotRevokeWhileInCustody(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        // Set up: Iron Thorns is delegated and physically with staff (received but not returned)
        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        self::assertTrue($registration->isDelegateToStaff());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReceivedBy($this->getUser('admin@example.com'));
        $entityManager->flush();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $delegationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-delegation"]', $event->getId()))->first();
        $csrfToken = $delegationForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Cannot revoke delegation');
    }

    public function testUnregisterDeckWithActiveBorrowDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        // Ancient Box has an approved borrow for borrower at this event
        $deck = $this->getAdminDeck('Ancient Box');

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $registrationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-registration"]', $event->getId()))->first();
        $csrfToken = $registrationForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-registration', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot unregister');
    }

    // ---------------------------------------------------------------
    // F4.14 — Custody handover tracking
    // ---------------------------------------------------------------

    public function testOwnerHandoverSucceeds(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        self::assertTrue($registration->isDelegateToStaff());
        self::assertFalse($registration->hasStaffReceived());
        $registrationId = $registration->getId();

        // "Hand to staff" button is on the page — extract CSRF from it
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $handoverForm = $crawler->filter(\sprintf('form[action*="custody/%d/owner-handover"]', $registrationId))->first();
        self::assertGreaterThan(0, $handoverForm->count(), 'Owner handover form should exist.');
        $csrfToken = $handoverForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $event->getId(), $registrationId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'handed over to staff');

        /** @var EventDeckRegistrationRepository $freshRepository */
        $freshRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $freshRegistration = $freshRepository->find($registrationId);
        self::assertNotNull($freshRegistration);
        self::assertTrue($freshRegistration->hasStaffReceived());
    }

    public function testOwnerHandoverInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $event->getId(), $registration->getId()), [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testOwnerHandoverNotFoundThrows404(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-owner-handover-999999');

        $this->client->request('POST', \sprintf('/event/%d/custody/999999/owner-handover', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnerHandoverByNonOwnerShowsDanger(): void
    {
        // Borrower tries to hand over admin's deck
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registrationId = $registration->getId();

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-owner-handover-'.$registrationId);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $event->getId(), $registrationId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Only the deck owner can confirm the handover');
    }

    public function testOwnerHandoverAlreadyHandedOverShowsDanger(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registrationId = $registration->getId();

        // Mark as already handed over
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReceivedBy($this->getUser('admin@example.com'));
        $entityManager->flush();

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-owner-handover-'.$registrationId);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $event->getId(), $registrationId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'already been handed over');
    }

    // ---------------------------------------------------------------
    // F4.14 — Staff return
    // ---------------------------------------------------------------

    public function testStaffReturnSucceeds(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        // Set up: hand over first, then cancel borrows so the return is possible
        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registrationId = $registration->getId();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReceivedBy($this->getUser('admin@example.com'));
        $entityManager->flush();

        // Cancel existing borrows for this deck at this event
        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-staff-return-'.$registrationId);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/staff-return', $event->getId(), $registrationId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'returned to owner');

        /** @var EventDeckRegistrationRepository $freshRepository */
        $freshRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $freshRegistration = $freshRepository->find($registrationId);
        self::assertNotNull($freshRegistration);
        self::assertTrue($freshRegistration->hasStaffReturned());
    }

    public function testStaffReturnInvalidCsrfRedirects(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/staff-return', $event->getId(), $registration->getId()), [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testStaffReturnNotFoundThrows404(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-staff-return-999999');

        $this->client->request('POST', \sprintf('/event/%d/custody/999999/staff-return', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testStaffReturnNotReceivedShowsDanger(): void
    {
        // Try to return a deck that was never handed over
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        self::assertFalse($registration->hasStaffReceived());
        $registrationId = $registration->getId();

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-staff-return-'.$registrationId);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/staff-return', $event->getId(), $registrationId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'not been handed over to staff');
    }

    public function testStaffReturnByNonStaffShowsDanger(): void
    {
        // Lender (not staff) tries to confirm return
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registrationId = $registration->getId();

        // Set up: hand over so return path can be tested
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReceivedBy($this->getUser('admin@example.com'));
        $entityManager->flush();

        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-staff-return-'.$registrationId);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/staff-return', $event->getId(), $registrationId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Only organizers or staff');
    }

    // ---------------------------------------------------------------
    // F4.14 — Owner reclaim
    // ---------------------------------------------------------------

    public function testOwnerReclaimSucceeds(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registrationId = $registration->getId();

        // Set up: hand over so reclaim is possible
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReceivedBy($this->getUser('admin@example.com'));
        $entityManager->flush();

        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-owner-reclaim-'.$registrationId);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-reclaim', $event->getId(), $registrationId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'returned to you');

        /** @var EventDeckRegistrationRepository $freshRepository */
        $freshRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $freshRegistration = $freshRepository->find($registrationId);
        self::assertNotNull($freshRegistration);
        self::assertTrue($freshRegistration->hasStaffReturned());
    }

    public function testOwnerReclaimInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-reclaim', $event->getId(), $registration->getId()), [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testOwnerReclaimNotFoundThrows404(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-owner-reclaim-999999');

        $this->client->request('POST', \sprintf('/event/%d/custody/999999/owner-reclaim', $event->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnerReclaimByNonOwnerShowsDanger(): void
    {
        // Borrower tries to reclaim admin's deck
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registrationId = $registration->getId();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReceivedBy($this->getUser('admin@example.com'));
        $entityManager->flush();

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-owner-reclaim-'.$registrationId);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-reclaim', $event->getId(), $registrationId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Only the deck owner can reclaim');
    }

    public function testOwnerReclaimNotReceivedShowsDanger(): void
    {
        // Try to reclaim a deck that was never handed over
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        self::assertFalse($registration->hasStaffReceived());
        $registrationId = $registration->getId();

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-owner-reclaim-'.$registrationId);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-reclaim', $event->getId(), $registrationId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'not been handed over to staff');
    }

    public function testOwnerReclaimAlreadyReturnedShowsDanger(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registrationId = $registration->getId();

        // Set up: hand over and already returned
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getUser('admin@example.com');
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReceivedBy($admin);
        $registration->setStaffReturnedAt(new \DateTimeImmutable());
        $registration->setStaffReturnedBy($admin);
        $entityManager->flush();

        // Initialize session
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $csrfToken = $this->getCsrfToken('custody-owner-reclaim-'.$registrationId);

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-reclaim', $event->getId(), $registrationId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'already been returned');
    }

    // ---------------------------------------------------------------
    // F4.12 — Walk-up submit — edge cases
    // ---------------------------------------------------------------

    public function testWalkUpSubmitInvalidCsrfRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/walk-up', $event->getId()), [
            '_token' => 'invalid-token',
            'deck_id' => '1',
            'borrower_id' => '1',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d/walk-up', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testWalkUpSubmitDeniedForNonStaff(): void
    {
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();

        $this->client->request('POST', \sprintf('/event/%d/walk-up', $event->getId()), [
            '_token' => 'any-token',
            'deck_id' => '1',
            'borrower_id' => '1',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testWalkUpSubmitDeckNotFound(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/walk-up', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => '999999',
            'borrower_id' => '1',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d/walk-up', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Deck not found.');
    }

    public function testWalkUpSubmitBorrowerNotFound(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        $crawler = $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/walk-up', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
            'borrower_id' => '999999',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d/walk-up', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Borrower not found.');
    }

    // ---------------------------------------------------------------
    // F3.3 — Show — visibility edge cases
    // ---------------------------------------------------------------

    public function testShowFinishedEventDisplaysBadge(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('.badge.bg-secondary', 'Finished');
    }

    public function testShowPrivateEventDeniedForNonEngagedUser(): void
    {
        // Draft event is not accessible to lender
        $this->loginAs('lender@example.com');

        $event = $this->getDraftEvent();

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testShowPrivateEventAccessibleForAdmin(): void
    {
        // Admin has ROLE_ADMIN, should be able to see any draft event
        $this->loginAs('admin@example.com');

        $event = $this->getDraftEvent();

        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();
    }

    // ---------------------------------------------------------------
    // Available decks — finished event
    // ---------------------------------------------------------------

    public function testAvailableDecksPageRedirectsForFinishedEvent(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFinishedEvent();

        $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Decks cannot be browsed for a cancelled or finished event.');
    }

    // ---------------------------------------------------------------
    // F3.20 — Finish — denied for other organizer
    // ---------------------------------------------------------------

    public function testFinishEventDeniedForOtherOrganizer(): void
    {
        $event = $this->getFixtureEvent();

        $this->loginAs('organizer@example.com');

        $this->client->request('POST', \sprintf('/event/%d/finish', $event->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    // ---------------------------------------------------------------
    // F3.7 — Select deck — finished event
    // ---------------------------------------------------------------

    public function testSelectDeckFinishedEventDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFutureEvent();

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setFinishedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => '1',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Deck selection is not available');
    }

    // ---------------------------------------------------------------
    // F4.8 — Toggle registration / delegation — finished event
    // ---------------------------------------------------------------

    public function testToggleRegistrationFinishedEventDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        // Cancel borrows for Ancient Box so the finished-event guard is hit
        $deck = $this->getAdminDeck('Ancient Box');
        $this->cancelExistingBorrowsForDeck($deck, $event);

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $registrationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-registration"]', $event->getId()))->first();
        $csrfToken = $registrationForm->filter('input[name="_token"]')->attr('value');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setFinishedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/toggle-registration', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot change registration for a cancelled or finished event.');
    }

    public function testToggleDelegationFinishedEventDenied(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $delegationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-delegation"]', $event->getId()))->first();
        $csrfToken = $delegationForm->filter('input[name="_token"]')->attr('value');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $freshEvent = $entityManager->find(Event::class, $event->getId());
        self::assertNotNull($freshEvent);
        $freshEvent->setFinishedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Cannot change delegation for a cancelled or finished event.');
    }

    // ---------------------------------------------------------------
    // Walk-up — finished event
    // ---------------------------------------------------------------

    public function testWalkUpFinishedEventRedirects(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $event->setFinishedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'Walk-up lending is not available');
    }

    // ---------------------------------------------------------------
    // F3.20 — Finish — edit allowed for finished event (not cancelled)
    // ---------------------------------------------------------------

    public function testEditFinishedEventAllowedByOrganizer(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $event->setFinishedAt(new \DateTimeImmutable());
        $entityManager->flush();

        // Finished events can still be edited (only cancelled are blocked)
        $this->client->request('GET', \sprintf('/event/%d/edit', $event->getId()));
        self::assertResponseIsSuccessful();
    }

    // ---------------------------------------------------------------
    // F3.13 — Invite — access denied for organizer not staff
    // ---------------------------------------------------------------

    /**
     * An organizer who is not the event organizer or staff should get a 403
     * from the isOrganizerOrStaff guard inside the invite action.
     *
     * Covers EventController::invite() line 541.
     *
     * @see docs/features.md F3.13 — Player engagement states
     */
    public function testInviteAccessDeniedForOrganizerNotStaffOfEvent(): void
    {
        // "Past Expanded Weekly #40" is organized by admin; organizer@example.com
        // is NOT the organizer and NOT staff for this event, but has ROLE_ORGANIZER.
        $this->loginAs('organizer@example.com');

        $event = $this->getFinishedEvent(); // organizer: admin, not cancelled

        $this->client->request('POST', \sprintf('/event/%d/invite', $event->getId()), [
            '_token' => 'any-token',
            'user_query' => 'lender@example.com',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ---------------------------------------------------------------
    // F4.8 — Toggle registration — other user's deck
    // ---------------------------------------------------------------

    /**
     * Trying to toggle registration for a deck owned by another user should
     * be denied with a flash message.
     *
     * Covers EventController::toggleDeckRegistration() lines 765, 767.
     *
     * @see docs/features.md F4.8 — Staff-delegated lending
     */
    public function testToggleRegistrationOtherOwnerDeckDenied(): void
    {
        // Borrower tries to register admin's deck
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        // Borrower owns Lugia Archeops, so a registration form exists; extract CSRF token
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $registrationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-registration"]', $event->getId()))->first();
        $csrfToken = $registrationForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-registration', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'You can only manage registration for your own decks.');
    }

    // ---------------------------------------------------------------
    // F4.12 — Walk-up — DomainException
    // ---------------------------------------------------------------

    /**
     * Walk-up lending with a retired deck should catch the DomainException
     * and display a danger flash.
     *
     * Covers EventController::walkUpSubmit() lines 1052-1053, 1055.
     *
     * @see docs/features.md F4.12 — Walk-up lending (direct lend)
     */
    public function testWalkUpSubmitDomainExceptionShowsDanger(): void
    {
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        // Retire the deck so createWalkUpBorrow throws a DomainException
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $deck->setStatus(\App\Enum\DeckStatus::Retired);
        $entityManager->flush();

        $borrower = $this->getUser('borrower@example.com');

        $crawler = $this->client->request('GET', \sprintf('/event/%d/walk-up', $event->getId()));
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/walk-up', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
            'borrower_id' => (string) $borrower->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d/walk-up', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'retired');
    }

    // ---------------------------------------------------------------
    // F3.7 — Select deck with pending borrow (non-approved/non-lent)
    // ---------------------------------------------------------------

    /**
     * A borrower tries to select a deck they have a Pending borrow for.
     * The resolvePlayableDeckVersion method should return null because
     * Pending is neither Approved nor Lent.
     *
     * Covers EventController::resolvePlayableDeckVersion() line 1094.
     *
     * @see docs/features.md F3.7 — Register played deck for event
     */
    public function testSelectDeckWithPendingBorrowDenied(): void
    {
        // Borrower has a Pending borrow for Iron Thorns at "Expanded Weekly #42"
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getAdminDeck('Iron Thorns');

        // Verify the pending borrow exists
        /** @var BorrowRepository $borrowRepository */
        $borrowRepository = static::getContainer()->get(BorrowRepository::class);
        $borrow = $borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event);
        self::assertNotNull($borrow);
        self::assertSame(BorrowStatus::Pending, $borrow->getStatus());

        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $csrfToken = $this->extractSelectDeckCsrfToken($crawler);

        $this->client->request('POST', \sprintf('/event/%d/select-deck', $event->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $deck->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'This deck is not available for selection.');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function cancelExistingBorrowsForDeck(Deck $deck, Event $event): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var BorrowRepository $borrowRepository */
        $borrowRepository = static::getContainer()->get(BorrowRepository::class);
        $existing = $borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event);

        if (null !== $existing) {
            $existing->setStatus(BorrowStatus::Cancelled);
            $existing->setCancelledAt(new \DateTimeImmutable());
            $entityManager->flush();
        }
    }

    private function getFixtureEvent(): Event
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $event = $eventRepository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        return $event;
    }

    private function getFutureEvent(): Event
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $event = $eventRepository->findOneBy(['name' => 'Lyon Expanded Cup 2026']);
        self::assertNotNull($event);

        return $event;
    }

    private function getFinishedEvent(): Event
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $event = $eventRepository->findOneBy(['name' => 'Past Expanded Weekly #40']);
        self::assertNotNull($event);

        return $event;
    }

    private function getDraftEvent(): Event
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $event = $eventRepository->findOneBy(['name' => 'Draft Event — Not Yet Published']);
        self::assertNotNull($event);

        return $event;
    }

    private function getAdminDeck(string $name): Deck
    {
        /** @var DeckRepository $deckRepository */
        $deckRepository = static::getContainer()->get(DeckRepository::class);
        $deck = $deckRepository->findOneBy(['name' => $name]);
        self::assertNotNull($deck);

        return $deck;
    }

    private function getDeckByName(string $name): Deck
    {
        /** @var DeckRepository $deckRepository */
        $deckRepository = static::getContainer()->get(DeckRepository::class);
        $deck = $deckRepository->findOneBy(['name' => $name]);
        self::assertNotNull($deck);

        return $deck;
    }

    private function getUser(string $email): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }

    /**
     * Generate a CSRF token that shares the browser client's session.
     *
     * The container's CsrfTokenManager uses SessionTokenStorage, which requires
     * an active request on the RequestStack. Between requests in functional tests,
     * there is no active request, causing SessionNotFoundException. This helper
     * pushes a synthetic request with the client's session onto the RequestStack
     * so the token manager can read/write tokens in the same session the browser uses.
     *
     * IMPORTANT: a prior GET request must have been made to initialize the session.
     */
    private function getCsrfToken(string $tokenId): string
    {
        $session = $this->client->getSession();
        self::assertNotNull($session, 'Session must exist — make a GET request first.');
        $session->start();

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = static::getContainer()->get('request_stack');

        $syntheticRequest = new \Symfony\Component\HttpFoundation\Request();
        $syntheticRequest->setSession($session);
        $requestStack->push($syntheticRequest);

        try {
            /** @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokenManager */
            $tokenManager = static::getContainer()->get('security.csrf.token_manager');

            return $tokenManager->getToken($tokenId)->getValue();
        } finally {
            $requestStack->pop();
            $session->save();
        }
    }

    private function extractSelectDeckCsrfToken(\Symfony\Component\DomCrawler\Crawler $crawler): string
    {
        $form = $crawler->filter('form[action*="select-deck"]');
        self::assertGreaterThan(0, $form->count(), 'Select-deck form should exist.');

        $token = $form->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($token);

        return $token;
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
}
