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
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see docs/features.md F8.1 — Borrow workflow email notifications
 */
class BorrowNotificationEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function sendBorrowRequested(Borrow $borrow): void
    {
        $recipient = $borrow->isDelegatedToStaff()
            ? $borrow->getDeck()->getOwner()
            : $borrow->getDeck()->getOwner();

        $this->sendEmail(
            $recipient,
            \sprintf('New borrow request for "%s"', $borrow->getDeck()->getName()),
            'email/borrow/requested.html.twig',
            $borrow,
        );
    }

    public function sendBorrowApproved(Borrow $borrow): void
    {
        $this->sendEmail(
            $borrow->getBorrower(),
            \sprintf('Your borrow request for "%s" was approved', $borrow->getDeck()->getName()),
            'email/borrow/approved.html.twig',
            $borrow,
        );
    }

    public function sendBorrowDenied(Borrow $borrow): void
    {
        $this->sendEmail(
            $borrow->getBorrower(),
            \sprintf('Your borrow request for "%s" was denied', $borrow->getDeck()->getName()),
            'email/borrow/denied.html.twig',
            $borrow,
        );
    }

    public function sendBorrowOverdue(Borrow $borrow): void
    {
        // Notify both owner and borrower
        $this->sendEmail(
            $borrow->getDeck()->getOwner(),
            \sprintf('"%s" is overdue for return', $borrow->getDeck()->getName()),
            'email/borrow/overdue.html.twig',
            $borrow,
        );

        $this->sendEmail(
            $borrow->getBorrower(),
            \sprintf('"%s" is overdue for return', $borrow->getDeck()->getName()),
            'email/borrow/overdue.html.twig',
            $borrow,
        );
    }

    public function sendBorrowCancelled(Borrow $borrow, User $actor): void
    {
        $recipient = $actor->getId() === $borrow->getBorrower()->getId()
            ? $borrow->getDeck()->getOwner()
            : $borrow->getBorrower();

        $this->sendEmail(
            $recipient,
            \sprintf('Borrow of "%s" was cancelled', $borrow->getDeck()->getName()),
            'email/borrow/cancelled.html.twig',
            $borrow,
            ['actor' => $actor],
        );
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
            ->from('noreply@expanded-decks.com')
            ->to($recipient->getEmail())
            ->subject($subject)
            ->htmlTemplate($template)
            ->context(array_merge([
                'borrow' => $borrow,
                'recipient' => $recipient,
                'borrowUrl' => $borrowUrl,
            ], $extraContext));

        $this->mailer->send($email);
    }
}
