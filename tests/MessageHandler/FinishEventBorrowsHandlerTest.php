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
use App\Message\FinishEventBorrowsMessage;
use App\MessageHandler\FinishEventBorrowsHandler;
use App\Repository\BorrowRepository;
use App\Repository\EventRepository;
use App\Service\BorrowService;
use App\Service\EventNotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F4.6 — Overdue tracking
 * @see docs/features.md F3.20 — Mark event as finished
 */
class FinishEventBorrowsHandlerTest extends TestCase
{
    private EventRepository $eventRepository;
    private BorrowRepository $borrowRepository;
    private BorrowService $borrowService;
    private EventNotificationService $notificationService;
    private LoggerInterface $logger;
    private FinishEventBorrowsHandler $handler;

    protected function setUp(): void
    {
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->borrowRepository = $this->createStub(BorrowRepository::class);
        $this->borrowService = $this->createStub(BorrowService::class);
        $this->notificationService = $this->createMock(EventNotificationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new FinishEventBorrowsHandler(
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
            ->with('Event #{id} not found for finish borrow processing.', ['id' => 77]);

        $this->logger->expects(self::never())->method('info');
        $this->notificationService->expects(self::never())->method('notifyCustodyPickup');

        ($this->handler)(new FinishEventBorrowsMessage(77));
    }

    public function testEventFoundMarksOverdueAndNotifiesCustody(): void
    {
        $event = $this->createEvent(42, 'Regional Championship');

        $this->eventRepository->method('find')->willReturn($event);
        $this->borrowService->method('markOverdueForEvent')->willReturn(5);

        $this->notificationService->expects(self::once())
            ->method('notifyCustodyPickup')
            ->with($event, $this->borrowRepository);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Finished event #{id} "{name}": marked {count} borrow(s) as overdue.',
                ['count' => 5, 'id' => 42, 'name' => 'Regional Championship'],
            );

        ($this->handler)(new FinishEventBorrowsMessage(42));
    }

    public function testEventFoundWithZeroOverdueBorrowsStillNotifiesCustody(): void
    {
        $event = $this->createEvent(10, 'League Cup');

        $this->eventRepository->method('find')->willReturn($event);
        $this->borrowService->method('markOverdueForEvent')->willReturn(0);

        $this->notificationService->expects(self::once())
            ->method('notifyCustodyPickup');

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Finished event #{id} "{name}": marked {count} borrow(s) as overdue.',
                ['count' => 0, 'id' => 10, 'name' => 'League Cup'],
            );

        ($this->handler)(new FinishEventBorrowsMessage(10));
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
