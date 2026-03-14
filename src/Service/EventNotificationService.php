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

use App\Entity\Event;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
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
            ->from($this->mailSender)
            ->to($recipient->getEmail())
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
