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

namespace App\Tests\EventListener;

use App\Entity\Borrow;
use App\EventListener\BorrowApprovedListener;
use App\Message\DeclineCompetingBorrowsMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;

/**
 * @see docs/features.md F4.11 — Borrow conflict detection
 */
class BorrowApprovedListenerTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $messageBus = $this->createStub(MessageBusInterface::class);

        $listener = new BorrowApprovedListener($messageBus);

        self::assertInstanceOf(BorrowApprovedListener::class, $listener);
    }

    public function testOnApprovedDispatchesDeclineCompetingBorrowsMessage(): void
    {
        $borrow = new Borrow();
        $reflection = new \ReflectionProperty(Borrow::class, 'id');
        $reflection->setValue($borrow, 42);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (DeclineCompetingBorrowsMessage $message): bool => 42 === $message->borrowId,
            ))
            ->willReturn(new Envelope(new \stdClass()));

        $listener = new BorrowApprovedListener($messageBus);
        $event = new CompletedEvent($borrow, new Marking(['approved' => 1]));

        $listener->onApproved($event);
    }

    public function testOnApprovedDoesNothingWhenBorrowHasNoId(): void
    {
        $borrow = new Borrow();

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $listener = new BorrowApprovedListener($messageBus);
        $event = new CompletedEvent($borrow, new Marking(['approved' => 1]));

        $listener->onApproved($event);
    }
}
