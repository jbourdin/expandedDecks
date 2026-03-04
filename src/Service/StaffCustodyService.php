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
use App\Entity\EventDeckRegistration;
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
 * Tracks physical custody handover of delegated decks between owner and staff.
 *
 * @see docs/features.md F4.14 — Staff custody handover tracking
 */
class StaffCustodyService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BorrowRepository $borrowRepository,
        private readonly WorkflowInterface $borrowStateMachine,
    ) {
    }

    /**
     * Owner confirms handing the physical deck to event staff.
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    public function confirmOwnerHandover(EventDeckRegistration $registration, User $actor): void
    {
        if (!$registration->isDelegateToStaff()) {
            throw new \DomainException('This deck is not delegated to staff.');
        }

        if ($registration->hasStaffReceived()) {
            throw new \DomainException('This deck has already been handed over to staff.');
        }

        $deckOwner = $registration->getDeck()->getOwner();
        if ($deckOwner->getId() !== $actor->getId()) {
            throw new AccessDeniedHttpException('Only the deck owner can confirm the handover to staff.');
        }

        $registration->setStaffReceivedAt(new \DateTimeImmutable());
        $registration->setStaffReceivedBy($actor);

        $this->em->flush();
    }

    /**
     * Staff confirms returning the physical deck to the owner.
     *
     * Guards: blocks return if the deck is currently lent to a borrower (lent/overdue).
     * Auto-closes remaining active borrows (returned → returned_to_owner, pending/approved → cancelled).
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    public function confirmStaffReturn(EventDeckRegistration $registration, User $actor): void
    {
        if (!$registration->hasStaffReceived()) {
            throw new \DomainException('This deck has not been handed over to staff yet.');
        }

        if ($registration->hasStaffReturned()) {
            throw new \DomainException('This deck has already been returned to the owner.');
        }

        $event = $registration->getEvent();
        if (!$event->isOrganizerOrStaff($actor)) {
            throw new AccessDeniedHttpException('Only organizers or staff can confirm the return to owner.');
        }

        $deck = $registration->getDeck();
        $openBorrows = $this->borrowRepository->findOpenBorrowsForDeckAtEvent($deck, $event);

        // Guard: cannot return while deck is physically with a borrower
        foreach ($openBorrows as $borrow) {
            if (BorrowStatus::Lent === $borrow->getStatus() || BorrowStatus::Overdue === $borrow->getStatus()) {
                throw new \DomainException('Cannot return deck to owner — it is currently lent to a borrower. Collect it back first.');
            }
        }

        // Auto-close remaining borrows
        $now = new \DateTimeImmutable();
        foreach ($openBorrows as $borrow) {
            $this->closeBorrowForReturn($borrow, $actor, $now);
        }

        $registration->setStaffReturnedAt($now);
        $registration->setStaffReturnedBy($actor);

        $this->em->flush();
    }

    /**
     * Owner reclaims a deck directly (e.g. borrower returned to the owner instead of staff).
     * Closes all active borrows and the custody tracking in one action.
     *
     * @see docs/features.md F4.14 — Staff custody handover tracking
     */
    public function ownerReclaimDeck(EventDeckRegistration $registration, User $actor): void
    {
        $deckOwner = $registration->getDeck()->getOwner();
        if ($deckOwner->getId() !== $actor->getId()) {
            throw new AccessDeniedHttpException('Only the deck owner can reclaim this deck.');
        }

        if (!$registration->hasStaffReceived()) {
            throw new \DomainException('This deck has not been handed over to staff yet.');
        }

        if ($registration->hasStaffReturned()) {
            throw new \DomainException('This deck has already been returned to you.');
        }

        $deck = $registration->getDeck();
        $event = $registration->getEvent();
        $openBorrows = $this->borrowRepository->findOpenBorrowsForDeckAtEvent($deck, $event);

        $now = new \DateTimeImmutable();

        foreach ($openBorrows as $borrow) {
            $this->closeBorrowForOwnerReclaim($borrow, $actor, $now);
        }

        $registration->setStaffReturnedAt($now);
        $registration->setStaffReturnedBy($actor);

        $deck->setStatus(DeckStatus::Available);

        $this->em->flush();
    }

    /**
     * Close a borrow when staff returns the deck to the owner.
     * Handles returned (→ returned_to_owner) and pending/approved (→ cancelled).
     */
    private function closeBorrowForReturn(Borrow $borrow, User $actor, \DateTimeImmutable $now): void
    {
        match ($borrow->getStatus()) {
            BorrowStatus::Returned => $this->transitionToReturnedToOwner($borrow, $now),
            BorrowStatus::Pending => $this->cancelBorrow($borrow, $actor, $now, 'cancel_pending'),
            BorrowStatus::Approved => $this->cancelBorrow($borrow, $actor, $now, 'cancel_approved'),
            default => null,
        };
    }

    /**
     * Close a borrow when the owner reclaims the deck.
     * Handles all active statuses including lent/overdue.
     */
    private function closeBorrowForOwnerReclaim(Borrow $borrow, User $actor, \DateTimeImmutable $now): void
    {
        match ($borrow->getStatus()) {
            BorrowStatus::Lent => $this->returnAndCloseForOwner($borrow, $actor, $now, 'return'),
            BorrowStatus::Overdue => $this->returnAndCloseForOwner($borrow, $actor, $now, 'return_overdue'),
            BorrowStatus::Returned => $this->transitionToReturnedToOwner($borrow, $now),
            BorrowStatus::Pending => $this->cancelBorrow($borrow, $actor, $now, 'cancel_pending'),
            BorrowStatus::Approved => $this->cancelBorrow($borrow, $actor, $now, 'cancel_approved'),
            default => null,
        };
    }

    /**
     * Return a lent/overdue borrow to staff, then close it as returned to owner.
     * Notifies the borrower that the owner reclaimed the deck.
     */
    private function returnAndCloseForOwner(Borrow $borrow, User $actor, \DateTimeImmutable $now, string $returnTransition): void
    {
        $this->borrowStateMachine->apply($borrow, $returnTransition);
        $borrow->setReturnedAt($now);
        $borrow->setReturnedTo($actor);

        $this->borrowStateMachine->apply($borrow, 'return_to_owner');
        $borrow->setReturnedToOwnerAt($now);

        $this->createNotification(
            $borrow->getBorrower(),
            NotificationType::BorrowReturned,
            'Deck reclaimed by owner',
            \sprintf('The owner has reclaimed "%s". The borrow has been closed.', $borrow->getDeck()->getName()),
            $borrow,
        );
    }

    private function transitionToReturnedToOwner(Borrow $borrow, \DateTimeImmutable $now): void
    {
        $this->borrowStateMachine->apply($borrow, 'return_to_owner');
        $borrow->setReturnedToOwnerAt($now);
    }

    private function cancelBorrow(Borrow $borrow, User $actor, \DateTimeImmutable $now, string $transition): void
    {
        $this->borrowStateMachine->apply($borrow, $transition);
        $borrow->setCancelledAt($now);
        $borrow->setCancelledBy($actor);
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
    }
}
