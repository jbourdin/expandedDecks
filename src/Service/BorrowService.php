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

namespace App\Service;

use App\Entity\Borrow;
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\BorrowStatus;
use App\Enum\DeckStatus;
use App\Enum\NotificationType;
use App\Repository\BorrowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @see docs/features.md F4.1 — Request to borrow a deck
 * @see docs/features.md F4.2 — Approve / deny a borrow request
 * @see docs/features.md F4.3 — Confirm deck hand-off (lend)
 * @see docs/features.md F4.4 — Confirm deck return
 * @see docs/features.md F4.7 — Cancel a borrow request
 */
class BorrowService
{
    public function __construct(
        private readonly WorkflowInterface $borrowStateMachine,
        private readonly EntityManagerInterface $em,
        private readonly BorrowRepository $borrowRepository,
    ) {
    }

    /**
     * @see docs/features.md F4.1 — Request to borrow a deck
     */
    public function requestBorrow(Deck $deck, User $borrower, Event $event, ?string $notes = null): Borrow
    {
        if ($deck->getOwner()->getId() === $borrower->getId()) {
            throw new \DomainException('You cannot borrow your own deck.');
        }

        $engagement = $event->getEngagementFor($borrower);
        if (null === $engagement) {
            throw new \DomainException('You must be a participant of this event to request a borrow.');
        }

        if (DeckStatus::Retired === $deck->getStatus()) {
            throw new \DomainException('This deck is retired and cannot be borrowed.');
        }

        if (null !== $event->getCancelledAt()) {
            throw new \DomainException('Cannot borrow decks for a cancelled event.');
        }

        if (null !== $event->getFinishedAt()) {
            throw new \DomainException('Cannot borrow decks for a finished event.');
        }

        if (null !== $this->borrowRepository->findActiveBorrowForDeckAtEvent($deck, $event)) {
            throw new \DomainException('This deck already has an active borrow request for this event.');
        }

        if ([] !== $this->borrowRepository->findConflictingBorrowsOnSameDay($deck, $event)) {
            throw new \DomainException('This deck already has an active borrow at another event on the same day.');
        }

        $currentVersion = $deck->getCurrentVersion();
        if (null === $currentVersion) {
            throw new \DomainException('This deck has no version and cannot be borrowed.');
        }

        $borrow = new Borrow();
        $borrow->setDeck($deck);
        $borrow->setDeckVersion($currentVersion);
        $borrow->setBorrower($borrower);
        $borrow->setEvent($event);
        $borrow->setNotes($notes);

        $this->em->persist($borrow);
        $this->em->flush();

        $this->createNotification(
            $deck->getOwner(),
            NotificationType::BorrowRequested,
            'New borrow request',
            \sprintf('%s wants to borrow "%s" for %s.', $borrower->getScreenName(), $deck->getName(), $event->getName()),
            $borrow,
        );

        return $borrow;
    }

    /**
     * @see docs/features.md F4.2 — Approve / deny a borrow request
     */
    public function approve(Borrow $borrow, User $actor): void
    {
        $this->assertDeckOwner($borrow, $actor);

        $this->borrowStateMachine->apply($borrow, 'approve');

        $borrow->setApprovedAt(new \DateTimeImmutable());
        $borrow->setApprovedBy($actor);

        $this->em->flush();

        $this->createNotification(
            $borrow->getBorrower(),
            NotificationType::BorrowApproved,
            'Borrow request approved',
            \sprintf('Your request to borrow "%s" has been approved.', $borrow->getDeck()->getName()),
            $borrow,
        );
    }

    /**
     * @see docs/features.md F4.2 — Approve / deny a borrow request
     */
    public function deny(Borrow $borrow, User $actor): void
    {
        $this->assertDeckOwner($borrow, $actor);

        $this->borrowStateMachine->apply($borrow, 'deny');

        $borrow->setCancelledAt(new \DateTimeImmutable());
        $borrow->setCancelledBy($actor);

        $this->em->flush();

        $this->createNotification(
            $borrow->getBorrower(),
            NotificationType::BorrowDenied,
            'Borrow request denied',
            \sprintf('Your request to borrow "%s" has been denied.', $borrow->getDeck()->getName()),
            $borrow,
        );
    }

    /**
     * @see docs/features.md F4.3 — Confirm deck hand-off (lend)
     */
    public function handOff(Borrow $borrow, User $actor): void
    {
        $this->assertDeckOwner($borrow, $actor);

        $this->borrowStateMachine->apply($borrow, 'hand_off');

        $borrow->setHandedOffAt(new \DateTimeImmutable());
        $borrow->setHandedOffBy($actor);

        $this->syncDeckStatus($borrow->getDeck(), DeckStatus::Lent);

        $this->em->flush();

        $this->createNotification(
            $borrow->getBorrower(),
            NotificationType::BorrowHandedOff,
            'Deck handed off',
            \sprintf('"%s" has been handed off to you. Enjoy!', $borrow->getDeck()->getName()),
            $borrow,
        );
    }

    /**
     * @see docs/features.md F4.4 — Confirm deck return
     */
    public function confirmReturn(Borrow $borrow, User $actor): void
    {
        $this->assertDeckOwner($borrow, $actor);

        $transitionName = BorrowStatus::Overdue === $borrow->getStatus() ? 'return_overdue' : 'return';
        $this->borrowStateMachine->apply($borrow, $transitionName);

        $borrow->setReturnedAt(new \DateTimeImmutable());
        $borrow->setReturnedTo($actor);

        $this->syncDeckStatus($borrow->getDeck(), DeckStatus::Available);

        $this->em->flush();

        $this->createNotification(
            $borrow->getDeck()->getOwner(),
            NotificationType::BorrowReturned,
            'Deck returned',
            \sprintf('"%s" has been returned by %s.', $borrow->getDeck()->getName(), $borrow->getBorrower()->getScreenName()),
            $borrow,
        );
    }

    /**
     * @see docs/features.md F4.7 — Cancel a borrow request
     */
    public function cancel(Borrow $borrow, User $actor): void
    {
        $this->assertBorrowerOrOwner($borrow, $actor);

        $transitionName = BorrowStatus::Pending === $borrow->getStatus() ? 'cancel_pending' : 'cancel_approved';
        $this->borrowStateMachine->apply($borrow, $transitionName);

        $borrow->setCancelledAt(new \DateTimeImmutable());
        $borrow->setCancelledBy($actor);

        $this->em->flush();

        $notifyUser = $actor->getId() === $borrow->getBorrower()->getId()
            ? $borrow->getDeck()->getOwner()
            : $borrow->getBorrower();

        $this->createNotification(
            $notifyUser,
            NotificationType::BorrowCancelled,
            'Borrow cancelled',
            \sprintf('The borrow of "%s" has been cancelled by %s.', $borrow->getDeck()->getName(), $actor->getScreenName()),
            $borrow,
        );
    }

    private function assertDeckOwner(Borrow $borrow, User $actor): void
    {
        if ($borrow->getDeck()->getOwner()->getId() !== $actor->getId()) {
            throw new AccessDeniedHttpException('Only the deck owner can perform this action.');
        }
    }

    private function assertBorrowerOrOwner(Borrow $borrow, User $actor): void
    {
        $actorId = $actor->getId();
        if ($borrow->getBorrower()->getId() !== $actorId && $borrow->getDeck()->getOwner()->getId() !== $actorId) {
            throw new AccessDeniedHttpException('Only the borrower or deck owner can cancel this borrow.');
        }
    }

    private function syncDeckStatus(Deck $deck, DeckStatus $status): void
    {
        $deck->setStatus($status);
    }

    private function createNotification(User $recipient, NotificationType $type, string $title, string $message, Borrow $borrow): void
    {
        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setContext([
            'borrowId' => $borrow->getId(),
            'deckId' => $borrow->getDeck()->getId(),
            'eventId' => $borrow->getEvent()->getId(),
        ]);

        $this->em->persist($notification);
        $this->em->flush();
    }
}
