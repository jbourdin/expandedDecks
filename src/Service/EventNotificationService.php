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
use App\Entity\Event;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\BorrowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F8.2 — Event notifications
 */
class EventNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly string $mailSender,
        private readonly string $mailSenderName,
    ) {
    }

    public function notifyStaffAssigned(Event $event, User $staffUser): void
    {
        if ($staffUser->isNotificationEnabled(NotificationType::StaffAssigned, 'email')) {
            $this->sendEmail(
                $staffUser,
                $this->trans('app.email.event.staff_assigned_subject', ['%event%' => $event->getName()], $staffUser),
                'email/event/staff_assigned.html.twig',
                $event,
            );
        }

        $this->createNotification(
            $staffUser,
            NotificationType::StaffAssigned,
            $this->trans('app.notification.event.staff_assigned_title', [], $staffUser),
            $this->trans('app.notification.event.staff_assigned_message', ['%event%' => $event->getName()], $staffUser),
            $event,
        );
    }

    public function notifyEventUpdated(Event $event): void
    {
        foreach ($event->getEngagements() as $engagement) {
            $user = $engagement->getUser();

            if ($user->isNotificationEnabled(NotificationType::EventUpdated, 'email')) {
                $this->sendEmail(
                    $user,
                    $this->trans('app.email.event.updated_subject', ['%event%' => $event->getName()], $user),
                    'email/event/event_updated.html.twig',
                    $event,
                );
            }

            $this->createNotification(
                $user,
                NotificationType::EventUpdated,
                $this->trans('app.notification.event.updated_title', [], $user),
                $this->trans('app.notification.event.updated_message', ['%event%' => $event->getName()], $user),
                $event,
            );
        }
    }

    public function notifyEventCancelled(Event $event): void
    {
        foreach ($event->getEngagements() as $engagement) {
            $user = $engagement->getUser();

            if ($user->isNotificationEnabled(NotificationType::EventCancelled, 'email')) {
                $this->sendEmail(
                    $user,
                    $this->trans('app.email.event.cancelled_subject', ['%event%' => $event->getName()], $user),
                    'email/event/event_cancelled.html.twig',
                    $event,
                );
            }

            $this->createNotification(
                $user,
                NotificationType::EventCancelled,
                $this->trans('app.notification.event.cancelled_title', [], $user),
                $this->trans('app.notification.event.cancelled_message', ['%event%' => $event->getName()], $user),
                $event,
            );
        }
    }

    public function notifyUserInvited(Event $event, User $invitedUser): void
    {
        if ($invitedUser->isNotificationEnabled(NotificationType::EventInvited, 'email')) {
            $this->sendEmail(
                $invitedUser,
                $this->trans('app.email.event.invitation_subject', ['%event%' => $event->getName()], $invitedUser),
                'email/event/invitation.html.twig',
                $event,
            );
        }

        $this->createNotification(
            $invitedUser,
            NotificationType::EventInvited,
            $this->trans('app.notification.event.invited_title', [], $invitedUser),
            $this->trans('app.notification.event.invited_message', ['%event%' => $event->getName()], $invitedUser),
            $event,
        );
    }

    /**
     * Notify borrowers and owners that the ending phase has started.
     *
     * @see docs/features.md F4.6 — Overdue tracking
     */
    public function notifyEndingPhase(Event $event, BorrowRepository $borrowRepository): void
    {
        $lentBorrows = $borrowRepository->findLentBorrowsByEvent($event);

        // Group by borrower for "please return" notifications
        /** @var array<int, list<Borrow>> $byBorrower */
        $byBorrower = [];
        /** @var array<int, User> $borrowerMap */
        $borrowerMap = [];
        /** @var array<int, list<Borrow>> $byOwner */
        $byOwner = [];
        /** @var array<int, User> $ownerMap */
        $ownerMap = [];

        foreach ($lentBorrows as $borrow) {
            $borrowerId = (int) $borrow->getBorrower()->getId();
            $byBorrower[$borrowerId][] = $borrow;
            $borrowerMap[$borrowerId] = $borrow->getBorrower();

            $ownerId = (int) $borrow->getDeck()->getOwnerOrFail()->getId();
            $byOwner[$ownerId][] = $borrow;
            $ownerMap[$ownerId] = $borrow->getDeck()->getOwnerOrFail();
        }

        foreach ($byBorrower as $borrowerId => $borrows) {
            $borrower = $borrowerMap[$borrowerId];
            $deckNames = array_map(static fn (Borrow $borrow): string => $borrow->getDeck()->getName(), $borrows);

            if ($borrower->isNotificationEnabled(NotificationType::EventEndingPhase, 'email')) {
                $this->sendEmail(
                    $borrower,
                    $this->trans('app.email.event.ending_phase_subject', ['%event%' => $event->getName()], $borrower),
                    'email/event/ending_phase.html.twig',
                    $event,
                    ['deckNames' => $deckNames],
                );
            }

            $this->createNotification(
                $borrower,
                NotificationType::EventEndingPhase,
                $this->trans('app.notification.event.ending_phase_title', [], $borrower),
                $this->trans('app.notification.event.ending_phase_message', [
                    '%event%' => $event->getName(),
                    '%count%' => (string) \count($borrows),
                ], $borrower),
                $event,
            );
        }

        foreach ($byOwner as $ownerId => $borrows) {
            $owner = $ownerMap[$ownerId];

            if ($owner->isNotificationEnabled(NotificationType::EventEndingPhase, 'email')) {
                $this->sendEmail(
                    $owner,
                    $this->trans('app.email.event.ending_phase_owner_subject', ['%event%' => $event->getName()], $owner),
                    'email/event/ending_phase_owner.html.twig',
                    $event,
                    ['deckCount' => \count($borrows)],
                );
            }

            $this->createNotification(
                $owner,
                NotificationType::EventEndingPhase,
                $this->trans('app.notification.event.ending_phase_owner_title', [], $owner),
                $this->trans('app.notification.event.ending_phase_owner_message', [
                    '%event%' => $event->getName(),
                    '%count%' => (string) \count($borrows),
                ], $owner),
                $event,
            );
        }
    }

    /**
     * Notify owners of delegated decks in staff custody to pick them up.
     *
     * @see docs/features.md F4.6 — Overdue tracking
     * @see docs/features.md F3.20 — Mark event as finished
     */
    public function notifyCustodyPickup(Event $event, BorrowRepository $borrowRepository): void
    {
        $custodyBorrows = $borrowRepository->findInCustodyBorrowsByEvent($event);

        // Group by owner
        /** @var array<int, list<Borrow>> $byOwner */
        $byOwner = [];
        /** @var array<int, User> $ownerMap */
        $ownerMap = [];

        foreach ($custodyBorrows as $borrow) {
            $ownerId = (int) $borrow->getDeck()->getOwnerOrFail()->getId();
            $byOwner[$ownerId][] = $borrow;
            $ownerMap[$ownerId] = $borrow->getDeck()->getOwnerOrFail();
        }

        foreach ($byOwner as $ownerId => $borrows) {
            $owner = $ownerMap[$ownerId];
            $deckNames = array_map(static fn (Borrow $borrow): string => $borrow->getDeck()->getName(), $borrows);

            if ($owner->isNotificationEnabled(NotificationType::EventCustodyPickup, 'email')) {
                $this->sendEmail(
                    $owner,
                    $this->trans('app.email.event.custody_pickup_subject', ['%event%' => $event->getName()], $owner),
                    'email/event/custody_pickup.html.twig',
                    $event,
                    ['deckNames' => $deckNames],
                );
            }

            $this->createNotification(
                $owner,
                NotificationType::EventCustodyPickup,
                $this->trans('app.notification.event.custody_pickup_title', [], $owner),
                $this->trans('app.notification.event.custody_pickup_message', [
                    '%event%' => $event->getName(),
                    '%count%' => (string) \count($borrows),
                ], $owner),
                $event,
            );
        }
    }

    /**
     * Notify the target that the current organizer wants to hand the event over.
     *
     * @see docs/features.md F3.23 — Organizer handover
     */
    public function notifyTransferRequested(Event $event, User $target, User $fromOrganizer): void
    {
        $this->createNotification(
            $target,
            NotificationType::EventTransferRequested,
            $this->trans('app.notification.event.transfer_requested_title', [], $target),
            $this->trans('app.notification.event.transfer_requested_message', [
                '%event%' => $event->getName(),
                '%name%' => $fromOrganizer->getScreenName(),
            ], $target),
            $event,
        );
    }

    /**
     * Notify the previous organizer that the target accepted the handover.
     *
     * @see docs/features.md F3.23 — Organizer handover
     */
    public function notifyTransferAccepted(Event $event, User $previousOrganizer, User $newOrganizer): void
    {
        $this->createNotification(
            $previousOrganizer,
            NotificationType::EventTransferAccepted,
            $this->trans('app.notification.event.transfer_accepted_title', [], $previousOrganizer),
            $this->trans('app.notification.event.transfer_accepted_message', [
                '%event%' => $event->getName(),
                '%name%' => $newOrganizer->getScreenName(),
            ], $previousOrganizer),
            $event,
        );
    }

    /**
     * Notify the organizer that the target declined the handover.
     *
     * @see docs/features.md F3.23 — Organizer handover
     */
    public function notifyTransferDeclined(Event $event, User $organizer, User $target): void
    {
        $this->createNotification(
            $organizer,
            NotificationType::EventTransferDeclined,
            $this->trans('app.notification.event.transfer_declined_title', [], $organizer),
            $this->trans('app.notification.event.transfer_declined_message', [
                '%event%' => $event->getName(),
                '%name%' => $target->getScreenName(),
            ], $organizer),
            $event,
        );
    }

    /**
     * @param array<string, string> $params
     */
    private function trans(string $key, array $params, User $recipient): string
    {
        return $this->translator->trans($key, $params, null, $recipient->getPreferredLocale());
    }

    /**
     * @param array<string, mixed> $extraContext
     */
    private function sendEmail(User $recipient, string $subject, string $template, Event $event, array $extraContext = []): void
    {
        $eventUrl = $this->urlGenerator->generate('app_event_show', [
            'id' => $event->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailSender, $this->mailSenderName))
            ->to(new Address($recipient->getEmail(), $recipient->getScreenName()))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context(array_merge([
                'event' => $event,
                'recipient' => $recipient,
                'eventUrl' => $eventUrl,
                'locale' => $recipient->getPreferredLocale(),
            ], $extraContext));

        $this->mailer->send($email);
    }

    private function createNotification(User $recipient, NotificationType $type, string $title, string $message, Event $event): void
    {
        if (!$recipient->isNotificationEnabled($type, 'inApp')) {
            return;
        }

        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setContext([
            'eventId' => $event->getId(),
        ]);

        $this->em->persist($notification);
        $this->em->flush();
    }
}
