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

use App\Entity\Borrow;
use App\Entity\Deck;
use App\Entity\DeckVersion;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Message\DeclineCompetingBorrowsMessage;
use App\MessageHandler\DeclineCompetingBorrowsHandler;
use App\Repository\BorrowRepository;
use App\Service\BorrowService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @see docs/features.md F4.11 — Borrow conflict detection
 */
class DeclineCompetingBorrowsHandlerTest extends TestCase
{
    private BorrowRepository $borrowRepository;
    private BorrowService $borrowService;
    private LoggerInterface $logger;
    private DeclineCompetingBorrowsHandler $handler;

    protected function setUp(): void
    {
        $this->borrowRepository = $this->createStub(BorrowRepository::class);
        $this->borrowService = $this->createMock(BorrowService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new DeclineCompetingBorrowsHandler(
            $this->borrowRepository,
            $this->borrowService,
            $this->logger,
        );
    }

    public function testBorrowNotFoundLogsWarningAndReturnsEarly(): void
    {
        $this->borrowRepository->method('find')->willReturn(null);

        $this->logger->expects(self::once())
            ->method('warning')
            ->with('Borrow #{id} not found for competing-borrow decline.', ['id' => 99]);

        $this->logger->expects(self::never())->method('info');
        $this->borrowService->expects(self::never())->method('deny');

        ($this->handler)(new DeclineCompetingBorrowsMessage(99));
    }

    public function testNoCompetingBorrowsReturnsWithoutDenials(): void
    {
        $owner = $this->createUser(10);
        $deck = $this->createDeck(100, $owner);
        $event = $this->createEvent(200);
        $approvedBorrow = $this->createBorrow(1, $deck, $event, BorrowStatus::Approved);

        $this->borrowRepository->method('find')->willReturn($approvedBorrow);
        $this->borrowRepository->method('findPendingBorrowsForDeckAtEvent')
            ->willReturn([]);

        $this->borrowService->expects(self::never())->method('deny');
        $this->logger->expects(self::never())->method('info');

        ($this->handler)(new DeclineCompetingBorrowsMessage(1));
    }

    public function testCompetingBorrowsAreDenied(): void
    {
        $owner = $this->createUser(10);
        $deck = $this->createDeck(100, $owner);
        $event = $this->createEvent(200);
        $approvedBorrow = $this->createBorrow(1, $deck, $event, BorrowStatus::Approved);

        $competitor1 = $this->createBorrow(2, $deck, $event, BorrowStatus::Pending);
        $competitor2 = $this->createBorrow(3, $deck, $event, BorrowStatus::Pending);

        $this->borrowRepository->method('find')->willReturn($approvedBorrow);
        $this->borrowRepository->method('findPendingBorrowsForDeckAtEvent')
            ->willReturn([$competitor1, $competitor2]);

        $deniedBorrows = [];
        $this->borrowService->expects(self::exactly(2))
            ->method('deny')
            ->willReturnCallback(static function (Borrow $borrow, User $actor) use ($owner, &$deniedBorrows): void {
                self::assertSame($owner, $actor);
                $deniedBorrows[] = $borrow->getId();
            });

        $infoMessages = [];
        $this->logger->expects(self::exactly(3))
            ->method('info')
            ->willReturnCallback(static function (string $message) use (&$infoMessages): void {
                $infoMessages[] = $message;
            });

        ($this->handler)(new DeclineCompetingBorrowsMessage(1));

        self::assertSame([2, 3], $deniedBorrows);
        self::assertSame('Auto-declining {count} competing borrow(s) for Borrow #{id}.', $infoMessages[0]);
        self::assertSame('Auto-declined competing Borrow #{competitorId} (approved: #{approvedId}).', $infoMessages[1]);
        self::assertSame('Auto-declined competing Borrow #{competitorId} (approved: #{approvedId}).', $infoMessages[2]);
    }

    public function testExceptionDuringDenialIsCaughtAndLoggedAsError(): void
    {
        $owner = $this->createUser(10);
        $deck = $this->createDeck(100, $owner);
        $event = $this->createEvent(200);
        $approvedBorrow = $this->createBorrow(1, $deck, $event, BorrowStatus::Approved);

        $competitor1 = $this->createBorrow(2, $deck, $event, BorrowStatus::Pending);
        $competitor2 = $this->createBorrow(3, $deck, $event, BorrowStatus::Pending);

        $this->borrowRepository->method('find')->willReturn($approvedBorrow);
        $this->borrowRepository->method('findPendingBorrowsForDeckAtEvent')
            ->willReturn([$competitor1, $competitor2]);

        $callIndex = 0;
        $this->borrowService->expects(self::exactly(2))
            ->method('deny')
            ->willReturnCallback(static function () use (&$callIndex): void {
                ++$callIndex;
                if (1 === $callIndex) {
                    throw new \RuntimeException('Workflow transition failed');
                }
            });

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to auto-decline Borrow #{competitorId}: {error}',
                [
                    'competitorId' => 2,
                    'approvedId' => 1,
                    'error' => 'Workflow transition failed',
                ],
            );

        // 1 info for count, 1 info for the successful denial of competitor2
        $infoMessages = [];
        $this->logger->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(static function (string $message) use (&$infoMessages): void {
                $infoMessages[] = $message;
            });

        ($this->handler)(new DeclineCompetingBorrowsMessage(1));

        self::assertSame('Auto-declining {count} competing borrow(s) for Borrow #{id}.', $infoMessages[0]);
        self::assertSame('Auto-declined competing Borrow #{competitorId} (approved: #{approvedId}).', $infoMessages[1]);
    }

    public function testExceptionOnFirstCompetitorDoesNotPreventDenialOfSecond(): void
    {
        $owner = $this->createUser(10);
        $deck = $this->createDeck(100, $owner);
        $event = $this->createEvent(200);
        $approvedBorrow = $this->createBorrow(1, $deck, $event, BorrowStatus::Approved);

        $competitor1 = $this->createBorrow(2, $deck, $event, BorrowStatus::Pending);
        $competitor2 = $this->createBorrow(3, $deck, $event, BorrowStatus::Pending);

        $this->borrowRepository->method('find')->willReturn($approvedBorrow);
        $this->borrowRepository->method('findPendingBorrowsForDeckAtEvent')
            ->willReturn([$competitor1, $competitor2]);

        $callIndex = 0;
        $deniedBorrowIds = [];
        $this->borrowService->expects(self::exactly(2))
            ->method('deny')
            ->willReturnCallback(static function (Borrow $borrow) use (&$callIndex, &$deniedBorrowIds): void {
                ++$callIndex;
                $deniedBorrowIds[] = $borrow->getId();
                if (1 === $callIndex) {
                    throw new \RuntimeException('First denial failed');
                }
            });

        // 1 info for count + 1 info for successful denial of competitor2
        $this->logger->expects(self::exactly(2))->method('info');
        // 1 error for the failed denial of competitor1
        $this->logger->expects(self::once())->method('error');

        ($this->handler)(new DeclineCompetingBorrowsMessage(1));

        // Both competitors were attempted even though the first threw an exception
        self::assertSame([2, 3], $deniedBorrowIds);
    }

    private function createUser(int $identifier): User
    {
        $user = new User();
        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $identifier);
        $user->setScreenName('User '.$identifier);

        return $user;
    }

    private function createDeck(int $identifier, User $owner): Deck
    {
        $deck = new Deck();
        $reflection = new \ReflectionProperty(Deck::class, 'id');
        $reflection->setValue($deck, $identifier);
        $deck->setName('Test Deck');
        $deck->setOwner($owner);

        return $deck;
    }

    private function createEvent(int $identifier): Event
    {
        $event = new Event();
        $reflection = new \ReflectionProperty(Event::class, 'id');
        $reflection->setValue($event, $identifier);
        $event->setName('Test Event');

        $organizer = $this->createUser(999);
        $event->setOrganizer($organizer);

        return $event;
    }

    private function createBorrow(int $identifier, Deck $deck, Event $event, BorrowStatus $status): Borrow
    {
        $borrower = $this->createUser($identifier + 100);

        $version = new DeckVersion();
        $version->setDeck($deck);

        $borrow = new Borrow();
        $reflection = new \ReflectionProperty(Borrow::class, 'id');
        $reflection->setValue($borrow, $identifier);
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($version);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($event);
        $borrow->setStatus($status);

        return $borrow;
    }
}
