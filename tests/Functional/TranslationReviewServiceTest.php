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

use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Entity\Deck;
use App\Entity\DeckTranslation;
use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Entity\User;
use App\Enum\DeckFormat;
use App\Enum\TranslationRevisionState;
use App\Service\Translation\StaleTranslationSourceException;
use App\Service\Translation\TranslationDraftProvider;
use App\Service\Translation\TranslationReviewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

/**
 * @see docs/features.md F9.9 — Translation review workflow
 */
class TranslationReviewServiceTest extends AbstractFunctionalTest
{
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function getReviewService(): TranslationReviewService
    {
        /** @var TranslationReviewService $service */
        $service = static::getContainer()->get(TranslationReviewService::class);

        return $service;
    }

    private function getDraftProvider(): TranslationDraftProvider
    {
        /** @var TranslationDraftProvider $provider */
        $provider = static::getContainer()->get(TranslationDraftProvider::class);

        return $provider;
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
        $sourceTranslation->setTitle('Review test');
        $sourceTranslation->setContent('English content');

        $em->persist($page);
        $em->persist($sourceTranslation);
        $em->flush();

        return $page;
    }

    public function testFullLifecycleSubmitApproveLandsOnLiveRow(): void
    {
        $em = $this->getEntityManager();
        $page = $this->createPageWithSource('review-lifecycle');

        $translator = $this->loginByEmail('translator@example.com');

        $draft = $this->getDraftProvider()->findOrCreateDraft($page, 'fr');
        self::assertInstanceOf(PageTranslationRevision::class, $draft);
        self::assertSame(TranslationRevisionState::Draft, $draft->getState());
        self::assertNotNull($draft->getSourceRevision());
        self::assertSame($translator->getId(), $draft->getAuthor()?->getId());

        // Resuming a session (after the draft was flushed) returns the same
        // pending revision (US-T3).
        $em->flush();
        self::assertSame($draft, $this->getDraftProvider()->findOrCreateDraft($page, 'fr'));

        $draft->setTitle('Test de revue');
        $draft->setContent('Contenu français');
        $this->getReviewService()->submit($draft);
        self::assertSame(TranslationRevisionState::PendingReview, $draft->getState());

        $moderator = $this->loginByEmail('admin@example.com');
        $this->getReviewService()->approve($draft);

        self::assertSame(TranslationRevisionState::Validated, $draft->getState());
        self::assertSame($moderator->getId(), $draft->getReviewedBy()?->getId());
        self::assertNotNull($draft->getReviewedAt());

        $live = $em->getRepository(PageTranslation::class)->findOneBy(['page' => $page, 'locale' => 'fr']);
        self::assertInstanceOf(PageTranslation::class, $live);
        self::assertSame('Test de revue', $live->getTitle());
        self::assertSame('Contenu français', $live->getContent());
        self::assertSame($translator->getId(), $live->getTranslator()?->getId());
        self::assertFalse($live->isSourceOutdated());

        // The approval write itself must not snapshot a duplicate revision:
        // en source + the approved fr revision, nothing else.
        self::assertCount(2, $em->getRepository(PageTranslationRevision::class)->findBy(['page' => $page]));
    }

    public function testRejectCarriesCommentAndReworkReturnsToDraft(): void
    {
        $page = $this->createPageWithSource('review-reject');

        $this->loginByEmail('translator@example.com');
        $draft = $this->getDraftProvider()->findOrCreateDraft($page, 'fr');
        \assert($draft instanceof PageTranslationRevision);
        $draft->setTitle('Brouillon');
        $this->getReviewService()->submit($draft);

        $this->loginByEmail('admin@example.com');
        $this->getReviewService()->reject($draft, 'Tutoiement requis.');

        self::assertSame(TranslationRevisionState::Rejected, $draft->getState());
        self::assertSame('Tutoiement requis.', $draft->getReviewComment());
        self::assertNotNull($draft->getReviewedBy());

        $this->loginByEmail('translator@example.com');
        // A rejected revision is what the translator resumes (US-T3)…
        self::assertSame($draft, $this->getDraftProvider()->findOrCreateDraft($page, 'fr'));
        // …and rework returns it to draft for editing.
        $this->getReviewService()->rework($draft);
        self::assertSame(TranslationRevisionState::Draft, $draft->getState());
    }

    public function testStaleSourceSubmissionIsRefused(): void
    {
        $em = $this->getEntityManager();
        $page = $this->createPageWithSource('review-stale');

        $this->loginByEmail('translator@example.com');
        $draft = $this->getDraftProvider()->findOrCreateDraft($page, 'fr');
        \assert($draft instanceof PageTranslationRevision);
        $draft->setTitle('Basé sur v1');
        $em->flush();

        // The source moves on while the translator works.
        $sourceTranslation = $em->getRepository(PageTranslation::class)->findOneBy(['page' => $page, 'locale' => 'en']);
        \assert($sourceTranslation instanceof PageTranslation);
        $sourceTranslation->setContent('Updated English content');
        $em->flush();

        $this->expectException(StaleTranslationSourceException::class);
        $this->getReviewService()->submit($draft);
    }

    public function testApproveRequiresPendingReviewState(): void
    {
        $page = $this->createPageWithSource('review-no-shortcut');

        $this->loginByEmail('translator@example.com');
        $draft = $this->getDraftProvider()->findOrCreateDraft($page, 'fr');
        $this->getEntityManager()->flush();

        // No shortcut: a moderator cannot validate a draft that was never
        // submitted, their own or anyone else's.
        $this->loginByEmail('admin@example.com');
        $this->expectException(NotEnabledTransitionException::class);
        $this->getReviewService()->approve($draft);
    }

    public function testGuardsDenyWrongRoles(): void
    {
        $page = $this->createPageWithSource('review-guards');

        $this->loginByEmail('translator@example.com');
        $draft = $this->getDraftProvider()->findOrCreateDraft($page, 'fr');
        $this->getReviewService()->submit($draft);

        // A translator cannot moderate…
        try {
            $this->getReviewService()->approve($draft);
            self::fail('Translator must not approve.');
        } catch (AccessDeniedException) {
        }

        // …and a moderator without the editor role cannot author: admin has
        // no translationLocales, so submitting is denied by the voter.
        $this->loginByEmail('admin@example.com');
        $otherDraft = $this->getDraftProvider()->findOrCreateDraft($this->createPageWithSource('review-guards-2'), 'fr');
        $this->expectException(AccessDeniedException::class);
        $this->getReviewService()->submit($otherDraft);
    }

    public function testApprovalCreatesLiveRowsForEveryContentType(): void
    {
        $em = $this->getEntityManager();

        $archetype = new Archetype();
        $archetype->setName('Review Workflow Subject');
        $archetypeSource = new ArchetypeTranslation();
        $archetypeSource->setArchetype($archetype);
        $archetypeSource->setLocale('en');
        $archetypeSource->setName('Review Workflow Subject');
        $em->persist($archetype);
        $em->persist($archetypeSource);

        $menuCategory = new MenuCategory();
        $menuCategorySource = new MenuCategoryTranslation();
        $menuCategorySource->setMenuCategory($menuCategory);
        $menuCategorySource->setLocale('en');
        $menuCategorySource->setName('Review Guides');
        $em->persist($menuCategory);
        $em->persist($menuCategorySource);

        $variant = new Deck();
        $variant->setName('Review variant');
        $variant->setArchetype($archetype);
        $variant->setOwner(null);
        $variant->setFormat(DeckFormat::Expanded);
        $variant->setNotes('English variant notes');
        $em->persist($variant);

        $em->flush();

        $translator = $this->loginByEmail('translator@example.com');
        $draftProvider = $this->getDraftProvider();
        $reviewService = $this->getReviewService();

        $archetypeDraft = $draftProvider->findOrCreateDraft($archetype, 'fr');
        \assert($archetypeDraft instanceof \App\Entity\ArchetypeTranslationRevision);
        $archetypeDraft->setName('Sujet de revue');
        $reviewService->submit($archetypeDraft);

        $menuCategoryDraft = $draftProvider->findOrCreateDraft($menuCategory, 'fr');
        \assert($menuCategoryDraft instanceof \App\Entity\MenuCategoryTranslationRevision);
        $menuCategoryDraft->setName('Guides de revue');
        $reviewService->submit($menuCategoryDraft);

        $deckDraft = $draftProvider->findOrCreateDraft($variant, 'fr');
        \assert($deckDraft instanceof \App\Entity\DeckTranslationRevision);
        $deckDraft->setNotes('Notes de variante en français');
        $reviewService->submit($deckDraft);

        $this->loginByEmail('admin@example.com');
        $reviewService->approve($archetypeDraft);
        $reviewService->approve($menuCategoryDraft);
        $reviewService->approve($deckDraft);

        $archetypeLive = $em->getRepository(ArchetypeTranslation::class)->findOneBy(['archetype' => $archetype, 'locale' => 'fr']);
        self::assertInstanceOf(ArchetypeTranslation::class, $archetypeLive);
        self::assertSame('Sujet de revue', $archetypeLive->getName());
        self::assertSame($translator->getId(), $archetypeLive->getTranslator()?->getId());

        $menuCategoryLive = $em->getRepository(MenuCategoryTranslation::class)->findOneBy(['menuCategory' => $menuCategory, 'locale' => 'fr']);
        self::assertInstanceOf(MenuCategoryTranslation::class, $menuCategoryLive);
        self::assertSame('Guides de revue', $menuCategoryLive->getName());

        $deckLive = $em->getRepository(DeckTranslation::class)->findOneBy(['deck' => $variant, 'locale' => 'fr']);
        self::assertInstanceOf(DeckTranslation::class, $deckLive);
        self::assertSame('Notes de variante en français', $deckLive->getNotes());
        self::assertFalse($deckLive->isSourceOutdated());
    }
}
