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

namespace App\MessageHandler;

use App\Message\FinishEventBorrowsMessage;
use App\Repository\BorrowRepository;
use App\Repository\EventRepository;
use App\Service\BorrowService;
use App\Service\EventNotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @see docs/features.md F4.6 — Overdue tracking
 * @see docs/features.md F3.20 — Mark event as finished
 */
#[AsMessageHandler]
class FinishEventBorrowsHandler
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly BorrowRepository $borrowRepository,
        private readonly BorrowService $borrowService,
        private readonly EventNotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(FinishEventBorrowsMessage $message): void
    {
        $event = $this->eventRepository->find($message->eventId);

        if (null === $event) {
            $this->logger->warning('Event #{id} not found for finish borrow processing.', [
                'id' => $message->eventId,
            ]);

            return;
        }

        $overdueCount = $this->borrowService->markOverdueForEvent($event);

        $this->logger->info('Finished event #{id} "{name}": marked {count} borrow(s) as overdue.', [
            'count' => $overdueCount,
            'id' => $message->eventId,
            'name' => $event->getName(),
        ]);

        $this->notificationService->notifyCustodyPickup($event, $this->borrowRepository);
    }
}
