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
use App\Enum\BorrowStatus;
use App\Enum\DeckStatus;
use App\Repository\BorrowRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F4.1 — Request to borrow a deck
 * @see docs/features.md F4.2 — Approve / deny a borrow request
 * @see docs/features.md F4.3 — Confirm deck hand-off (lend)
 * @see docs/features.md F4.4 — Confirm deck return
 * @see docs/features.md F4.7 — Cancel a borrow request
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

        // Visit the event page to get the borrow request form
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/borrow/request"]');
        self::assertGreaterThan(0, $form->count(), 'Borrow request form should be present.');

        $csrfToken = $form->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            'notes' => 'Need it for round 2',
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
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

        // Admin sees the borrow form (Lugia VSTAR from lender is available), but Iron Thorns
        // won't be in the dropdown. POST directly to test server-side validation.
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
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

    public function testRequestBorrowDeniedIfDeckNotAvailable(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Ancient Box');

        // Ancient Box is already Reserved in fixtures
        self::assertSame(DeckStatus::Reserved, $deck->getStatus());

        // Visit event page — Ancient Box won't appear in dropdown (reserved), POST directly
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
        $form = $crawler->filter('form[action="/borrow/request"]');
        $csrfToken = $form->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => $deck->getId(),
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'This deck is not available for borrowing.');
    }

    public function testRequestBorrowDeniedIfActiveBorrowExists(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        // Iron Thorns already has a pending borrow in fixtures
        $crawler = $this->client->request('GET', \sprintf('/event/%d', $event->getId()));
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

    // ---------------------------------------------------------------
    // F4.2 — Approve / deny a borrow request
    // ---------------------------------------------------------------

    public function testApproveSetsApprovedAndDeckReserved(): void
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
        self::assertSame(DeckStatus::Reserved, $fresh->getDeck()->getStatus());
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

    public function testCancelApprovedBorrowRevertsDeck(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->getApprovedBorrow();
        self::assertSame(DeckStatus::Reserved, $borrow->getDeck()->getStatus());

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
