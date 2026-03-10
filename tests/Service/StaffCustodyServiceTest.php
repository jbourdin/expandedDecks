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

namespace App\Tests\Service;

use App\Entity\Borrow;
use App\Entity\Deck;
use App\Entity\DeckVersion;
use App\Entity\Event;
use App\Entity\EventDeckRegistration;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Repository\BorrowRepository;
use App\Service\StaffCustodyService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
class StaffCustodyServiceTest extends TestCase
{
    private BorrowRepository&Stub $borrowRepository;
    private WorkflowInterface&Stub $workflow;

    protected function setUp(): void
    {
        $this->borrowRepository = $this->createStub(BorrowRepository::class);
        $this->workflow = $this->createStub(WorkflowInterface::class);
    }

    /**
     * Create the service with a stub EntityManager (no expectations needed).
     */
    private function createServiceWithStub(): StaffCustodyService
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);

        return new StaffCustodyService($entityManager, $this->borrowRepository, $this->workflow);
    }

    /**
     * Create the service with a mock EntityManager for asserting flush/persist calls.
     *
     * @return array{StaffCustodyService, EntityManagerInterface&MockObject}
     */
    private function createServiceWithMock(): array
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new StaffCustodyService($entityManager, $this->borrowRepository, $this->workflow);

        return [$service, $entityManager];
    }

    // ---------------------------------------------------------------
    // confirmOwnerHandover
    // ---------------------------------------------------------------

    public function testConfirmOwnerHandoverThrowsWhenNotDelegated(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not delegated to staff');

        $service->confirmOwnerHandover($registration, $owner);
    }

    public function testConfirmOwnerHandoverThrowsWhenAlreadyHandedOver(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already been handed over');

        $service->confirmOwnerHandover($registration, $owner);
    }

    public function testConfirmOwnerHandoverThrowsWhenNotOwner(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $notOwner = $this->createUserWithId(2);
        $registration = $this->createRegistration($owner, delegated: true);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Only the deck owner');

        $service->confirmOwnerHandover($registration, $notOwner);
    }

    public function testConfirmOwnerHandoverSetsTimestampAndFlushes(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);

        $entityManager->expects(self::once())->method('flush');

        $service->confirmOwnerHandover($registration, $owner);

        self::assertNotNull($registration->getStaffReceivedAt());
        self::assertSame($owner, $registration->getStaffReceivedBy());
    }

    // ---------------------------------------------------------------
    // confirmStaffReturn
    // ---------------------------------------------------------------

    public function testConfirmStaffReturnThrowsWhenNotReceivedYet(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not been handed over to staff yet');

        $service->confirmStaffReturn($registration, $owner);
    }

    public function testConfirmStaffReturnThrowsWhenAlreadyReturned(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReturnedAt(new \DateTimeImmutable());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already been returned');

        $service->confirmStaffReturn($registration, $owner);
    }

    public function testConfirmStaffReturnThrowsWhenNotOrganizerOrStaff(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $outsider = $this->createUserWithId(99);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')->willReturn([]);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Only organizers or staff');

        $service->confirmStaffReturn($registration, $outsider);
    }

    public function testConfirmStaffReturnThrowsWhenDeckIsLent(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $lentBorrow = $this->createBorrowWithStatus(BorrowStatus::Lent, $registration);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$lentBorrow]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot return deck to owner');

        $service->confirmStaffReturn($registration, $owner);
    }

    public function testConfirmStaffReturnThrowsWhenDeckIsOverdue(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $overdueBorrow = $this->createBorrowWithStatus(BorrowStatus::Overdue, $registration);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$overdueBorrow]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot return deck to owner');

        $service->confirmStaffReturn($registration, $owner);
    }

    public function testConfirmStaffReturnAutoClosesReturnedBorrow(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $returnedBorrow = $this->createBorrowWithStatus(BorrowStatus::Returned, $registration);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$returnedBorrow]);

        $this->workflow->method('apply')->willReturn(new Marking());
        $entityManager->expects(self::once())->method('flush');

        $service->confirmStaffReturn($registration, $owner);

        self::assertNotNull($registration->getStaffReturnedAt());
        self::assertNotNull($returnedBorrow->getReturnedToOwnerAt());
    }

    public function testConfirmStaffReturnAutoClosesPendingBorrow(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $pendingBorrow = $this->createBorrowWithStatus(BorrowStatus::Pending, $registration);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$pendingBorrow]);

        $this->workflow->method('apply')->willReturn(new Marking());
        $entityManager->expects(self::once())->method('flush');

        $service->confirmStaffReturn($registration, $owner);

        self::assertNotNull($registration->getStaffReturnedAt());
        self::assertNotNull($pendingBorrow->getCancelledAt());
        self::assertSame($owner, $pendingBorrow->getCancelledBy());
    }

    public function testConfirmStaffReturnAutoClosesApprovedBorrow(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $approvedBorrow = $this->createBorrowWithStatus(BorrowStatus::Approved, $registration);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$approvedBorrow]);

        $this->workflow->method('apply')->willReturn(new Marking());
        $entityManager->expects(self::once())->method('flush');

        $service->confirmStaffReturn($registration, $owner);

        self::assertNotNull($registration->getStaffReturnedAt());
        self::assertNotNull($approvedBorrow->getCancelledAt());
        self::assertSame($owner, $approvedBorrow->getCancelledBy());
    }

    public function testConfirmStaffReturnWithNoBorrowsSucceeds(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([]);

        $entityManager->expects(self::once())->method('flush');

        $service->confirmStaffReturn($registration, $owner);

        self::assertNotNull($registration->getStaffReturnedAt());
        self::assertSame($owner, $registration->getStaffReturnedBy());
    }

    // ---------------------------------------------------------------
    // ownerReclaimDeck
    // ---------------------------------------------------------------

    public function testOwnerReclaimThrowsWhenNotOwner(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $notOwner = $this->createUserWithId(2);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Only the deck owner');

        $service->ownerReclaimDeck($registration, $notOwner);
    }

    public function testOwnerReclaimThrowsWhenNotReceivedYet(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not been handed over to staff yet');

        $service->ownerReclaimDeck($registration, $owner);
    }

    public function testOwnerReclaimThrowsWhenAlreadyReturned(): void
    {
        $service = $this->createServiceWithStub();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReturnedAt(new \DateTimeImmutable());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already been returned');

        $service->ownerReclaimDeck($registration, $owner);
    }

    public function testOwnerReclaimClosesOverdueBorrow(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $overdueBorrow = $this->createBorrowWithStatus(BorrowStatus::Overdue, $registration);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$overdueBorrow]);

        $this->workflow->method('apply')->willReturn(new Marking());
        $entityManager->expects(self::once())->method('flush');

        $service->ownerReclaimDeck($registration, $owner);

        self::assertNotNull($registration->getStaffReturnedAt());
        self::assertNotNull($overdueBorrow->getReturnedAt());
        self::assertNotNull($overdueBorrow->getReturnedToOwnerAt());
    }

    public function testOwnerReclaimClosesReturnedBorrow(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $returnedBorrow = $this->createBorrowWithStatus(BorrowStatus::Returned, $registration);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$returnedBorrow]);

        $this->workflow->method('apply')->willReturn(new Marking());
        $entityManager->expects(self::once())->method('flush');

        $service->ownerReclaimDeck($registration, $owner);

        self::assertNotNull($returnedBorrow->getReturnedToOwnerAt());
    }

    public function testOwnerReclaimWithNoBorrowsSucceeds(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([]);

        $entityManager->expects(self::once())->method('flush');

        $service->ownerReclaimDeck($registration, $owner);

        self::assertNotNull($registration->getStaffReturnedAt());
        self::assertSame($owner, $registration->getStaffReturnedBy());
    }

    public function testOwnerReclaimNotifiesBorrowerWhenLent(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $borrower = $this->createUserWithId(2);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $lentBorrow = $this->createBorrowWithStatus(BorrowStatus::Lent, $registration, $borrower);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$lentBorrow]);

        $this->workflow->method('apply')->willReturn(new Marking());

        // Notification should be persisted (borrower has default prefs = enabled)
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $service->ownerReclaimDeck($registration, $owner);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createUserWithId(int $id): User
    {
        $user = new User();
        $user->setEmail(\sprintf('user%d@example.com', $id));
        $user->setScreenName(\sprintf('User%d', $id));
        $user->setFirstName('Test');
        $user->setLastName('User');

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }

    private function createRegistration(User $owner, bool $delegated): EventDeckRegistration
    {
        $deck = new Deck();
        $deck->setName('Test Deck');
        $deck->setOwner($owner);
        $deckReflection = new \ReflectionProperty(Deck::class, 'id');
        $deckReflection->setValue($deck, 100);

        $event = new Event();
        $event->setName('Test Event');
        $event->setOrganizer($owner);
        $eventReflection = new \ReflectionProperty(Event::class, 'id');
        $eventReflection->setValue($event, 1);

        $registration = new EventDeckRegistration();
        $registration->setEvent($event);
        $registration->setDeck($deck);
        $registration->setDelegateToStaff($delegated);

        return $registration;
    }

    private function createBorrowWithStatus(BorrowStatus $status, EventDeckRegistration $registration, ?User $borrower = null): Borrow
    {
        $borrower ??= $this->createUserWithId(10);
        $deck = $registration->getDeck();

        $version = new DeckVersion();
        $version->setDeck($deck);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($version);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($registration->getEvent());
        $borrow->setStatus($status);

        $borrowReflection = new \ReflectionProperty(Borrow::class, 'id');
        $borrowReflection->setValue($borrow, random_int(1, 10000));

        return $borrow;
    }
}
