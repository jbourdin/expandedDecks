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
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Enum\NotificationType;
use App\Repository\BorrowRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Service\BorrowNotificationEmailService;
use App\Service\BorrowService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @see docs/features.md F3.10 — Cancel an event
 */
class BorrowServiceCancelEventTest extends TestCase
{
    private BorrowService $service;
    private WorkflowInterface&MockObject $workflow;
    private EntityManagerInterface&MockObject $em;
    private BorrowRepository&MockObject $borrowRepository;

    protected function setUp(): void
    {
        $this->workflow = $this->createMock(WorkflowInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->borrowRepository = $this->createMock(BorrowRepository::class);
        $emailService = $this->createMock(BorrowNotificationEmailService::class);
        $registrationRepository = $this->createMock(EventDeckRegistrationRepository::class);

        $this->service = new BorrowService(
            $this->workflow,
            $this->em,
            $this->borrowRepository,
            $emailService,
            $registrationRepository,
        );
    }

    public function testCancelBorrowsForEventCancelsPendingAndApproved(): void
    {
        $event = $this->createEvent();
        $pendingBorrow = $this->createBorrow(BorrowStatus::Pending, $event);
        $approvedBorrow = $this->createBorrow(BorrowStatus::Approved, $event);

        $this->borrowRepository->method('findCancellableBorrowsByEvent')
            ->with($event)
            ->willReturn([$pendingBorrow, $approvedBorrow]);

        $this->workflow->expects(self::exactly(2))
            ->method('apply')
            ->willReturnCallback(static function (Borrow $borrow, string $transition): Marking {
                if (BorrowStatus::Pending === $borrow->getStatus()) {
                    self::assertSame('cancel_pending', $transition);
                } else {
                    self::assertSame('cancel_approved', $transition);
                }

                return new Marking();
            });

        // Notification persist (2 borrows × 1 notification each) + 1 flush
        $this->em->expects(self::exactly(2))->method('persist');
        $this->em->expects(self::atLeastOnce())->method('flush');

        $count = $this->service->cancelBorrowsForEvent($event);

        self::assertSame(2, $count);
        self::assertNotNull($pendingBorrow->getCancelledAt());
        self::assertNotNull($approvedBorrow->getCancelledAt());
    }

    public function testCancelBorrowsForEventReturnsZeroWhenNoCancellableBorrows(): void
    {
        $event = $this->createEvent();

        $this->borrowRepository->method('findCancellableBorrowsByEvent')
            ->with($event)
            ->willReturn([]);

        $this->workflow->expects(self::never())->method('apply');
        $this->em->expects(self::never())->method('flush');

        $count = $this->service->cancelBorrowsForEvent($event);

        self::assertSame(0, $count);
    }

    public function testCancelBorrowsForEventSkipsInAppNotificationWhenDisabled(): void
    {
        $event = $this->createEvent();
        $pendingBorrow = $this->createBorrow(BorrowStatus::Pending, $event);

        // Disable in-app notifications for the borrower
        $pendingBorrow->getBorrower()->setNotificationPreference(
            NotificationType::BorrowCancelled,
            'inApp',
            false,
        );

        $this->borrowRepository->method('findCancellableBorrowsByEvent')
            ->with($event)
            ->willReturn([$pendingBorrow]);

        $this->workflow->expects(self::once())
            ->method('apply')
            ->with($pendingBorrow, 'cancel_pending');

        // No notification should be persisted
        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::atLeastOnce())->method('flush');

        $count = $this->service->cancelBorrowsForEvent($event);

        self::assertSame(1, $count);
    }

    public function testCancelBorrowsForEventDoesNotTouchLentBorrows(): void
    {
        $event = $this->createEvent();

        // Only pending/approved come back from the repository query
        $pendingBorrow = $this->createBorrow(BorrowStatus::Pending, $event);

        $this->borrowRepository->method('findCancellableBorrowsByEvent')
            ->with($event)
            ->willReturn([$pendingBorrow]);

        $this->workflow->expects(self::once())
            ->method('apply')
            ->with($pendingBorrow, 'cancel_pending');

        $this->em->expects(self::atLeastOnce())->method('flush');

        $count = $this->service->cancelBorrowsForEvent($event);

        self::assertSame(1, $count);
    }

    private function createEvent(): Event
    {
        $event = new Event();
        $event->setName('Test Event');

        $organizer = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($organizer, 1);
        $event->setOrganizer($organizer);

        return $event;
    }

    private function createBorrow(BorrowStatus $status, Event $event): Borrow
    {
        $owner = new User();
        $ownerRef = new \ReflectionProperty(User::class, 'id');
        $ownerRef->setValue($owner, 10);
        $owner->setScreenName('Owner');

        $borrower = new User();
        $borrowerRef = new \ReflectionProperty(User::class, 'id');
        $borrowerRef->setValue($borrower, 20);
        $borrower->setScreenName('Borrower');

        $deck = new Deck();
        $deck->setName('Test Deck');
        $deck->setOwner($owner);
        $deckRef = new \ReflectionProperty(Deck::class, 'id');
        $deckRef->setValue($deck, 100);

        $version = new DeckVersion();
        $version->setDeck($deck);

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($version);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($event);
        $borrow->setStatus($status);

        $ref = new \ReflectionProperty(Borrow::class, 'id');
        $ref->setValue($borrow, random_int(1, 9999));

        return $borrow;
    }
}
