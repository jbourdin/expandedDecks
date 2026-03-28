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

use App\Message\StartEndingPhaseMessage;
use App\Repository\BorrowRepository;
use App\Repository\EventRepository;
use App\Service\BorrowService;
use App\Service\EventNotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @see docs/features.md F4.6 — Overdue tracking
 */
#[AsMessageHandler]
class StartEndingPhaseHandler
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly BorrowRepository $borrowRepository,
        private readonly BorrowService $borrowService,
        private readonly EventNotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(StartEndingPhaseMessage $message): void
    {
        $event = $this->eventRepository->find($message->eventId);

        if (null === $event) {
            $this->logger->warning('Event #{id} not found for ending phase processing.', [
                'id' => $message->eventId,
            ]);

            return;
        }

        $cancelledCount = $this->borrowService->cancelBorrowsForEvent($event);

        $this->logger->info('Ending phase for event #{id} "{name}": cancelled {count} pre-handoff borrow(s).', [
            'count' => $cancelledCount,
            'id' => $message->eventId,
            'name' => $event->getName(),
        ]);

        $this->notificationService->notifyEndingPhase($event, $this->borrowRepository);
    }
}
