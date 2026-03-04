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

use App\Message\DeclineCompetingBorrowsMessage;
use App\Repository\BorrowRepository;
use App\Service\BorrowService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @see docs/features.md F4.11 — Borrow conflict detection
 */
#[AsMessageHandler]
class DeclineCompetingBorrowsHandler
{
    public function __construct(
        private readonly BorrowRepository $borrowRepository,
        private readonly BorrowService $borrowService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeclineCompetingBorrowsMessage $message): void
    {
        $approvedBorrow = $this->borrowRepository->find($message->borrowId);

        if (null === $approvedBorrow) {
            $this->logger->warning('Borrow #{id} not found for competing-borrow decline.', [
                'id' => $message->borrowId,
            ]);

            return;
        }

        $owner = $approvedBorrow->getDeck()->getOwner();

        $competitors = $this->borrowRepository->findPendingBorrowsForDeckAtEvent(
            $approvedBorrow->getDeck(),
            $approvedBorrow->getEvent(),
            $approvedBorrow,
        );

        if ([] === $competitors) {
            return;
        }

        $this->logger->info('Auto-declining {count} competing borrow(s) for Borrow #{id}.', [
            'count' => \count($competitors),
            'id' => $message->borrowId,
        ]);

        foreach ($competitors as $competitor) {
            try {
                $this->borrowService->deny($competitor, $owner);

                $this->logger->info('Auto-declined competing Borrow #{competitorId} (approved: #{approvedId}).', [
                    'competitorId' => $competitor->getId(),
                    'approvedId' => $message->borrowId,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to auto-decline Borrow #{competitorId}: {error}', [
                    'competitorId' => $competitor->getId(),
                    'approvedId' => $message->borrowId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
