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

namespace App\Service\Translation;

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\MenuCategory;
use App\Entity\Notification;
use App\Entity\Page;
use App\Entity\TranslationRevisionInterface;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Workflow notifications (F9.15): submissions alert every moderator, review
 * outcomes alert the contributor, and freshly outdated translations alert
 * their credited translator. In-app and email channels both honor the
 * recipient's per-type preferences and preferred locale.
 *
 * @see docs/features.md F9.15 — Translation workflow notifications
 */
final readonly class TranslationNotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        #[Autowire('%app.mail_sender%')]
        private string $mailSender,
        #[Autowire('%app.mail_sender_name%')]
        private string $mailSenderName,
        #[Autowire('%kernel.default_locale%')]
        private string $sourceLocale,
    ) {
    }

    /**
     * Submission → every moderator except the submitting contributor.
     */
    public function notifySubmitted(TranslationRevisionInterface $revision): void
    {
        $author = $revision->getAuthor();
        $label = $this->labelFor($revision->getSubject());
        $url = $this->editorUrl($revision);

        foreach ($this->userRepository->findTranslationModerators() as $moderator) {
            if ($author instanceof User && $moderator->getId() === $author->getId()) {
                continue;
            }

            $this->notify(
                $moderator,
                NotificationType::TranslationSubmitted,
                'app.notification.translation_submitted_title',
                'app.notification.translation_submitted_message',
                [
                    '%label%' => $label,
                    '%locale%' => strtoupper($revision->getLocale()),
                    '%author%' => $author instanceof User ? $author->getScreenName() : '',
                ],
                $url,
                'email/translation/submitted.html.twig',
                'app.email.translation_submitted_subject',
                $revision,
                $label,
            );
        }
    }

    /**
     * Review outcome → the contributor (skipped on self-review).
     */
    public function notifyReviewed(TranslationRevisionInterface $revision, bool $approved): void
    {
        $author = $revision->getAuthor();
        $reviewer = $revision->getReviewedBy();
        if (!$author instanceof User) {
            return;
        }
        if ($reviewer instanceof User && $reviewer->getId() === $author->getId()) {
            return;
        }

        $label = $this->labelFor($revision->getSubject());
        $titleKey = $approved ? 'app.notification.translation_approved_title' : 'app.notification.translation_rejected_title';
        $messageKey = $approved ? 'app.notification.translation_approved_message' : 'app.notification.translation_rejected_message';

        $this->notify(
            $author,
            NotificationType::TranslationReviewed,
            $titleKey,
            $messageKey,
            [
                '%label%' => $label,
                '%locale%' => strtoupper($revision->getLocale()),
                '%comment%' => $revision->getReviewComment() ?? '',
            ],
            $this->editorUrl($revision),
            'email/translation/reviewed.html.twig',
            $approved ? 'app.email.translation_approved_subject' : 'app.email.translation_rejected_subject',
            $revision,
            $label,
            ['approved' => $approved],
        );
    }

    /**
     * A source change flagged this translation outdated → its credited
     * translator (F19.8 field), when there is one.
     */
    public function notifySourceOutdated(User $translatorUser, Page|Archetype|MenuCategory|Deck $content, string $locale): void
    {
        $label = $this->labelFor($content);
        [$routeType, $routeId] = $this->routeTarget($content);
        $relativeUrl = $this->urlGenerator->generate('app_admin_translation_edit', ['type' => $routeType, 'id' => $routeId, 'locale' => $locale]);

        $this->notifyRaw(
            $translatorUser,
            NotificationType::TranslationSourceOutdated,
            'app.notification.translation_outdated_title',
            'app.notification.translation_outdated_message',
            ['%label%' => $label, '%locale%' => strtoupper($locale)],
            $relativeUrl,
            'email/translation/outdated.html.twig',
            'app.email.translation_outdated_subject',
            $label,
            $locale,
            $this->urlGenerator->generate('app_admin_translation_edit', ['type' => $routeType, 'id' => $routeId, 'locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }

    /**
     * @param array<string, string> $parameters
     * @param array<string, mixed>  $extraContext
     */
    private function notify(
        User $recipient,
        NotificationType $type,
        string $titleKey,
        string $messageKey,
        array $parameters,
        string $relativeUrl,
        string $emailTemplate,
        string $emailSubjectKey,
        TranslationRevisionInterface $revision,
        string $label,
        array $extraContext = [],
    ): void {
        $absoluteUrl = $this->urlGenerator->generate(
            'app_admin_translation_edit',
            $this->editorRouteParameters($revision),
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->createInAppNotification($recipient, $type, $titleKey, $messageKey, $parameters, $relativeUrl);
        $this->sendEmail($recipient, $type, $emailTemplate, $emailSubjectKey, $label, $revision->getLocale(), $absoluteUrl, array_merge($extraContext, ['comment' => $revision->getReviewComment()]));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function notifyRaw(
        User $recipient,
        NotificationType $type,
        string $titleKey,
        string $messageKey,
        array $parameters,
        string $relativeUrl,
        string $emailTemplate,
        string $emailSubjectKey,
        string $label,
        string $locale,
        string $absoluteUrl,
    ): void {
        $this->createInAppNotification($recipient, $type, $titleKey, $messageKey, $parameters, $relativeUrl);
        $this->sendEmail($recipient, $type, $emailTemplate, $emailSubjectKey, $label, $locale, $absoluteUrl, []);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function createInAppNotification(User $recipient, NotificationType $type, string $titleKey, string $messageKey, array $parameters, string $url): void
    {
        if (!$recipient->isNotificationEnabled($type, 'inApp')) {
            return;
        }

        $recipientLocale = $recipient->getPreferredLocale();

        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setType($type);
        $notification->setTitle($this->translator->trans($titleKey, $parameters, null, $recipientLocale));
        $notification->setMessage($this->translator->trans($messageKey, $parameters, null, $recipientLocale));
        $notification->setContext(['url' => $url]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $extraContext
     */
    private function sendEmail(User $recipient, NotificationType $type, string $template, string $subjectKey, string $label, string $targetLocale, string $editorUrl, array $extraContext): void
    {
        if (!$recipient->isNotificationEnabled($type, 'email')) {
            return;
        }

        $recipientLocale = $recipient->getPreferredLocale();

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailSender, $this->mailSenderName))
            ->to(new Address($recipient->getEmail(), $recipient->getScreenName()))
            ->subject($this->translator->trans($subjectKey, ['%label%' => $label, '%locale%' => strtoupper($targetLocale)], null, $recipientLocale))
            ->htmlTemplate($template)
            ->context(array_merge([
                'label' => $label,
                'targetLocale' => strtoupper($targetLocale),
                'editorUrl' => $editorUrl,
                'recipient' => $recipient,
                'locale' => $recipientLocale,
            ], $extraContext));

        $this->mailer->send($email);
    }

    private function editorUrl(TranslationRevisionInterface $revision): string
    {
        return $this->urlGenerator->generate('app_admin_translation_edit', $this->editorRouteParameters($revision));
    }

    /**
     * @return array{type: string, id: int, locale: string}
     */
    private function editorRouteParameters(TranslationRevisionInterface $revision): array
    {
        [$routeType, $routeId] = $this->routeTarget($revision->getSubject());

        return ['type' => $routeType, 'id' => $routeId, 'locale' => $revision->getLocale()];
    }

    /**
     * Variant work opens in its archetype's combined context (F9.13).
     *
     * @return array{string, int}
     */
    private function routeTarget(Page|Archetype|MenuCategory|Deck $content): array
    {
        if ($content instanceof Deck) {
            $archetype = $content->getArchetype();
            $archetypeId = $archetype?->getId();
            if (null !== $archetypeId) {
                return ['archetype', $archetypeId];
            }
        }

        $id = $content->getId();
        \assert(null !== $id);

        return [match (true) {
            $content instanceof Page => 'page',
            $content instanceof Archetype => 'archetype',
            $content instanceof MenuCategory => 'menu_category',
            default => 'archetype',
        }, $id];
    }

    private function labelFor(Page|Archetype|MenuCategory|Deck $content): string
    {
        if ($content instanceof Page) {
            return $content->getTranslation($this->sourceLocale)?->getTitle() ?? $content->getSlug();
        }
        if ($content instanceof Archetype) {
            return $content->getName();
        }
        if ($content instanceof MenuCategory) {
            return $content->getTranslation($this->sourceLocale)?->getName() ?? \sprintf('#%d', $content->getId() ?? 0);
        }

        $archetypeName = $content->getArchetype()?->getName();

        return null !== $archetypeName ? \sprintf('%s — %s', $archetypeName, $content->getName()) : $content->getName();
    }
}
