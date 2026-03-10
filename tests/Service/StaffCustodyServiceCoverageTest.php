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
use App\Enum\NotificationType;
use App\Repository\BorrowRepository;
use App\Service\StaffCustodyService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Additional coverage for StaffCustodyService — uncovered branches.
 *
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
class StaffCustodyServiceCoverageTest extends TestCase
{
    private BorrowRepository&Stub $borrowRepository;
    private WorkflowInterface&Stub $workflow;

    protected function setUp(): void
    {
        $this->borrowRepository = $this->createStub(BorrowRepository::class);
        $this->workflow = $this->createStub(WorkflowInterface::class);
    }

    /**
     * @return array{StaffCustodyService, EntityManagerInterface&MockObject}
     */
    private function createServiceWithMock(): array
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new StaffCustodyService($entityManager, $this->borrowRepository, $this->workflow);

        return [$service, $entityManager];
    }

    /**
     * closeBorrowForReturn ignores borrows with unexpected statuses (default arm).
     */
    public function testConfirmStaffReturnIgnoresUnexpectedBorrowStatus(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        // A borrow with Cancelled status should hit the default => null arm
        $cancelledBorrow = $this->createBorrowWithStatus(BorrowStatus::Cancelled, $registration);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$cancelledBorrow]);

        $entityManager->expects(self::once())->method('flush');

        $service->confirmStaffReturn($registration, $owner);

        // The cancelled borrow should be untouched — no returnedToOwnerAt, no cancelledAt change
        self::assertNull($cancelledBorrow->getReturnedToOwnerAt());
        self::assertNotNull($registration->getStaffReturnedAt());
    }

    /**
     * closeBorrowForOwnerReclaim ignores borrows with unexpected statuses (default arm).
     */
    public function testOwnerReclaimIgnoresUnexpectedBorrowStatus(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        // ReturnedToOwner is terminal — should hit the default => null arm
        $terminalBorrow = $this->createBorrowWithStatus(BorrowStatus::ReturnedToOwner, $registration);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$terminalBorrow]);

        $entityManager->expects(self::once())->method('flush');

        $service->ownerReclaimDeck($registration, $owner);

        self::assertNotNull($registration->getStaffReturnedAt());
    }

    /**
     * createNotification skips persist when the borrower has in-app notifications disabled.
     */
    public function testOwnerReclaimSkipsNotificationWhenDisabled(): void
    {
        [$service, $entityManager] = $this->createServiceWithMock();
        $owner = $this->createUserWithId(1);
        $borrower = $this->createUserWithId(2);
        $borrower->setNotificationPreferences([
            NotificationType::BorrowReturned->value => ['inApp' => false, 'email' => false],
        ]);

        $registration = $this->createRegistration($owner, delegated: true);
        $registration->setStaffReceivedAt(new \DateTimeImmutable());

        $lentBorrow = $this->createBorrowWithStatus(BorrowStatus::Lent, $registration, $borrower);

        $this->borrowRepository->method('findOpenBorrowsForDeckAtEvent')
            ->willReturn([$lentBorrow]);

        $this->workflow->method('apply')->willReturn(new Marking());

        // persist should NOT be called (notification skipped)
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $service->ownerReclaimDeck($registration, $owner);

        self::assertNotNull($lentBorrow->getReturnedAt());
        self::assertNotNull($lentBorrow->getReturnedToOwnerAt());
    }

    /**
     * ownerReclaimDeck auto-closes an approved borrow (cancel_approved transition).
     */
    public function testOwnerReclaimClosesApprovedBorrow(): void
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

        $service->ownerReclaimDeck($registration, $owner);

        self::assertNotNull($approvedBorrow->getCancelledAt());
        self::assertSame($owner, $approvedBorrow->getCancelledBy());
    }

    /**
     * ownerReclaimDeck auto-closes a pending borrow (cancel_pending transition).
     */
    public function testOwnerReclaimClosesPendingBorrow(): void
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

        $service->ownerReclaimDeck($registration, $owner);

        self::assertNotNull($pendingBorrow->getCancelledAt());
        self::assertSame($owner, $pendingBorrow->getCancelledBy());
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
