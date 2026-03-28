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

namespace App\Tests\MessageHandler;

use App\Entity\Event;
use App\Entity\User;
use App\Message\StartEndingPhaseMessage;
use App\MessageHandler\StartEndingPhaseHandler;
use App\Repository\BorrowRepository;
use App\Repository\EventRepository;
use App\Service\BorrowService;
use App\Service\EventNotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F4.6 — Overdue tracking
 */
class StartEndingPhaseHandlerTest extends TestCase
{
    private EventRepository $eventRepository;
    private BorrowRepository $borrowRepository;
    private BorrowService $borrowService;
    private EventNotificationService $notificationService;
    private LoggerInterface $logger;
    private StartEndingPhaseHandler $handler;

    protected function setUp(): void
    {
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->borrowRepository = $this->createStub(BorrowRepository::class);
        $this->borrowService = $this->createStub(BorrowService::class);
        $this->notificationService = $this->createMock(EventNotificationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new StartEndingPhaseHandler(
            $this->eventRepository,
            $this->borrowRepository,
            $this->borrowService,
            $this->notificationService,
            $this->logger,
        );
    }

    public function testEventNotFoundLogsWarningAndReturnsEarly(): void
    {
        $this->eventRepository->method('find')->willReturn(null);

        $this->logger->expects(self::once())
            ->method('warning')
            ->with('Event #{id} not found for ending phase processing.', ['id' => 77]);

        $this->logger->expects(self::never())->method('info');
        $this->notificationService->expects(self::never())->method('notifyEndingPhase');

        ($this->handler)(new StartEndingPhaseMessage(77));
    }

    public function testEventFoundCancelsBorrowsAndNotifies(): void
    {
        $event = $this->createEvent(42, 'Regional Championship');

        $this->eventRepository->method('find')->willReturn($event);
        $this->borrowService->method('cancelBorrowsForEvent')->willReturn(3);

        $this->notificationService->expects(self::once())
            ->method('notifyEndingPhase')
            ->with($event, $this->borrowRepository);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Ending phase for event #{id} "{name}": cancelled {count} pre-handoff borrow(s).',
                ['count' => 3, 'id' => 42, 'name' => 'Regional Championship'],
            );

        ($this->handler)(new StartEndingPhaseMessage(42));
    }

    public function testEventFoundWithZeroCancelledBorrowsStillNotifies(): void
    {
        $event = $this->createEvent(10, 'League Cup');

        $this->eventRepository->method('find')->willReturn($event);
        $this->borrowService->method('cancelBorrowsForEvent')->willReturn(0);

        $this->notificationService->expects(self::once())
            ->method('notifyEndingPhase');

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Ending phase for event #{id} "{name}": cancelled {count} pre-handoff borrow(s).',
                ['count' => 0, 'id' => 10, 'name' => 'League Cup'],
            );

        ($this->handler)(new StartEndingPhaseMessage(10));
    }

    private function createEvent(int $identifier, string $name): Event
    {
        $event = new Event();
        $event->setName($name);

        $organizer = new User();
        $organizerReflection = new \ReflectionProperty(User::class, 'id');
        $organizerReflection->setValue($organizer, 1);
        $event->setOrganizer($organizer);

        $eventReflection = new \ReflectionProperty(Event::class, 'id');
        $eventReflection->setValue($event, $identifier);

        return $event;
    }
}
