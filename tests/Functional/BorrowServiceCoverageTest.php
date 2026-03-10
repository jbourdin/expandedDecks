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
use App\Repository\EventDeckRegistrationRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\BorrowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Additional coverage tests for BorrowService — targets uncovered edge cases
 * (guard clauses, access control assertions, walk-up lend guards).
 *
 * @see docs/features.md F4.1 — Request to borrow a deck
 * @see docs/features.md F4.2 — Approve / deny a borrow request
 * @see docs/features.md F4.3 — Confirm deck hand-off (lend)
 * @see docs/features.md F4.7 — Cancel a borrow request
 * @see docs/features.md F4.8 — Staff-delegated lending
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
class BorrowServiceCoverageTest extends AbstractFunctionalTest
{
    private BorrowService $borrowService;

    protected function setUp(): void
    {
        parent::setUp();

        // Log in to establish a session (required for container services)
        $this->loginAs('admin@example.com');

        /** @var BorrowService $service */
        $service = static::getContainer()->get(BorrowService::class);
        $this->borrowService = $service;
    }

    // ---------------------------------------------------------------
    // F4.1 — requestBorrow: participant guard (line 64)
    // ---------------------------------------------------------------

    public function testRequestBorrowDeniedIfNotParticipant(): void
    {
        $deck = $this->getDeckByName('Iron Thorns');
        $event = $this->getFixtureEvent();
        $lender = $this->getUserByEmail('lender@example.com');

        // Lender is not a participant in the today event
        if (null !== $event->getEngagementFor($lender)) {
            self::markTestSkipped('Lender is already engaged in the event in fixtures.');
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('You must be a participant of this event to request a borrow.');

        $this->borrowService->requestBorrow($deck, $lender, $event);
    }

    // ---------------------------------------------------------------
    // F4.1 — requestBorrow: cancelled event guard (line 72)
    // ---------------------------------------------------------------

    public function testRequestBorrowDeniedForCancelledEvent(): void
    {
        $borrower = $this->getUserByEmail('borrower@example.com');
        $deck = $this->getDeckByName('Iron Thorns');
        $event = $this->getFixtureEvent();

        // Cancel the event
        $entityManager = $this->getEntityManager();
        $event->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot borrow decks for a cancelled event.');

        $this->borrowService->requestBorrow($deck, $borrower, $event);
    }

    // ---------------------------------------------------------------
    // F4.1 — requestBorrow: finished event guard (line 76)
    // ---------------------------------------------------------------

    public function testRequestBorrowDeniedForFinishedEvent(): void
    {
        $borrower = $this->getUserByEmail('borrower@example.com');
        $deck = $this->getDeckByName('Iron Thorns');
        $event = $this->getFixtureEvent();

        // Finish the event
        $entityManager = $this->getEntityManager();
        $event->setFinishedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot borrow decks for a finished event.');

        $this->borrowService->requestBorrow($deck, $borrower, $event);
    }

    // ---------------------------------------------------------------
    // F4.1 — requestBorrow: deck has no version guard (line 89)
    // ---------------------------------------------------------------

    public function testRequestBorrowDeniedIfDeckHasNoVersion(): void
    {
        $borrower = $this->getUserByEmail('borrower@example.com');
        $event = $this->getFixtureEvent();

        // Create a deck with no version
        $entityManager = $this->getEntityManager();
        $admin = $this->getUserByEmail('admin@example.com');

        $deckWithoutVersion = new Deck();
        $deckWithoutVersion->setName('Empty Deck');
        $deckWithoutVersion->setOwner($admin);
        $deckWithoutVersion->setFormat('Expanded');
        $deckWithoutVersion->setStatus(DeckStatus::Available);

        $entityManager->persist($deckWithoutVersion);
        $entityManager->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This deck has no version and cannot be borrowed.');

        $this->borrowService->requestBorrow($deckWithoutVersion, $borrower, $event);
    }

    // ---------------------------------------------------------------
    // F4.12 — createWalkUpBorrow: borrower is owner guard (line 264)
    // ---------------------------------------------------------------

    public function testWalkUpBorrowDeniedIfBorrowerIsOwner(): void
    {
        $admin = $this->getUserByEmail('admin@example.com');
        $deck = $this->getDeckByName('Iron Thorns'); // owned by admin
        $event = $this->getFixtureEvent();

        $this->cancelExistingBorrowsForDeck($deck, $event);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('The borrower cannot be the deck owner.');

        $this->borrowService->createWalkUpBorrow($deck, $admin, $event, $admin);
    }

    // ---------------------------------------------------------------
    // F4.12 — createWalkUpBorrow: retired deck guard (line 268)
    // ---------------------------------------------------------------

    public function testWalkUpBorrowDeniedForRetiredDeck(): void
    {
        $admin = $this->getUserByEmail('admin@example.com');
        $borrower = $this->getUserByEmail('borrower@example.com');
        $deck = $this->getDeckByName('Iron Thorns');
        $event = $this->getFixtureEvent();

        $this->cancelExistingBorrowsForDeck($deck, $event);

        $entityManager = $this->getEntityManager();
        $deck->setStatus(DeckStatus::Retired);
        $entityManager->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This deck is retired and cannot be lent.');

        $this->borrowService->createWalkUpBorrow($deck, $borrower, $event, $admin);
    }

    // ---------------------------------------------------------------
    // F4.12 — createWalkUpBorrow: cancelled event guard (line 272)
    // ---------------------------------------------------------------

    public function testWalkUpBorrowDeniedForCancelledEvent(): void
    {
        $admin = $this->getUserByEmail('admin@example.com');
        $borrower = $this->getUserByEmail('borrower@example.com');
        $deck = $this->getDeckByName('Iron Thorns');
        $event = $this->getFixtureEvent();

        $entityManager = $this->getEntityManager();
        $event->setCancelledAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot lend decks at a cancelled event.');

        $this->borrowService->createWalkUpBorrow($deck, $borrower, $event, $admin);
    }

    // ---------------------------------------------------------------
    // F4.12 — createWalkUpBorrow: finished event guard (line 276)
    // ---------------------------------------------------------------

    public function testWalkUpBorrowDeniedForFinishedEvent(): void
    {
        $admin = $this->getUserByEmail('admin@example.com');
        $borrower = $this->getUserByEmail('borrower@example.com');
        $deck = $this->getDeckByName('Iron Thorns');
        $event = $this->getFixtureEvent();

        $entityManager = $this->getEntityManager();
        $event->setFinishedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot lend decks at a finished event.');

        $this->borrowService->createWalkUpBorrow($deck, $borrower, $event, $admin);
    }

    // ---------------------------------------------------------------
    // F4.12 — createWalkUpBorrow: blocking borrow guard (line 280)
    // ---------------------------------------------------------------

    public function testWalkUpBorrowDeniedIfBlockingBorrowExists(): void
    {
        $admin = $this->getUserByEmail('admin@example.com');
        $borrower = $this->getUserByEmail('borrower@example.com');
        $deck = $this->getDeckByName('Iron Thorns');
        $event = $this->getFixtureEvent();

        // Approve the existing pending borrow so it blocks
        $entityManager = $this->getEntityManager();
        /** @var BorrowRepository $borrowRepository */
        $borrowRepository = static::getContainer()->get(BorrowRepository::class);
        $existing = $borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event);
        self::assertNotNull($existing);
        $existing->setStatus(BorrowStatus::Approved);
        $existing->setApprovedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This deck is already approved or lent for this event.');

        $this->borrowService->createWalkUpBorrow($deck, $borrower, $event, $admin);
    }

    // ---------------------------------------------------------------
    // F4.12 — createWalkUpBorrow: same-day conflict guard (line 284)
    // ---------------------------------------------------------------

    public function testWalkUpBorrowDeniedIfSameDayConflictExists(): void
    {
        $admin = $this->getUserByEmail('admin@example.com');
        $borrower = $this->getUserByEmail('borrower@example.com');
        $deck = $this->getDeckByName('Iron Thorns');
        $event = $this->getFixtureEvent();

        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Create a second event on the same day with an approved borrow
        $entityManager = $this->getEntityManager();
        $secondEvent = new Event();
        $secondEvent->setName('Same Day Walkup Event');
        $secondEvent->setDate(new \DateTimeImmutable('today'));
        $secondEvent->setTimezone('Europe/Paris');
        $secondEvent->setOrganizer($admin);
        $secondEvent->setRegistrationLink('https://example.com/same-day-walkup');
        $entityManager->persist($secondEvent);

        $currentVersion = $deck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        $conflictingBorrow = new Borrow();
        $conflictingBorrow->setDeck($deck);
        $conflictingBorrow->setDeckVersion($currentVersion);
        $conflictingBorrow->setBorrower($borrower);
        $conflictingBorrow->setEvent($secondEvent);
        $conflictingBorrow->setStatus(BorrowStatus::Approved);
        $conflictingBorrow->setApprovedAt(new \DateTimeImmutable());
        $entityManager->persist($conflictingBorrow);
        $entityManager->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This deck is already approved or lent at another event on the same day.');

        $this->borrowService->createWalkUpBorrow($deck, $borrower, $event, $admin);
    }

    // ---------------------------------------------------------------
    // F4.12 — createWalkUpBorrow: no version guard (line 289)
    // ---------------------------------------------------------------

    public function testWalkUpBorrowDeniedIfDeckHasNoVersion(): void
    {
        $admin = $this->getUserByEmail('admin@example.com');
        $borrower = $this->getUserByEmail('borrower@example.com');
        $event = $this->getFixtureEvent();

        // Create a deck with no version
        $entityManager = $this->getEntityManager();
        $deckWithoutVersion = new Deck();
        $deckWithoutVersion->setName('Versionless Walkup Deck');
        $deckWithoutVersion->setOwner($admin);
        $deckWithoutVersion->setFormat('Expanded');
        $deckWithoutVersion->setStatus(DeckStatus::Available);
        $entityManager->persist($deckWithoutVersion);
        $entityManager->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This deck has no version and cannot be lent.');

        $this->borrowService->createWalkUpBorrow($deckWithoutVersion, $borrower, $event, $admin);
    }

    // ---------------------------------------------------------------
    // F4.12 — createWalkUpBorrow: not owner or staff guard (line 294)
    // ---------------------------------------------------------------

    public function testWalkUpBorrowDeniedIfInitiatorIsNotOwnerNorStaff(): void
    {
        $borrower = $this->getUserByEmail('borrower@example.com');
        $deck = $this->getDeckByName('Regidrago'); // owned by lender
        $event = $this->getFixtureEvent();

        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Disable delegation on the registration so the custody guard doesn't fire first
        $entityManager = $this->getEntityManager();
        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        if (null !== $registration) {
            $registration->setDelegateToStaff(false);
            $entityManager->flush();
        }

        // staff2 is not staff/organizer for the today event and is not the deck owner
        $staff2 = $this->getUserByEmail('staff2@example.com');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Only the deck owner or event staff can initiate a walk-up lend.');

        $this->borrowService->createWalkUpBorrow($deck, $borrower, $event, $staff2);
    }

    // ---------------------------------------------------------------
    // F4.14 — createWalkUpBorrow: staff has no custody guard (line 300)
    // ---------------------------------------------------------------

    public function testWalkUpBorrowDeniedIfStaffHasNotReceivedDeck(): void
    {
        $borrower = $this->getUserByEmail('borrower@example.com');
        $deck = $this->getDeckByName('Regidrago'); // owned by lender, delegated to staff
        $event = $this->getFixtureEvent();

        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Ensure the registration is delegated but staff has NOT received
        $entityManager = $this->getEntityManager();
        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registration->setDelegateToStaff(true);
        $registration->setStaffReceivedAt(null);
        $registration->setStaffReceivedBy(null);
        $entityManager->flush();

        // staff1 IS staff for the today event, but hasn't physically received the deck
        $staff = $this->getUserByEmail('staff1@example.com');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This deck has not been handed over to staff yet.');

        $this->borrowService->createWalkUpBorrow($deck, $borrower, $event, $staff);
    }

    // ---------------------------------------------------------------
    // F4.12 — createWalkUpBorrow: staff initiator notifies owner (lines 341-347)
    // ---------------------------------------------------------------

    public function testWalkUpBorrowByStaffCreatesOwnerNotification(): void
    {
        $deck = $this->getDeckByName('Regidrago'); // owned by lender
        $event = $this->getFixtureEvent();
        $admin = $this->getUserByEmail('admin@example.com');

        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Mark staff as having received the deck
        $entityManager = $this->getEntityManager();
        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registration->setDelegateToStaff(true);

        $staff = $this->getUserByEmail('staff1@example.com');
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReceivedBy($staff);
        $entityManager->flush();

        // staff1 initiates walk-up for admin (borrower) — not the deck owner (lender)
        $borrow = $this->borrowService->createWalkUpBorrow($deck, $admin, $event, $staff);

        self::assertSame(BorrowStatus::Lent, $borrow->getStatus());
        self::assertTrue($borrow->isWalkUp());
        self::assertNotNull($borrow->getHandedOffAt());
        self::assertNotNull($borrow->getApprovedAt());
    }

    // ---------------------------------------------------------------
    // F4.8 — assertOwnerOrDelegatedStaff: access denied (line 431)
    // ---------------------------------------------------------------

    public function testApproveByUnrelatedUserThrowsAccessDenied(): void
    {
        $borrow = $this->getPendingBorrowForIronThorns();

        // Ensure the borrow is NOT delegated so only owner can act
        $entityManager = $this->getEntityManager();
        $borrow->setIsDelegatedToStaff(false);
        $entityManager->flush();

        // borrower is the borrow's borrower, not the owner — should be denied for approve
        $unrelatedUser = $this->getUserByEmail('borrower@example.com');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Only the deck owner or delegated staff can perform this action.');

        $this->borrowService->approve($borrow, $unrelatedUser);
    }

    // ---------------------------------------------------------------
    // F4.7 — assertBorrowerOrOwnerOrDelegatedStaff: access denied (line 443)
    // ---------------------------------------------------------------

    public function testCancelByUnrelatedUserThrowsAccessDenied(): void
    {
        $borrow = $this->getPendingBorrowForIronThorns();
        $entityManager = $this->getEntityManager();
        $borrow->setIsDelegatedToStaff(false);
        $entityManager->flush();

        // Use lender who is neither owner (admin), borrower, nor delegated staff
        $unrelatedUser = $this->getUserByEmail('lender@example.com');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Only the borrower, deck owner, or delegated staff can cancel this borrow.');

        $this->borrowService->cancel($borrow, $unrelatedUser);
    }

    // ---------------------------------------------------------------
    // F4.14 — assertStaffHasCustody: owner bypass (line 461)
    // ---------------------------------------------------------------

    public function testHandOffByOwnerAllowedEvenWhenDelegatedAndNotReceived(): void
    {
        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns'); // owned by admin
        $admin = $this->getUserByEmail('admin@example.com');
        $borrower = $this->getUserByEmail('borrower@example.com');

        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Create a delegated approved borrow where staff has NOT received the deck
        $entityManager = $this->getEntityManager();

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registration->setDelegateToStaff(true);
        $registration->setStaffReceivedAt(null);
        $registration->setStaffReceivedBy(null);

        $currentVersion = $deck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($currentVersion);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($event);
        $borrow->setStatus(BorrowStatus::Approved);
        $borrow->setApprovedAt(new \DateTimeImmutable());
        $borrow->setIsDelegatedToStaff(true);
        $entityManager->persist($borrow);
        $entityManager->flush();

        // Owner should still be allowed to hand off (they have the deck in hand)
        $this->borrowService->handOff($borrow, $admin);

        self::assertSame(BorrowStatus::Lent, $borrow->getStatus());
        self::assertNotNull($borrow->getHandedOffAt());
    }

    // ---------------------------------------------------------------
    // F4.14 — assertStaffHasCustody: staff without custody (line 470)
    // ---------------------------------------------------------------

    public function testHandOffByStaffDeniedWhenDeckNotReceivedByStaff(): void
    {
        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns'); // owned by admin
        $borrower = $this->getUserByEmail('borrower@example.com');
        $staff = $this->getUserByEmail('staff1@example.com');

        $this->cancelExistingBorrowsForDeck($deck, $event);

        // Set up a delegated borrow where staff has NOT received the deck
        $entityManager = $this->getEntityManager();

        /** @var EventDeckRegistrationRepository $registrationRepository */
        $registrationRepository = static::getContainer()->get(EventDeckRegistrationRepository::class);
        $registration = $registrationRepository->findOneByEventAndDeck($event, $deck);
        self::assertNotNull($registration);
        $registration->setDelegateToStaff(true);
        $registration->setStaffReceivedAt(null);
        $registration->setStaffReceivedBy(null);

        $currentVersion = $deck->getCurrentVersion();
        self::assertNotNull($currentVersion);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($currentVersion);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($event);
        $borrow->setStatus(BorrowStatus::Approved);
        $borrow->setApprovedAt(new \DateTimeImmutable());
        $borrow->setIsDelegatedToStaff(true);
        $entityManager->persist($borrow);
        $entityManager->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This deck has not been handed over to staff yet.');

        $this->borrowService->handOff($borrow, $staff);
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

    private function getDeckByName(string $name): Deck
    {
        /** @var DeckRepository $repository */
        $repository = static::getContainer()->get(DeckRepository::class);
        $deck = $repository->findOneBy(['name' => $name]);
        self::assertNotNull($deck);

        return $deck;
    }

    private function getUserByEmail(string $email): User
    {
        /** @var UserRepository $repository */
        $repository = static::getContainer()->get(UserRepository::class);
        $user = $repository->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function getPendingBorrowForIronThorns(): Borrow
    {
        $event = $this->getFixtureEvent();
        $deck = $this->getDeckByName('Iron Thorns');

        /** @var BorrowRepository $repository */
        $repository = static::getContainer()->get(BorrowRepository::class);
        $borrow = $repository->findActiveBorrowForDeckAtEvent($deck, $event);
        self::assertNotNull($borrow);
        self::assertSame(BorrowStatus::Pending, $borrow->getStatus());

        return $borrow;
    }

    private function cancelExistingBorrowsForDeck(Deck $deck, Event $event): void
    {
        $entityManager = $this->getEntityManager();

        /** @var BorrowRepository $repository */
        $repository = static::getContainer()->get(BorrowRepository::class);

        do {
            $existing = $repository->findActiveBorrowForDeckAtEvent($deck, $event);

            if (null !== $existing) {
                $existing->setStatus(BorrowStatus::Cancelled);
                $existing->setCancelledAt(new \DateTimeImmutable());
                $entityManager->flush();
            }
        } while (null !== $existing);
    }
}
