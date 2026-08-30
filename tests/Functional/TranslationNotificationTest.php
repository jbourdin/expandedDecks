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

namespace App\Tests\Functional;

use App\Entity\Notification;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Service\Translation\TranslationDraftProvider;
use App\Service\Translation\TranslationReviewService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see docs/features.md F9.15 — Translation workflow notifications
 */
class TranslationNotificationTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function loginByEmail(string $email): User
    {
        $user = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
        \assert($user instanceof User);
        $this->client->loginUser($user);

        return $user;
    }

    private function createPageWithSource(string $slug): Page
    {
        $em = $this->getEntityManager();

        $page = new Page();
        $page->setSlug($slug);

        $sourceTranslation = new PageTranslation();
        $sourceTranslation->setPage($page);
        $sourceTranslation->setLocale('en');
        $sourceTranslation->setTitle('Notification test');
        $sourceTranslation->setContent('English content');
        $page->addTranslation($sourceTranslation);

        $em->persist($page);
        $em->persist($sourceTranslation);
        $em->flush();

        return $page;
    }

    /**
     * @return list<Notification>
     */
    private function notificationsFor(User $recipient, NotificationType $type): array
    {
        return $this->getEntityManager()->getRepository(Notification::class)->findBy([
            'recipient' => $recipient,
            'type' => $type,
        ]);
    }

    public function testFullWorkflowNotifiesTheRightPeople(): void
    {
        $em = $this->getEntityManager();
        $page = $this->createPageWithSource('notify-workflow');
        $translator = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => 'translator@example.com']);
        $admin = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        \assert($translator instanceof User && $admin instanceof User);

        /** @var TranslationDraftProvider $draftProvider */
        $draftProvider = static::getContainer()->get(TranslationDraftProvider::class);
        /** @var TranslationReviewService $reviewService */
        $reviewService = static::getContainer()->get(TranslationReviewService::class);

        // Submission notifies the moderators, never the author.
        $this->loginByEmail('translator@example.com');
        $draft = $draftProvider->findOrCreateDraft($page, 'fr');
        \assert($draft instanceof \App\Entity\PageTranslationRevision);
        $draft->setTitle('Titre notifié');
        $reviewService->submit($draft);

        self::assertCount(1, $this->notificationsFor($admin, NotificationType::TranslationSubmitted));
        self::assertCount(0, $this->notificationsFor($translator, NotificationType::TranslationSubmitted));

        // Rejection notifies the contributor with the comment.
        $this->loginByEmail('admin@example.com');
        $reviewService->reject($draft, 'Tutoiement requis.');

        $rejectedNotifications = $this->notificationsFor($translator, NotificationType::TranslationReviewed);
        self::assertCount(1, $rejectedNotifications);
        self::assertStringContainsString('Tutoiement requis.', $rejectedNotifications[0]->getMessage());

        // Rework + resubmit + approval notifies the contributor again.
        $this->loginByEmail('translator@example.com');
        $reviewService->rework($draft);
        $reviewService->submit($draft);

        $this->loginByEmail('admin@example.com');
        $reviewService->approve($draft);

        self::assertCount(2, $this->notificationsFor($translator, NotificationType::TranslationReviewed));

        // A later source change notifies the credited translator — once.
        $sourceTranslation = $em->getRepository(PageTranslation::class)->findOneBy(['page' => $page, 'locale' => 'en']);
        \assert($sourceTranslation instanceof PageTranslation);
        $sourceTranslation->setContent('Fresh English content');
        $em->flush();

        self::assertCount(1, $this->notificationsFor($translator, NotificationType::TranslationSourceOutdated));

        // Still outdated: a second source edit must not re-notify.
        $sourceTranslation->setContent('Even fresher English content');
        $em->flush();

        self::assertCount(1, $this->notificationsFor($translator, NotificationType::TranslationSourceOutdated));
    }

    public function testDisabledPreferencesSuppressBothChannels(): void
    {
        $em = $this->getEntityManager();
        $page = $this->createPageWithSource('notify-prefs-off');
        $translator = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => 'translator@example.com']);
        \assert($translator instanceof User);
        $translator->setNotificationPreferences([
            NotificationType::TranslationReviewed->value => ['email' => false, 'inApp' => false],
        ]);
        $em->flush();

        /** @var TranslationDraftProvider $draftProvider */
        $draftProvider = static::getContainer()->get(TranslationDraftProvider::class);
        /** @var TranslationReviewService $reviewService */
        $reviewService = static::getContainer()->get(TranslationReviewService::class);

        $this->loginByEmail('translator@example.com');
        $draft = $draftProvider->findOrCreateDraft($page, 'fr');
        \assert($draft instanceof \App\Entity\PageTranslationRevision);
        $draft->setTitle('Sans notification');
        $reviewService->submit($draft);

        $this->loginByEmail('admin@example.com');
        $reviewService->reject($draft, 'Silence.');

        self::assertCount(0, $this->notificationsFor($translator, NotificationType::TranslationReviewed));
    }

    public function testAuthorlessRevisionNotifiesNobody(): void
    {
        $page = $this->createPageWithSource('notify-authorless');

        /** @var \App\Service\Translation\TranslationNotificationService $notificationService */
        $notificationService = static::getContainer()->get(\App\Service\Translation\TranslationNotificationService::class);

        // Auto-validated snapshots (editor saves, backfill) have no author:
        // reviewing one must never explode nor notify anyone.
        $revision = $this->getEntityManager()->getRepository(\App\Entity\PageTranslationRevision::class)
            ->findOneBy(['page' => $page, 'locale' => 'en']);
        \assert($revision instanceof \App\Entity\PageTranslationRevision);
        self::assertNull($revision->getAuthor());

        $notificationService->notifyReviewed($revision, true);

        self::assertCount(0, $this->getEntityManager()->getRepository(Notification::class)->findBy(['type' => NotificationType::TranslationReviewed]));
    }

    public function testSelfReviewDoesNotNotifyTheReviewer(): void
    {
        $em = $this->getEntityManager();
        $page = $this->createPageWithSource('notify-self-review');

        // Give the admin translation rights so they can author, too.
        $admin = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        \assert($admin instanceof User);
        $admin->setRoles([...$admin->getRoles(), 'ROLE_TRANSLATION_EDITOR']);
        $admin->setTranslationLocales(['fr']);
        $em->flush();

        /** @var TranslationDraftProvider $draftProvider */
        $draftProvider = static::getContainer()->get(TranslationDraftProvider::class);
        /** @var TranslationReviewService $reviewService */
        $reviewService = static::getContainer()->get(TranslationReviewService::class);

        $this->loginByEmail('admin@example.com');
        $draft = $draftProvider->findOrCreateDraft($page, 'fr');
        \assert($draft instanceof \App\Entity\PageTranslationRevision);
        $draft->setTitle('Auto-relecture');
        $reviewService->submit($draft);

        // The submitting moderator is not notified about their own submission.
        self::assertCount(0, $this->notificationsFor($admin, NotificationType::TranslationSubmitted));

        $reviewService->approve($draft);

        // Self-approval produces no review notification either.
        self::assertCount(0, $this->notificationsFor($admin, NotificationType::TranslationReviewed));
    }
}
