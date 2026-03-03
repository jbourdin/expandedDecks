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

namespace App\EventListener;

use App\Entity\Borrow;
use App\Message\DeclineCompetingBorrowsMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Dispatches auto-decline of competing pending borrows when a borrow is approved
 * or a walk-up lend is created.
 *
 * @see docs/features.md F4.11 — Borrow conflict detection
 */
#[AsEventListener(event: 'workflow.borrow.completed.approve', method: 'onApproved')]
#[AsEventListener(event: 'workflow.borrow.completed.walk_up_lend', method: 'onApproved')]
class BorrowApprovedListener
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function onApproved(CompletedEvent $event): void
    {
        $borrow = $event->getSubject();
        \assert($borrow instanceof Borrow);

        $borrowId = $borrow->getId();
        if (null === $borrowId) {
            return;
        }

        $this->messageBus->dispatch(new DeclineCompetingBorrowsMessage($borrowId));
    }
}
