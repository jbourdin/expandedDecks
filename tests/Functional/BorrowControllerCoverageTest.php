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
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Additional coverage tests for BorrowController — targets uncovered lines
 * (error paths, CSRF failures, exception catch blocks).
 *
 * @see docs/features.md F4.1 — Request to borrow a deck
 * @see docs/features.md F4.2 — Approve / deny a borrow request
 * @see docs/features.md F4.3 — Confirm deck hand-off (lend)
 * @see docs/features.md F4.4 — Confirm deck return
 * @see docs/features.md F4.7 — Cancel a borrow request
 * @see docs/features.md F4.8 — Staff-delegated lending
 */
class BorrowControllerCoverageTest extends AbstractFunctionalTest
{
    // ---------------------------------------------------------------
    // F4.1 — Request: event not found (line 76)
    // ---------------------------------------------------------------

    public function testRequestBorrowWithNonExistentEventReturns404(): void
    {
        $this->loginAs('borrower@example.com');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => 999999,
            'deck_id' => 1,
            '_token' => 'irrelevant',
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    // ---------------------------------------------------------------
    // F4.1 — Request: deck not found (lines 87-89)
    // ---------------------------------------------------------------

    public function testRequestBorrowWithNonExistentDeckShowsError(): void
    {
        $this->loginAs('borrower@example.com');

        $event = $this->getFixtureEvent();

        // Get a valid CSRF token from the available decks page
        $crawler = $this->client->request('GET', \sprintf('/event/%d/decks', $event->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/borrow/request"]')->first();
        $csrfToken = $form->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/borrow/request', [
            'event_id' => $event->getId(),
            'deck_id' => 999999,
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $event->getId()));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // F4.2 — Approve: exception catch block (lines 124-125)
    // ---------------------------------------------------------------

    public function testApproveAlreadyApprovedBorrowShowsError(): void
    {
        $this->loginAs('admin@example.com');

        // Get a pending borrow and extract the CSRF token from the approve form
        $borrow = $this->getPendingBorrow();
        $borrowId = $borrow->getId();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrowId));
        self::assertResponseIsSuccessful();
        $approveForm = $crawler->filter(\sprintf('form[action="/borrow/%d/approve"]', $borrowId));
        $csrfToken = $approveForm->filter('input[name="_token"]')->attr('value');

        // Transition borrow to Approved so the workflow transition "approve" fails
        $entityManager = $this->getEntityManager();
        /** @var Borrow $freshBorrow */
        $freshBorrow = $entityManager->find(Borrow::class, $borrowId);
        $freshBorrow->setStatus(BorrowStatus::Approved);
        $freshBorrow->setApprovedAt(new \DateTimeImmutable());
        $entityManager->flush();

        // POST with valid CSRF — the service will throw because approve transition is invalid
        $this->client->request('POST', \sprintf('/borrow/%d/approve', $borrowId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrowId));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // F4.2 — Deny: invalid CSRF token (lines 138, 140)
    // ---------------------------------------------------------------

    public function testDenyWithInvalidCsrfTokenShowsError(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->getPendingBorrow();

        $this->client->request('POST', \sprintf('/borrow/%d/deny', $borrow->getId()), [
            '_token' => 'invalid-csrf-token',
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    // ---------------------------------------------------------------
    // F4.2 — Deny: exception catch block (lines 149-150)
    // ---------------------------------------------------------------

    public function testDenyAlreadyCancelledBorrowShowsError(): void
    {
        $this->loginAs('admin@example.com');

        // Get a pending borrow and extract the CSRF token from the deny form
        $borrow = $this->getPendingBorrow();
        $borrowId = $borrow->getId();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrowId));
        $denyForm = $crawler->filter(\sprintf('form[action="/borrow/%d/deny"]', $borrowId));
        $csrfToken = $denyForm->filter('input[name="_token"]')->attr('value');

        // Cancel the borrow directly so the deny transition fails
        $entityManager = $this->getEntityManager();
        /** @var Borrow $freshBorrow */
        $freshBorrow = $entityManager->find(Borrow::class, $borrowId);
        $freshBorrow->setStatus(BorrowStatus::Cancelled);
        $freshBorrow->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/borrow/%d/deny', $borrowId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrowId));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // F4.3 — Hand-off: exception catch block (lines 174-175)
    // ---------------------------------------------------------------

    public function testHandOffPendingBorrowShowsError(): void
    {
        $this->loginAs('admin@example.com');

        // Get an approved borrow — hand-off form is visible on approved borrows
        $borrow = $this->getApprovedBorrow();
        $borrowId = $borrow->getId();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrowId));
        $handOffForm = $crawler->filter(\sprintf('form[action="/borrow/%d/hand-off"]', $borrowId));
        $csrfToken = $handOffForm->filter('input[name="_token"]')->attr('value');

        // Revert borrow to Pending so the hand_off transition fails
        $entityManager = $this->getEntityManager();
        /** @var Borrow $freshBorrow */
        $freshBorrow = $entityManager->find(Borrow::class, $borrowId);
        $freshBorrow->setStatus(BorrowStatus::Pending);
        $freshBorrow->setApprovedAt(null);
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/borrow/%d/hand-off', $borrowId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrowId));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // F4.4 — Return: exception catch block (lines 199-200)
    // ---------------------------------------------------------------

    public function testReturnApprovedBorrowShowsError(): void
    {
        $this->loginAs('admin@example.com');

        // Transition to lent, get the return form CSRF, then revert to Approved
        $borrow = $this->getApprovedBorrow();
        $borrowId = $borrow->getId();
        $this->transitionToLent($borrow);

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrowId));
        $returnForm = $crawler->filter(\sprintf('form[action="/borrow/%d/return"]', $borrowId));
        $csrfToken = $returnForm->filter('input[name="_token"]')->attr('value');

        // Revert borrow to Approved so the return transition fails
        $entityManager = $this->getEntityManager();
        /** @var Borrow $freshBorrow */
        $freshBorrow = $entityManager->find(Borrow::class, $borrowId);
        $freshBorrow->setStatus(BorrowStatus::Approved);
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/borrow/%d/return', $borrowId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrowId));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // F4.7 — Cancel: exception catch block (lines 224-225)
    // ---------------------------------------------------------------

    public function testCancelLentBorrowByOwnerShowsError(): void
    {
        $this->loginAs('admin@example.com');

        // Get a pending borrow — cancel form is visible — extract CSRF
        $borrow = $this->getPendingBorrow();
        $borrowId = $borrow->getId();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrowId));
        $cancelForm = $crawler->filter(\sprintf('form[action="/borrow/%d/cancel"]', $borrowId));
        $csrfToken = $cancelForm->filter('input[name="_token"]')->attr('value');

        // Transition to Lent so the cancel transition fails
        $entityManager = $this->getEntityManager();
        /** @var Borrow $freshBorrow */
        $freshBorrow = $entityManager->find(Borrow::class, $borrowId);
        $freshBorrow->setStatus(BorrowStatus::Lent);
        $freshBorrow->setHandedOffAt(new \DateTimeImmutable());
        $freshBorrow->getDeck()->setStatus(DeckStatus::Lent);
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/borrow/%d/cancel', $borrowId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrowId));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // F4.8 — Return to owner: invalid CSRF token (lines 238, 240)
    // ---------------------------------------------------------------

    public function testReturnToOwnerWithInvalidCsrfTokenShowsError(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->createReturnedDelegatedBorrow();

        $this->client->request('POST', \sprintf('/borrow/%d/return-to-owner', $borrow->getId()), [
            '_token' => 'invalid-csrf-token',
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrow->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid security token.');
    }

    // ---------------------------------------------------------------
    // F4.8 — Return to owner: exception catch block (lines 249-250)
    // ---------------------------------------------------------------

    public function testReturnToOwnerOnNonReturnedBorrowShowsError(): void
    {
        $this->loginAs('admin@example.com');

        // Create a delegated returned borrow — return-to-owner form is visible
        $borrow = $this->createReturnedDelegatedBorrow();
        $borrowId = $borrow->getId();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrowId));
        $returnToOwnerForm = $crawler->filter(\sprintf('form[action="/borrow/%d/return-to-owner"]', $borrowId));
        $csrfToken = $returnToOwnerForm->filter('input[name="_token"]')->attr('value');

        // Revert to Pending so the return_to_owner transition fails
        $entityManager = $this->getEntityManager();
        /** @var Borrow $freshBorrow */
        $freshBorrow = $entityManager->find(Borrow::class, $borrowId);
        $freshBorrow->setStatus(BorrowStatus::Pending);
        $freshBorrow->setReturnedAt(null);
        $freshBorrow->setHandedOffAt(null);
        $freshBorrow->setApprovedAt(null);
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/borrow/%d/return-to-owner', $borrowId), [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects(\sprintf('/borrow/%d', $borrowId));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    // ---------------------------------------------------------------
    // Redirect paths: lends list and event page
    // ---------------------------------------------------------------

    public function testApproveWithLendsRedirectGoesToLendList(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->getPendingBorrow();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $approveForm = $crawler->filter(\sprintf('form[action="/borrow/%d/approve"]', $borrow->getId()));
        $csrfToken = $approveForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/approve', $borrow->getId()), [
            '_token' => $csrfToken,
            'redirect_to' => 'lends',
            'redirect_scope' => 'pending',
        ]);

        self::assertResponseRedirects('/lends?scope=pending');
    }

    public function testDenyWithEventRedirectGoesToEventPage(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->getPendingBorrow();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $denyForm = $crawler->filter(\sprintf('form[action="/borrow/%d/deny"]', $borrow->getId()));
        $csrfToken = $denyForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/deny', $borrow->getId()), [
            '_token' => $csrfToken,
            'redirect_to' => 'event',
        ]);

        self::assertResponseRedirects(\sprintf('/event/%d', $borrow->getEvent()->getId()));
    }

    public function testCancelWithLendsRedirectAndNoScopeGoesToLendList(): void
    {
        $this->loginAs('borrower@example.com');

        $borrow = $this->getPendingBorrow();

        $crawler = $this->client->request('GET', \sprintf('/borrow/%d', $borrow->getId()));
        $cancelForm = $crawler->filter(\sprintf('form[action="/borrow/%d/cancel"]', $borrow->getId()));
        $csrfToken = $cancelForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/borrow/%d/cancel', $borrow->getId()), [
            '_token' => $csrfToken,
            'redirect_to' => 'lends',
        ]);

        self::assertResponseRedirects('/lends');
    }

    // ---------------------------------------------------------------
    // F4.4 — Return overdue borrow (covers return_overdue transition)
    // ---------------------------------------------------------------

    public function testReturnOverdueBorrowSetsReturnedAndDeckAvailable(): void
    {
        $this->loginAs('admin@example.com');

        $borrow = $this->getApprovedBorrow();
        $this->transitionToOverdue($borrow);

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

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function getFixtureEvent(): Event
    {
        /** @var EventRepository $repository */
        $repository = static::getContainer()->get(EventRepository::class);
        $event = $repository->findOneBy(['name' => 'Expanded Weekly #42']);
        self::assertNotNull($event);

        return $event;
    }

    private function getPendingBorrow(): Borrow
    {
        /** @var BorrowRepository $repository */
        $repository = static::getContainer()->get(BorrowRepository::class);
        $borrow = $repository->findOneBy(['status' => BorrowStatus::Pending->value]);
        self::assertNotNull($borrow);

        return $borrow;
    }

    private function getApprovedBorrow(): Borrow
    {
        /** @var BorrowRepository $repository */
        $repository = static::getContainer()->get(BorrowRepository::class);
        $borrow = $repository->findOneBy(['status' => BorrowStatus::Approved->value]);
        self::assertNotNull($borrow);

        return $borrow;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function refetchBorrow(int $identifier): Borrow
    {
        /** @var BorrowRepository $repository */
        $repository = static::getContainer()->get(BorrowRepository::class);
        $borrow = $repository->find($identifier);
        self::assertNotNull($borrow);

        return $borrow;
    }

    private function transitionToLent(Borrow $borrow): void
    {
        $entityManager = $this->getEntityManager();
        $borrow->setStatus(BorrowStatus::Lent);
        $borrow->setHandedOffAt(new \DateTimeImmutable());
        $borrow->getDeck()->setStatus(DeckStatus::Lent);
        $entityManager->flush();
    }

    private function transitionToOverdue(Borrow $borrow): void
    {
        $entityManager = $this->getEntityManager();
        $borrow->setStatus(BorrowStatus::Overdue);
        $borrow->setHandedOffAt(new \DateTimeImmutable('-3 days'));
        $borrow->getDeck()->setStatus(DeckStatus::Lent);
        $entityManager->flush();
    }

    /**
     * Creates a delegated borrow in Returned status, ready for return-to-owner action.
     * Uses Iron Thorns (owned by admin) at the fixture event.
     */
    private function createReturnedDelegatedBorrow(): Borrow
    {
        $entityManager = $this->getEntityManager();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $borrower = $userRepository->findOneBy(['email' => 'borrower@example.com']);
        self::assertNotNull($borrower);

        $event = $this->getFixtureEvent();

        /** @var DeckRepository $deckRepository */
        $deckRepository = static::getContainer()->get(DeckRepository::class);
        $deck = $deckRepository->findOneBy(['name' => 'Iron Thorns']);
        self::assertNotNull($deck);

        $currentVersion = $deck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($currentVersion);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($event);
        $borrow->setStatus(BorrowStatus::Returned);
        $borrow->setApprovedAt(new \DateTimeImmutable('-1 hour'));
        $borrow->setHandedOffAt(new \DateTimeImmutable('-30 minutes'));
        $borrow->setReturnedAt(new \DateTimeImmutable());
        $borrow->setIsDelegatedToStaff(true);

        $entityManager->persist($borrow);
        $entityManager->flush();

        return $borrow;
    }
}
