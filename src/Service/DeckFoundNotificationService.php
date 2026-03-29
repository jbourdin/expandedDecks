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

use App\Entity\Deck;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Creates in-app and email notifications when someone reports a found deck.
 *
 * @see docs/features.md F4.16 — Lost & found deck alert
 */
class DeckFoundNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly string $mailSender,
        private readonly string $mailSenderName,
    ) {
    }

    /**
     * @see docs/features.md F4.16 — Lost & found deck alert
     */
    public function notify(Deck $deck, ?User $reporter, ?string $message): void
    {
        $owner = $deck->getOwner();
        $locale = $owner->getPreferredLocale();
        $deckName = $deck->getName();
        $reporterName = $reporter?->getScreenName();

        $context = [
            'deckId' => $deck->getId(),
            'deckShortTag' => $deck->getShortTag(),
        ];

        if (null !== $reporterName) {
            $context['reporterScreenName'] = $reporterName;
        }

        if (null !== $message && '' !== $message) {
            $context['reporterMessage'] = $message;
        }

        $this->createInAppNotification($owner, $deckName, $reporter, $message, $context, $locale);
        $this->sendEmailNotification($owner, $deck, $reporter, $message, $locale);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function createInAppNotification(User $owner, string $deckName, ?User $reporter, ?string $reporterMessage, array $context, string $locale): void
    {
        if (!$owner->isNotificationEnabled(NotificationType::DeckFound, 'inApp')) {
            return;
        }

        $reporterLabel = null !== $reporter
            ? $reporter->getScreenName()
            : $this->translator->trans('app.email.deck_found_anonymous', [], null, $locale);

        $messageParts = [$this->translator->trans('app.notification.deck_found_message', [
            '%deck%' => $deckName,
            '%reporter%' => $reporterLabel,
        ], null, $locale)];

        if (null !== $reporterMessage && '' !== $reporterMessage) {
            $messageParts[] = $reporterMessage;
        }

        $notification = new Notification();
        $notification->setRecipient($owner);
        $notification->setType(NotificationType::DeckFound);
        $notification->setTitle($this->translator->trans('app.notification.deck_found_title', ['%deck%' => $deckName], null, $locale));
        $notification->setMessage(implode("\n\n", $messageParts));
        $notification->setContext($context);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    private function sendEmailNotification(User $owner, Deck $deck, ?User $reporter, ?string $message, string $locale): void
    {
        if (!$owner->isNotificationEnabled(NotificationType::DeckFound, 'email')) {
            return;
        }

        $deckUrl = $this->urlGenerator->generate('app_deck_show', [
            'short_tag' => $deck->getShortTag(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailSender, $this->mailSenderName))
            ->to(new Address($owner->getEmail(), $owner->getScreenName()))
            ->subject($this->translator->trans('app.email.deck_found_subject', ['%deck%' => $deck->getName()], null, $locale))
            ->htmlTemplate('email/deck_found.html.twig')
            ->context([
                'deck' => $deck,
                'reporter' => $reporter,
                'reporterMessage' => $message,
                'deckUrl' => $deckUrl,
                'locale' => $locale,
            ]);

        $this->mailer->send($email);
    }
}
