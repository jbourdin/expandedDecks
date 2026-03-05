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

use App\Message\CancelEventBorrowsMessage;
use App\Repository\EventRepository;
use App\Service\BorrowService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @see docs/features.md F3.10 — Cancel an event
 */
#[AsMessageHandler]
class CancelEventBorrowsHandler
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly BorrowService $borrowService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CancelEventBorrowsMessage $message): void
    {
        $event = $this->eventRepository->find($message->eventId);

        if (null === $event) {
            $this->logger->warning('Event #{id} not found for borrow cancellation cascade.', [
                'id' => $message->eventId,
            ]);

            return;
        }

        $count = $this->borrowService->cancelBorrowsForEvent($event);

        $this->logger->info('Cancelled {count} borrow(s) for cancelled event #{id} "{name}".', [
            'count' => $count,
            'id' => $message->eventId,
            'name' => $event->getName(),
        ]);
    }
}
