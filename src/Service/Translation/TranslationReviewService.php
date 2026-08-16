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

use App\Entity\ArchetypeTranslation;
use App\Entity\ArchetypeTranslationRevision;
use App\Entity\DeckTranslation;
use App\Entity\DeckTranslationRevision;
use App\Entity\MenuCategoryTranslation;
use App\Entity\MenuCategoryTranslationRevision;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Entity\TranslationRevisionInterface;
use App\Entity\User;
use App\Security\TranslationTarget;
use App\Security\Voter\TranslationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Lifecycle transitions of translation revisions (F9.9).
 *
 * Approval is the single write path to live translation rows: it copies the
 * revision's `#[Translatable]` fields into the live row (creating it on first
 * approval of a locale), credits the contributor as the row's translator
 * (F19.8 byline), and recomputes `sourceOutdated`. The revision listener is
 * suppressed during that write — the approved revision IS the history entry.
 *
 * The no-shortcut invariant is structural: `approve` only exists from
 * `pending_review`, so a moderator's own work follows the same explicit
 * submit-then-approve path as anyone else's.
 *
 * @see docs/features.md F9.9 — Translation review workflow
 * @see docs/technicalities/translation_workflow.md
 */
final readonly class TranslationReviewService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkflowInterface $translationReviewStateMachine,
        private Security $security,
        private LatestSourceRevisionProvider $latestSourceRevisionProvider,
        private TranslationRevisionSuppression $suppression,
    ) {
    }

    /**
     * @throws StaleTranslationSourceException when a newer source revision
     *                                         exists than the pinned one (US-T5)
     */
    public function submit(TranslationRevisionInterface $revision): void
    {
        $this->denyUnlessCanTranslate($revision);

        $latestSource = $this->latestSourceRevisionProvider->latestFor($revision);
        if ($latestSource instanceof TranslationRevisionInterface && $latestSource !== $revision->getSourceRevision()) {
            throw new StaleTranslationSourceException();
        }

        $this->translationReviewStateMachine->apply($revision, 'submit');
        $this->entityManager->flush();
    }

    public function approve(TranslationRevisionInterface $revision): void
    {
        $this->denyUnlessModerator();

        $this->translationReviewStateMachine->apply($revision, 'approve');
        $this->stampReview($revision);

        $liveTranslation = $this->findOrCreateLiveTranslation($revision);
        $this->applyRevision($revision, $liveTranslation);

        $author = $revision->getAuthor();
        if ($author instanceof User) {
            $this->creditTranslator($liveTranslation, $author);
        }

        // The source may have moved again between submission and approval:
        // recompute instead of blindly clearing.
        $latestSource = $this->latestSourceRevisionProvider->latestFor($revision);
        $upToDate = !$latestSource instanceof TranslationRevisionInterface || $latestSource === $revision->getSourceRevision();
        $this->setSourceOutdated($liveTranslation, !$upToDate);

        $this->suppression->suppress();
        try {
            $this->entityManager->flush();
        } finally {
            $this->suppression->release();
        }
    }

    public function reject(TranslationRevisionInterface $revision, string $comment): void
    {
        $this->denyUnlessModerator();

        $this->translationReviewStateMachine->apply($revision, 'reject');
        $this->stampReview($revision);
        $revision->setReviewComment($comment);

        $this->entityManager->flush();
    }

    public function rework(TranslationRevisionInterface $revision): void
    {
        $this->denyUnlessCanTranslate($revision);

        $this->translationReviewStateMachine->apply($revision, 'rework');
        $this->entityManager->flush();
    }

    private function denyUnlessCanTranslate(TranslationRevisionInterface $revision): void
    {
        $target = new TranslationTarget($revision->getSubject(), $revision->getLocale());
        if (!$this->security->isGranted(TranslationVoter::TRANSLATE, $target)) {
            throw new AccessDeniedException('Not allowed to translate this content into this locale.');
        }
    }

    private function denyUnlessModerator(): void
    {
        if (!$this->security->isGranted('ROLE_TRANSLATION_MODERATOR')) {
            throw new AccessDeniedException('Not allowed to moderate translations.');
        }
    }

    private function stampReview(TranslationRevisionInterface $revision): void
    {
        $reviewer = $this->security->getUser();
        if ($reviewer instanceof User) {
            $revision->setReviewedBy($reviewer);
        }
        $revision->setReviewedAt(new \DateTimeImmutable());
    }

    private function findOrCreateLiveTranslation(TranslationRevisionInterface $revision): PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation
    {
        $locale = $revision->getLocale();

        if ($revision instanceof PageTranslationRevision) {
            $live = $this->entityManager->getRepository(PageTranslation::class)
                ->findOneBy(['page' => $revision->getPage(), 'locale' => $locale]);
            if (!$live instanceof PageTranslation) {
                $live = new PageTranslation();
                $live->setPage($revision->getPage());
                $live->setLocale($locale);
                $this->entityManager->persist($live);
            }

            return $live;
        }

        if ($revision instanceof ArchetypeTranslationRevision) {
            $live = $this->entityManager->getRepository(ArchetypeTranslation::class)
                ->findOneBy(['archetype' => $revision->getArchetype(), 'locale' => $locale]);
            if (!$live instanceof ArchetypeTranslation) {
                $live = new ArchetypeTranslation();
                $live->setArchetype($revision->getArchetype());
                $live->setLocale($locale);
                $this->entityManager->persist($live);
            }

            return $live;
        }

        if ($revision instanceof MenuCategoryTranslationRevision) {
            $live = $this->entityManager->getRepository(MenuCategoryTranslation::class)
                ->findOneBy(['menuCategory' => $revision->getMenuCategory(), 'locale' => $locale]);
            if (!$live instanceof MenuCategoryTranslation) {
                $live = new MenuCategoryTranslation();
                $live->setMenuCategory($revision->getMenuCategory());
                $live->setLocale($locale);
                $this->entityManager->persist($live);
            }

            return $live;
        }

        \assert($revision instanceof DeckTranslationRevision);
        $live = $this->entityManager->getRepository(DeckTranslation::class)
            ->findOneBy(['deck' => $revision->getDeck(), 'locale' => $locale]);
        if (!$live instanceof DeckTranslation) {
            $live = new DeckTranslation();
            $live->setDeck($revision->getDeck());
            $live->setLocale($locale);
            $this->entityManager->persist($live);
        }

        return $live;
    }

    private function applyRevision(TranslationRevisionInterface $revision, PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation $liveTranslation): void
    {
        if ($revision instanceof PageTranslationRevision && $liveTranslation instanceof PageTranslation) {
            $revision->applyTo($liveTranslation);
        } elseif ($revision instanceof ArchetypeTranslationRevision && $liveTranslation instanceof ArchetypeTranslation) {
            $revision->applyTo($liveTranslation);
        } elseif ($revision instanceof MenuCategoryTranslationRevision && $liveTranslation instanceof MenuCategoryTranslation) {
            $revision->applyTo($liveTranslation);
        } elseif ($revision instanceof DeckTranslationRevision && $liveTranslation instanceof DeckTranslation) {
            $revision->applyTo($liveTranslation);
        }
    }

    private function creditTranslator(PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation $liveTranslation, User $translator): void
    {
        // MenuCategoryTranslation carries no public translator credit.
        if (!$liveTranslation instanceof MenuCategoryTranslation) {
            $liveTranslation->setTranslator($translator);
        }
    }

    private function setSourceOutdated(PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation $liveTranslation, bool $sourceOutdated): void
    {
        $liveTranslation->setSourceOutdated($sourceOutdated);
    }
}
