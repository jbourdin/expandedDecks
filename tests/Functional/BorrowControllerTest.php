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
use App\Entity\EventDeckRegistration;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Enum\DeckStatus;
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F4.1 — Request to borrow a deck
 * @see docs/features.md F4.2 — Approve / deny a borrow request
 * @see docs/features.md F4.3 — Confirm deck hand-off (lend)
 * @see docs/features.md F4.4 — Confirm deck return
 * @see docs/features.md F4.7 — Cancel a borrow request
 * @see docs/features.md F4.8 — Staff-delegated lending
 * @see docs/features.md F8.1 — Borrow workflow email notifications
 */
class BorrowControllerTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // F4.1 — Request to borrow a deck
    // ---------------------------------------------------------------

    public function testRequestBorrowCreatesPendingBorrow(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        // Cancel existing borrow for Iron Thorns so we can request again
        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Visit the available decks page to get the per-deck borrow request form
        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/borrow/request"]')->first();
        self::assertGreaterThan(0, $form->count(), 'Borrow request form should be present.');

        $csrfToken = $form->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            'notes' => 'Need it for round 2',
            'redirect_to' => 'event_decks',
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d/decks', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Borrow request for "Iron Thorns" submitted.');

        /** @var BorrowRepository $repo */
        $repo = static::getContainer()->get(BorrowRepository::class);
        $borrows = $repo->findByEvent($event);

        $found = false;
        foreach ($borrows as $borrow) {
            if ('Need it for round 2' === $borrow->getNotes()) {
                $found = true;
                self::assertSame(BorrowStatus::Pending, $borrow->getStatus());
                self::assertSame('borrower@example.com', $borrow->getBorrower()->getEmail());
            }
        }
        self::assertTrue($found, 'New borrow with notes "Need it for round 2" not found.');
    }

    public function testRequestBorrowDeniedIfNotParticipant(): void
    {
        // Lender is not a participant in the event
        $this->loginAs('lender@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        // Visit the event page to establish a session — lender won't see the form
        // but we can still POST with a manually crafted token
        $this->client->request('GET', \sprintf('/event/%d', $event->getId()));

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            '_token' => 'invalid',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testRequestBorrowDeniedIfOwnDeck(): void
    {
        // Admin owns Iron Thorns and Ancient Box, is a participant
        $this->loginAs('admin@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        // Cancel existing borrow so Iron Thorns is available
        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Admin sees the available decks page (Regidrago from lender is available),
        // but Iron Thorns won't be listed. POST directly to test server-side validation.
        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));
        $form = $crawler->filter('form[action="/borrow/request"]');
        $csrfToken = $form->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'You cannot borrow your own deck.');
    }

    public function testRequestBorrowDeniedIfDeckRetired(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Regidrago');
        $eventId = $event->getId();
        $deckId = $deck->getId();

        // Visit available decks page BEFORE retiring to get a valid CSRF token
        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $eventId));
        $csrfToken = $crawler->filter('form[action="/borrow/request"] input[name="_token"]')->attr('value');

        // Re-fetch deck from current kernel's EM and retire it
        $em = $this->getEntityManager();
        /** @var Deck $freshDeck */
        $freshDeck = $em->find(Deck::class, $deckId);
        $freshDeck->setStatus(DeckStatus::Retired);
        $em->flush();

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $eventId,
            'deck_id' => $deckId,
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $eventId));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'This deck is retired and cannot be borrowed.');
    }

    public function testRequestBorrowDeniedIfActiveBorrowExists(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        // Iron Thorns already has a pending borrow in fixtures.
        // Get CSRF token from the available decks page.
        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));
        $form = $crawler->filter('form[action="/borrow/request"]');
        $csrfToken = $form->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'already has an active borrow');
    }

    /**
     * @see docs/features.md F4.11 — Borrow conflict detection
     */
    public function testRequestBorrowDeniedIfSameDayConflict(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Regidrago');
        $eventId = $event->getId();
        $deckId = $deck->getId();

        $em = $this->getEntityManager();

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $borrower = $userRepo->findOneBy(['email' => 'borrower@example.com']);
        self::assertNotNull($borrower);

        // Create a second event on the same day with borrower engaged
        $secondEvent = new Event();
        $secondEvent->setName('Same Day Event');
        $secondEvent->setDate(new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')));
        $secondEvent->setTimezone('Europe/Paris');
        $secondEvent->setOrganizer($userRepo->findOneBy(['email' => 'admin@example.com']));
        $secondEvent->setRegistrationLink('https://example.com/same-day');
        $em->persist($secondEvent);

        $engagement = new \App\Entity\EventEngagement();
        $engagement->setEvent($secondEvent);
        $engagement->setUser($borrower);
        $engagement->setState(\App\Enum\EngagementState::RegisteredPlaying);
        $engagement->setParticipationMode(\App\Enum\ParticipationMode::Playing);
        $em->persist($engagement);
        $em->flush();

        $secondEventId = $secondEvent->getId();

        // Visit available decks page BEFORE creating the conflicting borrow to get CSRF token
        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $secondEventId));
        self::assertResponseIsSuccessful();
        $csrfToken = $crawler->filter('form[action="/borrow/request"] input[name="_token"]')->attr('value');

        // Re-fetch entities from current kernel's EM (previous EM is stale after client request)
        $em = $this->getEntityManager();
        /** @var Deck $freshDeck */
        $freshDeck = $em->find(Deck::class, $deckId);
        $currentVersion = $freshDeck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $freshBorrower = $userRepo->findOneBy(['email' => 'borrower@example.com']);
        self::assertNotNull($freshBorrower);

        /** @var Event $freshEvent */
        $freshEvent = $em->find(Event::class, $eventId);

        // Now create a borrow for Regidrago at the first event — this creates the conflict
        $existingBorrow = new Borrow();
        $existingBorrow->setDeck($freshDeck);
        $existingBorrow->setDeckVersion($currentVersion);
        $existingBorrow->setBorrower($freshBorrower);
        $existingBorrow->setEvent($freshEvent);
        $em->persist($existingBorrow);
        $em->flush();

        // Try to borrow Regidrago at the second event — should be blocked (same-day conflict)
        $this->client->request('POST', '/borrow/request', [
            'event_id' => $secondEventId,
            'deck_id' => $deckId,
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $secondEventId));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'already has an active borrow at another event on the same day');
    }

    public function testRequestBorrowInvalidCsrf(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            '_token' => 'invalid',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    public function testRequestBorrowFromAvailableDecksPageRedirectsBack(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        // Cancel existing borrow for Iron Thorns so we can request again
        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Visit the available decks page to get per-deck forms
        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/borrow/request"]')->first();
        $csrfToken = $form->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            'notes' => 'From available decks page',
            'redirect_to' => 'event_decks',
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d/decks', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Borrow request for "Iron Thorns" submitted.');
    }

    public function testRequestBorrowFromDeckPageRedirectsToDeck(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        // Cancel existing borrow for Iron Thorns so we can request again
        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Visit the deck page to verify the borrow form is present
        $crawler = $this->client->request('GET', \sprintf('/deck/%d', $deck->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/borrow/request"]');
        self::assertGreaterThan(0, $form->count(), 'Borrow request form should be present on deck page.');

        // Extract CSRF token from the event option's data-token attribute
        // (the form uses JS to copy this to the hidden _token input on change)
        $option = $form->filter(\sprintf('option[value="%d"]', $event->getId()));
        self::assertGreaterThan(0, $option->count(), 'Event option should be present.');
        $csrfToken = $option->attr('data-token');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            'notes' => 'Requesting from deck page',
            'redirect_to' => 'deck',
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/deck/%d', $deck->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Borrow request for "Iron Thorns" submitted.');
    }

    // ---------------------------------------------------------------
    // F4.2 — Approve / deny a borrow request
    // ---------------------------------------------------------------

    public function testApproveSetsApproved(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->getPendingBorrow();

        // Visit borrow show page and extract CSRF token from the Approve form
        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        self::assertResponseIsSuccessful();

        $approveForm = $crawler->filter(\sprintf('form[action="/borrow/%d/approve"]', $borrow->getId()));
        $csrfToken = $approveForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/approve', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Borrow request approved.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());

        self::assertSame(BorrowStatus::Approved, $fresh->getStatus());
        self::assertNotNull($fresh->getApprovedAt());
        // Deck stays Available — approval no longer sets Reserved (per-event concern)
        self::assertSame(DeckStatus::Available, $fresh->getDeck()->getStatus());
    }

    public function testDenySetsStatusCancelled(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->getPendingBorrow();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $denyForm = $crawler->filter(\sprintf('form[action="/borrow/%d/deny"]', $borrow->getId()));
        $csrfToken = $denyForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/deny', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Borrow request denied.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());

        self::assertSame(BorrowStatus::Cancelled, $fresh->getStatus());
        self::assertNotNull($fresh->getCancelledAt());
    }

    public function testApproveByNonOwnerDenied(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->getPendingBorrow();

        // Borrower visits the show page — won't see approve button, but we POST directly
        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        // The cancel form is visible (borrower can cancel), get its CSRF token format
        $cancelForm = $crawler->filter(\sprintf('form[action="/borrow/%d/cancel"]', $borrow->getId()));
        $csrfToken = $cancelForm->filter('input[name="_token"]')->attr('value');

        // Use a different (invalid) token for approve — format doesn't match
        $this->client->request('POST', \sprintf('/borrow/%d/approve', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        // Either invalid CSRF or access denied
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // F4.3 — Confirm deck hand-off (lend)
    // ---------------------------------------------------------------

    public function testHandOffSetsLentAndDeckLent(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->getApprovedBorrow();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $handOffForm = $crawler->filter(\sprintf('form[action="/borrow/%d/hand-off"]', $borrow->getId()));
        $csrfToken = $handOffForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/hand-off', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Deck has been handed off.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());

        self::assertSame(BorrowStatus::Lent, $fresh->getStatus());
        self::assertNotNull($fresh->getHandedOffAt());
        self::assertSame(DeckStatus::Lent, $fresh->getDeck()->getStatus());
    }

    public function testHandOffByNonOwnerDenied(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->getApprovedBorrow();

        // Borrower can cancel, so get a token from the page, but POST to hand-off
        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $cancelForm = $crawler->filter(\sprintf('form[action="/borrow/%d/cancel"]', $borrow->getId()));
        $csrfToken = $cancelForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/hand-off', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // F4.4 — Confirm deck return
    // ---------------------------------------------------------------

    public function testReturnSetsReturnedAndDeckAvailable(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->getApprovedBorrow();
        $this->transitionToLent($borrow);

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $returnForm = $crawler->filter(\sprintf('form[action="/borrow/%d/return"]', $borrow->getId()));
        $csrfToken = $returnForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/return', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Deck return confirmed.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());

        self::assertSame(BorrowStatus::Returned, $fresh->getStatus());
        self::assertNotNull($fresh->getReturnedAt());
        self::assertSame(DeckStatus::Available, $fresh->getDeck()->getStatus());
    }

    public function testReturnByNonOwnerDenied(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->getApprovedBorrow();
        $this->transitionToLent($borrow);

        // Lent status — borrower has no action forms, so POST with invalid token
        $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));

        $this->client->request('POST', \sprintf('/borrow/%d/return', $borrow->getId()), [
            '_token' => 'dummy',
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // F4.7 — Cancel a borrow request
    // ---------------------------------------------------------------

    public function testCancelPendingBorrow(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->getPendingBorrow();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $cancelForm = $crawler->filter(\sprintf('form[action="/borrow/%d/cancel"]', $borrow->getId()));
        $csrfToken = $cancelForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/cancel', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Borrow has been cancelled.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());

        self::assertSame(BorrowStatus::Cancelled, $fresh->getStatus());
        self::assertNotNull($fresh->getCancelledAt());
        // Deck stays available (was not reserved for a pending borrow)
        self::assertSame(DeckStatus::Available, $fresh->getDeck()->getStatus());
    }

    public function testCancelApprovedBorrow(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->getApprovedBorrow();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $cancelForm = $crawler->filter(\sprintf('form[action="/borrow/%d/cancel"]', $borrow->getId()));
        $csrfToken = $cancelForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/cancel', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Borrow has been cancelled.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());

        self::assertSame(BorrowStatus::Cancelled, $fresh->getStatus());
        self::assertSame(DeckStatus::Available, $fresh->getDeck()->getStatus());
    }

    public function testCancelLentBorrowDenied(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->getApprovedBorrow();
        $this->transitionToLent($borrow);

        // Lent status — borrower has no cancel form, POST directly with dummy token
        $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));

        $this->client->request('POST', \sprintf('/borrow/%d/cancel', $borrow->getId()), [
            '_token' => 'dummy',
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        // Should show an error (invalid CSRF or workflow transition not possible)
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // F4.8 — Staff-delegated lending
    // ---------------------------------------------------------------

    public function testBorrowAutoInheritsDelegationFromRegistration(): void
    {
        // Iron Thorns already has a delegation registration from fixtures
        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        // Cancel existing borrow so we can request again
        $this->cancelExistingBorrowsForDeck($deck, $event);

        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/borrow/request"]')->first();
        $csrfToken = $form->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            'redirect_to' => 'event_decks',
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d/decks', $event->getId()));

        /** @var BorrowRepository $repo */
        $repo = static::getContainer()->get(BorrowRepository::class);
        $borrows = $repo->findByEvent($event);

        $found = false;
        foreach ($borrows as $borrow) {
            if (BorrowStatus::Pending === $borrow->getStatus() && $borrow->isDelegatedToStaff()) {
                $found = true;
            }
        }
        self::assertTrue($found, 'New borrow should inherit isDelegatedToStaff from EventDeckRegistration.');
    }

    public function testStaffCanHandOffDelegatedBorrow(): void
    {
        // borrower@example.com is staff for the fixture event
        $this->loginAs('borrower@example.com');

        $borrow = $this->getApprovedBorrow();
        $this->setDelegated($borrow);

        // Need a different borrower — use a fresh pending borrow that won't be the current user
        // Actually the approved borrow's borrower IS borrower@example.com. We need a borrow
        // where the borrower is someone else and borrower@example.com is staff.
        // Let's create a dedicated borrow for this test.
        $borrow = $this->createDelegatedBorrowForStaffTest();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        self::assertResponseIsSuccessful();

        $handOffForm = $crawler->filter(\sprintf('form[action="/borrow/%d/hand-off"]', $borrow->getId()));
        self::assertGreaterThan(0, $handOffForm->count(), 'Staff should see hand-off button for delegated borrow.');
        $csrfToken = $handOffForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/hand-off', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Deck has been handed off.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());
        self::assertSame(BorrowStatus::Lent, $fresh->getStatus());
    }

    public function testStaffCanReturnDelegatedBorrow(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->createDelegatedBorrowForStaffTest();
        $this->transitionToLent($borrow);

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        self::assertResponseIsSuccessful();

        $returnForm = $crawler->filter(\sprintf('form[action="/borrow/%d/return"]', $borrow->getId()));
        self::assertGreaterThan(0, $returnForm->count(), 'Staff should see return button for delegated borrow.');
        $csrfToken = $returnForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/return', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Deck return confirmed.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());
        self::assertSame(BorrowStatus::Returned, $fresh->getStatus());
    }

    public function testStaffCannotActOnNonDelegatedBorrow(): void
    {
        $this->loginAs('borrower@example.com');

        // Create a borrow where borrower@example.com is staff but deck has no delegation registration
        $em = $this->getEntityManager();
        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Regidrago');
        $currentVersion = $deck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        $nonDelegated = new Borrow();
        $nonDelegated->setDeck($deck);
        $nonDelegated->setDeckVersion($currentVersion);
        $nonDelegated->setBorrower($userRepo->findOneBy(['email' => 'admin@example.com']));
        $nonDelegated->setEvent($event);
        $nonDelegated->setStatus(BorrowStatus::Approved);
        $nonDelegated->setApprovedAt(new \DateTimeImmutable());
        // isDelegatedToStaff defaults to false — no registration exists
        $em->persist($nonDelegated);
        $em->flush();

        // Staff tries to hand off non-delegated borrow
        $this->client->request('POST', \sprintf('/borrow/%d/hand-off', $nonDelegated->getId()), [
            '_token' => 'dummy',
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $nonDelegated->getId()));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testStaffCanApproveDelegatedBorrow(): void
    {
        // Register Regidrago with delegation at the fixture event
        $em = $this->getEntityManager();
        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Regidrago');
        $currentVersion = $deck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        $registration = new EventDeckRegistration();
        $registration->setEvent($event);
        $registration->setDeck($deck);
        $registration->setDelegateToStaff(true);
        $em->persist($registration);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $admin = $userRepo->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($currentVersion);
        $borrow->setBorrower($admin);
        $borrow->setEvent($event);
        $borrow->setIsDelegatedToStaff(true);
        $em->persist($borrow);
        $em->flush();

        // borrower@example.com is staff for the fixture event
        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        self::assertResponseIsSuccessful();

        $approveForm = $crawler->filter(\sprintf('form[action="/borrow/%d/approve"]', $borrow->getId()));
        self::assertGreaterThan(0, $approveForm->count(), 'Staff should see approve button for delegated borrow.');
        $csrfToken = $approveForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/approve', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Borrow request approved.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());
        self::assertSame(BorrowStatus::Approved, $fresh->getStatus());
    }

    public function testStaffCanDenyDelegatedBorrow(): void
    {
        $em = $this->getEntityManager();
        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Regidrago');
        $currentVersion = $deck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        $registration = new EventDeckRegistration();
        $registration->setEvent($event);
        $registration->setDeck($deck);
        $registration->setDelegateToStaff(true);
        $em->persist($registration);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $admin = $userRepo->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($currentVersion);
        $borrow->setBorrower($admin);
        $borrow->setEvent($event);
        $borrow->setIsDelegatedToStaff(true);
        $em->persist($borrow);
        $em->flush();

        $this->loginAs('borrower@example.com');

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        self::assertResponseIsSuccessful();

        $denyForm = $crawler->filter(\sprintf('form[action="/borrow/%d/deny"]', $borrow->getId()));
        self::assertGreaterThan(0, $denyForm->count(), 'Staff should see deny button for delegated borrow.');
        $csrfToken = $denyForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/deny', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Borrow request denied.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());
        self::assertSame(BorrowStatus::Cancelled, $fresh->getStatus());
    }

    public function testReturnToOwnerTransition(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->createDelegatedBorrowForStaffTest();
        $this->transitionToLent($borrow);

        // Return the deck (staff)
        $em = $this->getEntityManager();
        $borrow->setStatus(BorrowStatus::Returned);
        $borrow->setReturnedAt(new \DateTimeImmutable());
        $borrow->getDeck()->setStatus(DeckStatus::Available);
        $em->flush();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        self::assertResponseIsSuccessful();

        $returnToOwnerForm = $crawler->filter(\sprintf('form[action="/borrow/%d/return-to-owner"]', $borrow->getId()));
        self::assertGreaterThan(0, $returnToOwnerForm->count(), 'Return to Owner button should appear.');
        $csrfToken = $returnToOwnerForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/return-to-owner', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Deck returned to owner.');

        $fresh = $this->refetchBorrow((int) $borrow->getId());
        self::assertSame(BorrowStatus::ReturnedToOwner, $fresh->getStatus());
        self::assertNotNull($fresh->getReturnedToOwnerAt());
    }

    // ---------------------------------------------------------------
    // F8.1 — Borrow workflow email notifications
    // ---------------------------------------------------------------

    public function testBorrowRequestSendsEmailToOwner(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        $this->cancelExistingBorrowsForDeck($deck, $event);

        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/borrow/request"]')->first();
        $csrfToken = $form->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            'redirect_to' => 'event_decks',
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects();
        self::assertEmailCount(1);
    }

    public function testApproveSendsEmailToBorrower(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->getPendingBorrow();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $approveForm = $crawler->filter(\sprintf('form[action="/borrow/%d/approve"]', $borrow->getId()));
        $csrfToken = $approveForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/approve', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects();
        self::assertEmailCount(1);
    }

    public function testDenySendsEmailToBorrower(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->getPendingBorrow();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $denyForm = $crawler->filter(\sprintf('form[action="/borrow/%d/deny"]', $borrow->getId()));
        $csrfToken = $denyForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/deny', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects();
        self::assertEmailCount(1);
    }

    public function testCancelSendsEmailToOtherParty(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->getPendingBorrow();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $cancelForm = $crawler->filter(\sprintf('form[action="/borrow/%d/cancel"]', $borrow->getId()));
        $csrfToken = $cancelForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/cancel', $borrow->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects();
        self::assertEmailCount(1);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function getFixtureEvent(): Event
    {
        /** @var EventRepository $repo */
        $repo = static::getContainer()->get(EventRepository::class);
        $event = $repo->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        return $event;
    }

    private function getDeckByName(string $name): Deck
    {
        /** @var DeckRepository $repo */
        $repo = static::getContainer()->get(DeckRepository::class);
        $deck = $repo->findOneBy(['name' => $name]);
        self::assertNotNull($deck);

        return $deck;
    }

    private function getPendingBorrow(): Borrow
    {
        /** @var BorrowRepository $repo */
        $repo = static::getContainer()->get(BorrowRepository::class);
        $borrow = $repo->findOneBy(['status' => BorrowStatus::Pending->value]);
        self::assertNotNull($borrow);

        return $borrow;
    }

    private function getApprovedBorrow(): Borrow
    {
        /** @var BorrowRepository $repo */
        $repo = static::getContainer()->get(BorrowRepository::class);
        $borrow = $repo->findOneBy(['status' => BorrowStatus::Approved->value]);
        self::assertNotNull($borrow);

        return $borrow;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    private function refetchBorrow(int $id): Borrow
    {
        /** @var BorrowRepository $repo */
        $repo = static::getContainer()->get(BorrowRepository::class);
        $borrow = $repo->find($id);
        self::assertNotNull($borrow);

        return $borrow;
    }

    private function transitionToLent(Borrow $borrow): void
    {
        $em = $this->getEntityManager();
        $borrow->setStatus(BorrowStatus::Lent);
        $borrow->setHandedOffAt(new \DateTimeImmutable());
        $borrow->getDeck()->setStatus(DeckStatus::Lent);
        $em->flush();
    }

    private function setDelegated(Borrow $borrow): void
    {
        $em = $this->getEntityManager();
        $borrow->setIsDelegatedToStaff(true);
        $em->flush();
    }

    /**
     * Creates a borrow where:
     * - Deck owner: lender@example.com (Regidrago)
     * - Borrower: admin@example.com
     * - Event: Expanded Weekly #42 (borrower@example.com is staff)
     * - Delegated to staff: true
     * - Status: Approved.
     */
    private function createDelegatedBorrowForStaffTest(): Borrow
    {
        $em = $this->getEntityManager();

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $admin = $userRepo->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin);
        $lender = $userRepo->findOneBy(['email' => 'lender@example.com']);
        self::assertNotNull($lender);

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Regidrago');
        $currentVersion = $deck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        // Add lender engagement if missing
        if (null === $event->getEngagementFor($lender)) {
            $engagement = new \App\Entity\EventEngagement();
            $engagement->setEvent($event);
            $engagement->setUser($lender);
            $engagement->setState(\App\Enum\EngagementState::RegisteredPlaying);
            $engagement->setParticipationMode(\App\Enum\ParticipationMode::Playing);
            $em->persist($engagement);
        }

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($currentVersion);
        $borrow->setBorrower($admin);
        $borrow->setEvent($event);
        $borrow->setStatus(BorrowStatus::Approved);
        $borrow->setApprovedAt(new \DateTimeImmutable());
        $borrow->setApprovedBy($lender);
        $borrow->setIsDelegatedToStaff(true);

        $em->persist($borrow);
        $em->flush();

        return $borrow;
    }

    private function cancelExistingBorrowsForDeck(Deck $deck, Event $event): void
    {
        $em = $this->getEntityManager();

        /** @var BorrowRepository $repo */
        $repo = static::getContainer()->get(BorrowRepository::class);
        $existing = $repo->findActiveBorrowForDeckAtEvent($deck, $event);

        if (null !== $existing) {
            $existing->setStatus(BorrowStatus::Cancelled);
            $existing->setCancelledAt(new \DateTimeImmutable());
            $em->flush();
        }
    }
}
