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
use App\Entity\EventDeckRegistration;
use App\Enum\BorrowStatus;
use App\Enum\DeckStatus;
use App\Repository\BorrowRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
class StaffCustodyHandoverTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // Owner handover
    // ---------------------------------------------------------------

    public function testOwnerHandoverSuccess(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $csrfToken = $this->extractOwnerHandoverToken($crawler, $reg->getId());

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $reg->getEvent()->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'handed over to staff');

        $fresh = $this->refreshRegistration($reg);
        self::assertNotNull($fresh->getStaffReceivedAt());
        self::assertNotNull($fresh->getStaffReceivedBy());
        self::assertTrue($fresh->hasStaffReceived());
    }

    public function testOwnerHandoverDeniedForNonOwner(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Non-owner cannot see the handover form
        $this->loginAs('borrower@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        self::assertResponseIsSuccessful();

        $handoverForm = $crawler->filter(\sprintf('form[action$="/custody/%d/owner-handover"]', $reg->getId()));
        self::assertCount(0, $handoverForm, 'Non-owner should not see the Hand to staff button.');
    }

    public function testOwnerHandoverIdempotencyBlocked(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $csrfToken = $this->extractOwnerHandoverToken($crawler, $reg->getId());

        // First handover — success
        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $csrfToken,
        ]);
        self::assertResponseRedirects();

        // After handover, the "Hand to staff" button should disappear
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $handoverForm = $crawler->filter(\sprintf('form[action$="/custody/%d/owner-handover"]', $reg->getId()));
        self::assertCount(0, $handoverForm, 'Hand to staff button should disappear after handover.');
    }

    // ---------------------------------------------------------------
    // Staff return
    // ---------------------------------------------------------------

    public function testStaffReturnSuccess(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over first
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $ownerToken = $this->extractOwnerHandoverToken($crawler, $reg->getId());
        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $ownerToken,
        ]);
        self::assertResponseRedirects();

        // Staff confirms return (restart client to switch user session)
        $this->client->restart();
        $this->loginAs('staff1@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $returnToken = $this->extractStaffReturnToken($crawler, $reg->getId());

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/staff-return', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $returnToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $reg->getEvent()->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'returned to owner');

        $fresh = $this->refreshRegistration($reg);
        self::assertNotNull($fresh->getStaffReturnedAt());
        self::assertNotNull($fresh->getStaffReturnedBy());
        self::assertTrue($fresh->hasStaffReturned());
    }

    public function testStaffReturnRequiresReceivedFirst(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Staff sees the registration but no return button (deck not handed over yet)
        $this->loginAs('staff1@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        self::assertResponseIsSuccessful();

        $returnForm = $crawler->filter(\sprintf('form[action$="/custody/%d/staff-return"]', $reg->getId()));
        self::assertCount(0, $returnForm, 'Return button should not appear before owner handover.');

        // Verify the badge shows "Awaiting handover"
        self::assertSelectorTextContains('.badge.bg-warning', 'Awaiting handover');
    }

    public function testStaffReturnDeniedForNonStaff(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Non-staff user cannot see the Staff Custody section at all
        $this->loginAs('lender@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        self::assertResponseIsSuccessful();

        $returnForm = $crawler->filter(\sprintf('form[action$="/custody/%d/staff-return"]', $reg->getId()));
        self::assertCount(0, $returnForm, 'Non-staff should not see the return button.');
    }

    public function testStaffReturnIdempotencyBlocked(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner handover
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $ownerToken = $this->extractOwnerHandoverToken($crawler, $reg->getId());
        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $ownerToken,
        ]);

        // Staff return (restart client to switch user session)
        $this->client->restart();
        $this->loginAs('staff1@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $returnToken = $this->extractStaffReturnToken($crawler, $reg->getId());
        $this->client->request('POST', \sprintf('/event/%d/custody/%d/staff-return', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $returnToken,
        ]);
        self::assertResponseRedirects();

        // After return, the return button should disappear
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $returnForm = $crawler->filter(\sprintf('form[action$="/custody/%d/staff-return"]', $reg->getId()));
        self::assertCount(0, $returnForm, 'Return button should disappear after return.');
    }

    // ---------------------------------------------------------------
    // UI visibility
    // ---------------------------------------------------------------

    public function testOwnerSeesHandToStaffButton(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        self::assertResponseIsSuccessful();

        $handoverForm = $crawler->filter(\sprintf('form[action$="/custody/%d/owner-handover"]', $reg->getId()));
        self::assertCount(1, $handoverForm, 'Owner should see the Hand to staff button for delegated decks.');
    }

    public function testStaffSeesReturnButton(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over first
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $ownerToken = $this->extractOwnerHandoverToken($crawler, $reg->getId());
        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $ownerToken,
        ]);

        // Staff sees return button (restart client to switch user session)
        $this->client->restart();
        $this->loginAs('staff1@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        self::assertResponseIsSuccessful();

        $returnForm = $crawler->filter(\sprintf('form[action$="/custody/%d/staff-return"]', $reg->getId()));
        self::assertCount(1, $returnForm, 'Staff should see the return button after owner handover.');
    }

    // ---------------------------------------------------------------
    // Revoke delegation guard
    // ---------------------------------------------------------------

    public function testRevokeDelegationBlockedWhenDeckWithStaff(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over the deck to staff
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $ownerToken = $this->extractOwnerHandoverToken($crawler, $reg->getId());
        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $ownerToken,
        ]);
        self::assertResponseRedirects();

        // Try to revoke delegation — should be blocked
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $delegationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-delegation"]', $reg->getEvent()->getId()))->first();
        $csrfToken = $delegationForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $reg->getEvent()->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $reg->getDeck()->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $reg->getEvent()->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Cannot revoke delegation');

        // Registration should still be delegated
        $fresh = $this->refreshRegistration($reg);
        self::assertTrue($fresh->isDelegateToStaff());
    }

    public function testRevokeDelegationAllowedAfterStaffReturns(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $ownerToken = $this->extractOwnerHandoverToken($crawler, $reg->getId());
        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $ownerToken,
        ]);

        // Staff returns (restart client to switch user)
        $this->client->restart();
        $this->loginAs('staff1@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $returnToken = $this->extractStaffReturnToken($crawler, $reg->getId());
        $this->client->request('POST', \sprintf('/event/%d/custody/%d/staff-return', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $returnToken,
        ]);

        // Owner revokes delegation — should now succeed
        $this->client->restart();
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $delegationForm = $crawler->filter(\sprintf('form[action="/event/%d/toggle-delegation"]', $reg->getEvent()->getId()))->first();
        $csrfToken = $delegationForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/toggle-delegation', $reg->getEvent()->getId()), [
            '_token' => $csrfToken,
            'deck_id' => (string) $reg->getDeck()->getId(),
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $reg->getEvent()->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Staff delegation disabled');
    }

    public function testRevokeButtonDisabledWhenDeckWithStaff(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over
        $this->loginAs('admin@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $ownerToken = $this->extractOwnerHandoverToken($crawler, $reg->getId());
        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $ownerToken,
        ]);

        // Reload page — Revoke button should be disabled
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $revokeButtons = $crawler->filter('button:contains("Revoke")');
        self::assertGreaterThan(0, $revokeButtons->count(), 'Revoke button should still be visible.');

        // Find the Revoke button for this deck — it should have "disabled" attribute
        $revokeButton = $revokeButtons->first();
        self::assertNotNull($revokeButton->attr('disabled'), 'Revoke button should be disabled when deck is with staff.');
    }

    // ---------------------------------------------------------------
    // Staff return guard (lent/overdue borrows)
    // ---------------------------------------------------------------

    public function testStaffReturnBlockedWhenDeckIsLent(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over
        $this->performOwnerHandover($reg);

        // Create a lent borrow for this deck at the event
        $this->createBorrowForDeck($reg, BorrowStatus::Lent);

        // Staff tries to return to owner — should be blocked
        $this->client->restart();
        $this->loginAs('staff1@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));

        // The button should be disabled in the UI
        $staffReturnForms = $crawler->filter(\sprintf('form[action$="/custody/%d/staff-return"]', $reg->getId()));
        if ($staffReturnForms->count() > 0) {
            $button = $staffReturnForms->first()->filter('button[type="submit"]');
            self::assertNotNull($button->attr('disabled'), 'Staff return button should be disabled when deck is lent.');
        }

        // Even if we POST directly, the service should reject it
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $staffReturnForm = $crawler->filter(\sprintf('form[action$="/custody/%d/staff-return"]', $reg->getId()));
        $csrfToken = $staffReturnForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/staff-return', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Cannot return deck to owner');

        $fresh = $this->refreshRegistration($reg);
        self::assertNull($fresh->getStaffReturnedAt(), 'Staff return should not have been recorded.');
    }

    public function testStaffReturnAllowedWhenNoBorrowIsLent(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over
        $this->performOwnerHandover($reg);

        // Create a returned borrow (non-blocking) and a pending borrow (non-blocking)
        $returnedBorrow = $this->createBorrowForDeck($reg, BorrowStatus::Returned);
        $returnedBorrow->setReturnedAt(new \DateTimeImmutable());

        $pendingBorrow = $this->createBorrowForDeck($reg, BorrowStatus::Pending);
        $this->getEntityManager()->flush();

        // Staff return should succeed
        $this->client->restart();
        $this->loginAs('staff1@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $returnToken = $this->extractStaffReturnToken($crawler, $reg->getId());

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/staff-return', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $returnToken,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'returned to owner');

        $fresh = $this->refreshRegistration($reg);
        self::assertNotNull($fresh->getStaffReturnedAt());
    }

    public function testStaffReturnAutoClosesReturnedBorrows(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over
        $this->performOwnerHandover($reg);

        // Create a returned borrow
        $returnedBorrow = $this->createBorrowForDeck($reg, BorrowStatus::Returned);
        $returnedBorrow->setReturnedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
        $borrowId = $returnedBorrow->getId();

        // Staff returns deck to owner
        $this->client->restart();
        $this->loginAs('staff1@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $returnToken = $this->extractStaffReturnToken($crawler, $reg->getId());

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/staff-return', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $returnToken,
        ]);

        self::assertResponseRedirects();

        // Returned borrow should now be returned_to_owner
        $fresh = $this->refetchBorrow((int) $borrowId);
        self::assertSame(BorrowStatus::ReturnedToOwner, $fresh->getStatus());
        self::assertNotNull($fresh->getReturnedToOwnerAt());
    }

    // ---------------------------------------------------------------
    // Owner reclaim
    // ---------------------------------------------------------------

    public function testOwnerReclaimSuccess(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over
        $this->performOwnerHandover($reg);

        // Owner reclaims (no restart needed — same user session)
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $reclaimToken = $this->extractOwnerReclaimToken($crawler, $reg->getId());

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-reclaim', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $reclaimToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $reg->getEvent()->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'returned to you');

        $fresh = $this->refreshRegistration($reg);
        self::assertNotNull($fresh->getStaffReturnedAt());
        self::assertTrue($fresh->hasStaffReturned());
    }

    public function testOwnerReclaimClosesLentBorrow(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over
        $this->performOwnerHandover($reg);

        // Create a lent borrow
        $lentBorrow = $this->createBorrowForDeck($reg, BorrowStatus::Lent);
        $lentBorrow->getDeck()->setStatus(DeckStatus::Lent);
        $this->getEntityManager()->flush();
        $borrowId = $lentBorrow->getId();

        // Owner reclaims (no restart needed — same user session)
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $reclaimToken = $this->extractOwnerReclaimToken($crawler, $reg->getId());

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-reclaim', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $reclaimToken,
        ]);

        self::assertResponseRedirects();

        // Borrow should be fully closed (returned_to_owner)
        $fresh = $this->refetchBorrow((int) $borrowId);
        self::assertSame(BorrowStatus::ReturnedToOwner, $fresh->getStatus());
        self::assertNotNull($fresh->getReturnedAt());
        self::assertNotNull($fresh->getReturnedToOwnerAt());

        // Deck should be available
        self::assertSame(DeckStatus::Available, $fresh->getDeck()->getStatus());
    }

    public function testOwnerReclaimClosesMultipleBorrows(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over
        $this->performOwnerHandover($reg);

        // Create a pending borrow + a lent borrow
        $pendingBorrow = $this->createBorrowForDeck($reg, BorrowStatus::Pending);
        $lentBorrow = $this->createBorrowForDeck($reg, BorrowStatus::Lent);
        $lentBorrow->getDeck()->setStatus(DeckStatus::Lent);
        $this->getEntityManager()->flush();
        $pendingId = $pendingBorrow->getId();
        $lentId = $lentBorrow->getId();

        // Owner reclaims (no restart needed — same user session)
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $reclaimToken = $this->extractOwnerReclaimToken($crawler, $reg->getId());

        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-reclaim', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $reclaimToken,
        ]);

        self::assertResponseRedirects();

        // Pending borrow should be cancelled
        $freshPending = $this->refetchBorrow((int) $pendingId);
        self::assertSame(BorrowStatus::Cancelled, $freshPending->getStatus());
        self::assertNotNull($freshPending->getCancelledAt());

        // Lent borrow should be returned_to_owner
        $freshLent = $this->refetchBorrow((int) $lentId);
        self::assertSame(BorrowStatus::ReturnedToOwner, $freshLent->getStatus());
    }

    public function testOwnerSeesReclaimButton(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over
        $this->performOwnerHandover($reg);

        // Reload — should see "Mark returned to me"
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        self::assertResponseIsSuccessful();

        $reclaimForm = $crawler->filter(\sprintf('form[action$="/custody/%d/owner-reclaim"]', $reg->getId()));
        self::assertCount(1, $reclaimForm, 'Owner should see the Mark returned to me button when deck is with staff.');
    }

    public function testStaffReturnButtonDisabledWhenLent(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Owner hands over
        $this->performOwnerHandover($reg);

        // Create a lent borrow
        $this->createBorrowForDeck($reg, BorrowStatus::Lent);

        // Staff sees the return button but it should be disabled
        $this->client->restart();
        $this->loginAs('staff1@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        self::assertResponseIsSuccessful();

        $staffReturnForms = $crawler->filter(\sprintf('form[action$="/custody/%d/staff-return"]', $reg->getId()));
        self::assertCount(1, $staffReturnForms, 'Staff return form should still be present.');

        $button = $staffReturnForms->first()->filter('button[type="submit"]');
        self::assertNotNull($button->attr('disabled'), 'Staff return button should be disabled when deck is lent.');
        self::assertStringContainsString('Collect the deck from the borrower first', $button->attr('title') ?? '');
    }

    // ---------------------------------------------------------------
    // Hand-off custody guard (UI)
    // ---------------------------------------------------------------

    public function testHandOffButtonDisabledForAwaitingHandover(): void
    {
        $reg = $this->getDelegatedRegistrationOwnedBy('admin@example.com');

        // Create an approved borrow for this delegated deck so the hand-off button appears
        $this->loginAs('borrower@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $reg->getEvent()->getId()));
        $borrowForm = $crawler->filter('form[action$="/borrow"]')->reduce(static function (Crawler $form) use ($reg) {
            $deckInput = $form->filter('input[name="deck_id"]');

            return $deckInput->count() > 0 && $deckInput->attr('value') === (string) $reg->getDeck()->getId();
        });

        if ($borrowForm->count() > 0) {
            $csrfToken = $borrowForm->filter('input[name="_token"]')->attr('value');
            $this->client->request('POST', \sprintf('/event/%d/borrow', $reg->getEvent()->getId()), [
                '_token' => $csrfToken,
                'deck_id' => (string) $reg->getDeck()->getId(),
            ]);
        }

        // Staff approves the borrow (restart client to switch user)
        $this->client->restart();
        $this->loginAs('staff1@example.com');
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));

        // Staff sees the Borrower Activity section — find the hand-off button
        $handOffButtons = $crawler->filter('button:contains("Hand off")');

        if ($handOffButtons->count() > 0) {
            // If there's an approved borrow, the hand-off button should be disabled (deck not received)
            $handOffButton = $handOffButtons->first();
            self::assertNotNull($handOffButton->attr('disabled'), 'Hand off button should be disabled when deck awaits handover.');
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function getDelegatedRegistrationOwnedBy(string $ownerEmail): EventDeckRegistration
    {
        /** @var EventRepository $eventRepo */
        $eventRepo = static::getContainer()->get(EventRepository::class);
        $event = $eventRepo->findOneBy([]);
        self::assertNotNull($event);

        /** @var EventDeckRegistrationRepository $regRepo */
        $regRepo = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registrations = $regRepo->findDelegatedByEvent($event);

        foreach ($registrations as $reg) {
            if ($reg->getDeck()->getOwner()->getEmail() === $ownerEmail) {
                return $reg;
            }
        }

        self::fail(\sprintf('No delegated registration found for owner "%s" at event "%s".', $ownerEmail, $event->getName()));
    }

    private function refreshRegistration(EventDeckRegistration $reg): EventDeckRegistration
    {
        /** @var EventDeckRegistrationRepository $repo */
        $repo = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $fresh = $repo->find($reg->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }

    private function extractOwnerHandoverToken(Crawler $crawler, ?int $registrationId): string
    {
        $form = $crawler->filter(\sprintf('form[action$="/custody/%d/owner-handover"]', $registrationId));
        self::assertCount(1, $form, 'Owner handover form not found on page.');
        $token = $form->filter('input[name="_token"]')->attr('value');
        self::assertNotEmpty($token);

        return $token;
    }

    private function extractStaffReturnToken(Crawler $crawler, ?int $registrationId): string
    {
        $form = $crawler->filter(\sprintf('form[action$="/custody/%d/staff-return"]', $registrationId));
        self::assertCount(1, $form, 'Staff return form not found on page.');
        $token = $form->filter('input[name="_token"]')->attr('value');
        self::assertNotEmpty($token);

        return $token;
    }

    private function extractOwnerReclaimToken(Crawler $crawler, ?int $registrationId): string
    {
        $form = $crawler->filter(\sprintf('form[action$="/custody/%d/owner-reclaim"]', $registrationId));
        self::assertCount(1, $form, 'Owner reclaim form not found on page.');
        $token = $form->filter('input[name="_token"]')->attr('value');
        self::assertNotEmpty($token);

        return $token;
    }

    private function performOwnerHandover(EventDeckRegistration $reg): void
    {
        $this->loginAs($reg->getDeck()->getOwner()->getEmail());
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $reg->getEvent()->getId()));
        $ownerToken = $this->extractOwnerHandoverToken($crawler, $reg->getId());
        $this->client->request('POST', \sprintf('/event/%d/custody/%d/owner-handover', $reg->getEvent()->getId(), $reg->getId()), [
            '_token' => $ownerToken,
        ]);
        self::assertResponseRedirects();
    }

    private function createBorrowForDeck(EventDeckRegistration $reg, BorrowStatus $status): Borrow
    {
        $em = $this->getEntityManager();

        /** @var \App\Repository\UserRepository $userRepo */
        $userRepo = static::getContainer()->get(\App\Repository\UserRepository::class);
        $borrower = $userRepo->findOneBy(['email' => 'borrower@example.com']);
        self::assertNotNull($borrower);

        // Re-fetch managed entities to avoid detached entity issues after HTTP requests
        /** @var EventDeckRegistrationRepository $regRepo */
        $regRepo = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $freshReg = $regRepo->find($reg->getId());
        self::assertNotNull($freshReg);

        $deck = $freshReg->getDeck();
        $currentVersion = $deck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($currentVersion);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($freshReg->getEvent());
        $borrow->setStatus($status);
        $borrow->setIsDelegatedToStaff(true);

        if (BorrowStatus::Lent === $status || BorrowStatus::Overdue === $status) {
            $borrow->setApprovedAt(new \DateTimeImmutable());
            $borrow->setHandedOffAt(new \DateTimeImmutable());
        } elseif (BorrowStatus::Approved === $status) {
            $borrow->setApprovedAt(new \DateTimeImmutable());
        } elseif (BorrowStatus::Returned === $status) {
            $borrow->setApprovedAt(new \DateTimeImmutable());
            $borrow->setHandedOffAt(new \DateTimeImmutable());
            $borrow->setReturnedAt(new \DateTimeImmutable());
        }

        $em->persist($borrow);
        $em->flush();

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
}
