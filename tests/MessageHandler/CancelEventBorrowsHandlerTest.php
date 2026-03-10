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
use App\Message\CancelEventBorrowsMessage;
use App\MessageHandler\CancelEventBorrowsHandler;
use App\Repository\EventRepository;
use App\Service\BorrowService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F3.10 — Cancel an event
 */
class CancelEventBorrowsHandlerTest extends TestCase
{
    private EventRepository $eventRepository;
    private BorrowService $borrowService;
    private LoggerInterface $logger;
    private CancelEventBorrowsHandler $handler;

    protected function setUp(): void
    {
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->borrowService = $this->createStub(BorrowService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new CancelEventBorrowsHandler(
            $this->eventRepository,
            $this->borrowService,
            $this->logger,
        );
    }

    public function testEventNotFoundLogsWarningAndReturnsEarly(): void
    {
        $this->eventRepository->method('find')->willReturn(null);

        $this->logger->expects(self::once())
            ->method('warning')
            ->with('Event #{id} not found for borrow cancellation cascade.', ['id' => 77]);

        $this->logger->expects(self::never())->method('info');

        ($this->handler)(new CancelEventBorrowsMessage(77));
    }

    public function testEventFoundCallsBorrowServiceAndLogsInfo(): void
    {
        $event = $this->createEvent(42, 'Regional Championship');

        $this->eventRepository->method('find')->willReturn($event);
        $this->borrowService->method('cancelBorrowsForEvent')->willReturn(3);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Cancelled {count} borrow(s) for cancelled event #{id} "{name}".',
                ['count' => 3, 'id' => 42, 'name' => 'Regional Championship'],
            );

        $this->logger->expects(self::never())->method('warning');

        ($this->handler)(new CancelEventBorrowsMessage(42));
    }

    public function testEventFoundWithZeroCancelledBorrowsLogsZeroCount(): void
    {
        $event = $this->createEvent(10, 'League Cup');

        $this->eventRepository->method('find')->willReturn($event);
        $this->borrowService->method('cancelBorrowsForEvent')->willReturn(0);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Cancelled {count} borrow(s) for cancelled event #{id} "{name}".',
                ['count' => 0, 'id' => 10, 'name' => 'League Cup'],
            );

        ($this->handler)(new CancelEventBorrowsMessage(10));
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
