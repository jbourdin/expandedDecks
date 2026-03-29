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
use App\Repository\BorrowRepository;
use App\Repository\EventDeckRegistrationRepository;
use App\Service\BorrowNotificationEmailService;
use App\Service\BorrowService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @see docs/features.md F4.6 — Overdue tracking
 */
class BorrowServiceOverdueTest extends TestCase
{
    private BorrowService $service;
    private WorkflowInterface&Stub $workflow;
    private EntityManagerInterface&Stub $em;
    private BorrowRepository&Stub $borrowRepository;
    private BorrowNotificationEmailService&Stub $emailService;

    protected function setUp(): void
    {
        $this->workflow = $this->createStub(WorkflowInterface::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->borrowRepository = $this->createStub(BorrowRepository::class);
        $this->emailService = $this->createStub(BorrowNotificationEmailService::class);
        $registrationRepository = $this->createStub(EventDeckRegistrationRepository::class);

        $this->service = new BorrowService(
            $this->workflow,
            $this->em,
            $this->borrowRepository,
            $this->emailService,
            $registrationRepository,
        );
    }

    public function testMarkOverdueForEventTransitionsLentBorrowsToOverdue(): void
    {
        $event = $this->createEvent();
        $borrow1 = $this->createBorrow(BorrowStatus::Lent, $event);
        $borrow2 = $this->createBorrow(BorrowStatus::Lent, $event);

        $borrowRepository = $this->createMock(BorrowRepository::class);
        $borrowRepository->expects(self::once())
            ->method('findLentBorrowsByEvent')
            ->with($event)
            ->willReturn([$borrow1, $borrow2]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::exactly(2))
            ->method('apply')
            ->willReturnCallback(static function (Borrow $borrow, string $transition): Marking {
                self::assertSame('mark_overdue', $transition);

                return new Marking();
            });

        $emailService = $this->createMock(BorrowNotificationEmailService::class);
        $emailService->expects(self::exactly(2))
            ->method('sendBorrowOverdue');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeastOnce())->method('flush');

        $service = new BorrowService(
            $workflow,
            $entityManager,
            $borrowRepository,
            $emailService,
            $this->createStub(EventDeckRegistrationRepository::class),
        );

        $count = $service->markOverdueForEvent($event);

        self::assertSame(2, $count);
    }

    public function testMarkOverdueForEventReturnsZeroWhenNoLentBorrows(): void
    {
        $event = $this->createEvent();

        $borrowRepository = $this->createMock(BorrowRepository::class);
        $borrowRepository->expects(self::once())
            ->method('findLentBorrowsByEvent')
            ->with($event)
            ->willReturn([]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::never())->method('apply');

        $emailService = $this->createMock(BorrowNotificationEmailService::class);
        $emailService->expects(self::never())->method('sendBorrowOverdue');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $service = new BorrowService(
            $workflow,
            $entityManager,
            $borrowRepository,
            $emailService,
            $this->createStub(EventDeckRegistrationRepository::class),
        );

        $count = $service->markOverdueForEvent($event);

        self::assertSame(0, $count);
    }

    public function testRequestBorrowThrowsDuringEndingPhase(): void
    {
        $event = $this->createEvent();
        $event->setEndingPhaseAt(new \DateTimeImmutable());

        $owner = new User();
        $ownerRef = new \ReflectionProperty(User::class, 'id');
        $ownerRef->setValue($owner, 10);

        $borrower = new User();
        $borrowerRef = new \ReflectionProperty(User::class, 'id');
        $borrowerRef->setValue($borrower, 20);

        $deck = new Deck();
        $deck->setOwner($owner);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot borrow decks during the ending phase.');

        $this->service->requestBorrow($deck, $borrower, $event);
    }

    public function testApproveBorrowThrowsDuringEndingPhase(): void
    {
        $event = $this->createEvent();
        $event->setEndingPhaseAt(new \DateTimeImmutable());

        $borrow = $this->createBorrow(BorrowStatus::Pending, $event);
        $owner = $borrow->getDeck()->getOwner();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot approve borrows during the ending phase or after the event is finished.');

        $this->service->approve($borrow, $owner);
    }

    public function testHandOffThrowsDuringEndingPhase(): void
    {
        $event = $this->createEvent();
        $event->setEndingPhaseAt(new \DateTimeImmutable());

        $borrow = $this->createBorrow(BorrowStatus::Approved, $event);
        $owner = $borrow->getDeck()->getOwner();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot hand off decks during the ending phase or after the event is finished.');

        $this->service->handOff($borrow, $owner);
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
