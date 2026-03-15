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
use App\Entity\User;
use App\Enum\NotificationType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F8.1 — Borrow workflow email notifications
 */
class BorrowNotificationEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly string $mailSender,
    ) {
    }

    public function sendBorrowRequested(Borrow $borrow): void
    {
        $recipient = $borrow->getDeck()->getOwner();

        if (!$recipient->isNotificationEnabled(NotificationType::BorrowRequested, 'email')) {
            return;
        }

        $deckName = $borrow->getDeck()->getName();

        $this->sendEmail(
            $recipient,
            $this->trans('app.email.borrow.requested_subject', ['%deck%' => $deckName], $recipient),
            'email/borrow/requested.html.twig',
            $borrow,
        );
    }

    public function sendBorrowApproved(Borrow $borrow): void
    {
        $recipient = $borrow->getBorrower();

        if (!$recipient->isNotificationEnabled(NotificationType::BorrowApproved, 'email')) {
            return;
        }

        $deckName = $borrow->getDeck()->getName();

        $this->sendEmail(
            $recipient,
            $this->trans('app.email.borrow.approved_subject', ['%deck%' => $deckName], $recipient),
            'email/borrow/approved.html.twig',
            $borrow,
        );
    }

    public function sendBorrowDenied(Borrow $borrow): void
    {
        $recipient = $borrow->getBorrower();

        if (!$recipient->isNotificationEnabled(NotificationType::BorrowDenied, 'email')) {
            return;
        }

        $deckName = $borrow->getDeck()->getName();

        $this->sendEmail(
            $recipient,
            $this->trans('app.email.borrow.denied_subject', ['%deck%' => $deckName], $recipient),
            'email/borrow/denied.html.twig',
            $borrow,
        );
    }

    public function sendBorrowOverdue(Borrow $borrow): void
    {
        $deckName = $borrow->getDeck()->getName();

        // Notify both owner and borrower
        $owner = $borrow->getDeck()->getOwner();
        if ($owner->isNotificationEnabled(NotificationType::BorrowOverdue, 'email')) {
            $this->sendEmail(
                $owner,
                $this->trans('app.email.borrow.overdue_subject', ['%deck%' => $deckName], $owner),
                'email/borrow/overdue.html.twig',
                $borrow,
            );
        }

        $borrower = $borrow->getBorrower();
        if ($borrower->isNotificationEnabled(NotificationType::BorrowOverdue, 'email')) {
            $this->sendEmail(
                $borrower,
                $this->trans('app.email.borrow.overdue_subject', ['%deck%' => $deckName], $borrower),
                'email/borrow/overdue.html.twig',
                $borrow,
            );
        }
    }

    public function sendBorrowCancelled(Borrow $borrow, User $actor): void
    {
        $recipient = $actor->getId() === $borrow->getBorrower()->getId()
            ? $borrow->getDeck()->getOwner()
            : $borrow->getBorrower();

        if (!$recipient->isNotificationEnabled(NotificationType::BorrowCancelled, 'email')) {
            return;
        }

        $deckName = $borrow->getDeck()->getName();

        $this->sendEmail(
            $recipient,
            $this->trans('app.email.borrow.cancelled_subject', ['%deck%' => $deckName], $recipient),
            'email/borrow/cancelled.html.twig',
            $borrow,
            ['actor' => $actor],
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
    private function sendEmail(User $recipient, string $subject, string $template, Borrow $borrow, array $extraContext = []): void
    {
        $borrowUrl = $this->urlGenerator->generate('app_borrow_show', [
            'id' => $borrow->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from($this->mailSender)
            ->to($recipient->getEmail())
            ->subject($subject)
            ->htmlTemplate($template)
            ->context(array_merge([
                'borrow' => $borrow,
                'recipient' => $recipient,
                'borrowUrl' => $borrowUrl,
                'locale' => $recipient->getPreferredLocale(),
            ], $extraContext));

        $this->mailer->send($email);
    }
}
