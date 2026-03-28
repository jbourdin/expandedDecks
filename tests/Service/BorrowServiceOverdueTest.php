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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @see docs/features.md F4.6 — Overdue tracking
 */
class BorrowServiceOverdueTest extends TestCase
{
    private BorrowService $service;
    private WorkflowInterface&MockObject $workflow;
    private EntityManagerInterface&MockObject $em;
    private BorrowRepository&MockObject $borrowRepository;
    private BorrowNotificationEmailService&MockObject $emailService;

    protected function setUp(): void
    {
        $this->workflow = $this->createMock(WorkflowInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->borrowRepository = $this->createMock(BorrowRepository::class);
        $this->emailService = $this->createMock(BorrowNotificationEmailService::class);
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

        $this->borrowRepository->expects(self::once())
            ->method('findLentBorrowsByEvent')
            ->with($event)
            ->willReturn([$borrow1, $borrow2]);

        $this->workflow->expects(self::exactly(2))
            ->method('apply')
            ->willReturnCallback(static function (Borrow $borrow, string $transition): Marking {
                self::assertSame('mark_overdue', $transition);

                return new Marking();
            });

        $this->emailService->expects(self::exactly(2))
            ->method('sendBorrowOverdue');

        $this->em->expects(self::atLeastOnce())->method('flush');

        $count = $this->service->markOverdueForEvent($event);

        self::assertSame(2, $count);
    }

    public function testMarkOverdueForEventReturnsZeroWhenNoLentBorrows(): void
    {
        $event = $this->createEvent();

        $this->borrowRepository->expects(self::once())
            ->method('findLentBorrowsByEvent')
            ->with($event)
            ->willReturn([]);

        $this->workflow->expects(self::never())->method('apply');
        $this->emailService->expects(self::never())->method('sendBorrowOverdue');
        $this->em->expects(self::never())->method('flush');

        $count = $this->service->markOverdueForEvent($event);

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
