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
use App\Entity\ArchetypeTranslation;
use App\Entity\ArchetypeTranslationRevision;
use App\Entity\Deck;
use App\Entity\DeckTranslation;
use App\Entity\DeckTranslationRevision;
use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use App\Entity\MenuCategoryTranslationRevision;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Entity\TranslationRevisionInterface;
use App\Entity\User;
use App\Enum\TranslationRevisionState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Entry point of a translation session (US-T3 / US-T4).
 *
 * Returns the existing not-yet-validated revision for a (content, locale)
 * pair when one exists — a translator resumes their pending work — or
 * creates a new draft prefilled from the live translation row (the latest
 * validated content) and pinned to the latest source revision.
 *
 * The caller flushes; a freshly created draft is persisted but not flushed.
 *
 * @see docs/features.md F9.9 — Translation review workflow
 */
final readonly class TranslationDraftProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LatestSourceRevisionProvider $latestSourceRevisionProvider,
        private Security $security,
    ) {
    }

    public function findOrCreateDraft(Page|Archetype|MenuCategory|Deck $content, string $locale): TranslationRevisionInterface
    {
        $revisionClass = match (true) {
            $content instanceof Page => PageTranslationRevision::class,
            $content instanceof Archetype => ArchetypeTranslationRevision::class,
            $content instanceof MenuCategory => MenuCategoryTranslationRevision::class,
            $content instanceof Deck => DeckTranslationRevision::class,
        };

        $latest = $this->entityManager->getRepository($revisionClass)->findOneBy(
            [$revisionClass::subjectFieldName() => $content, 'locale' => $locale],
            ['id' => 'DESC'],
        );
        if ($latest instanceof TranslationRevisionInterface && TranslationRevisionState::Validated !== $latest->getState()) {
            return $latest;
        }

        $draft = $this->createPrefilledDraft($content, $locale);
        $draft->setState(TranslationRevisionState::Draft);

        $author = $this->security->getUser();
        if ($author instanceof User) {
            $draft->setAuthor($author);
        }

        $this->linkLatestSource($draft);
        $this->entityManager->persist($draft);

        return $draft;
    }

    /**
     * Prefill comes from the live translation row when it exists (US-T4 —
     * the live row IS the latest validated content); a transient empty
     * translation seeds the factory otherwise.
     */
    private function createPrefilledDraft(Page|Archetype|MenuCategory|Deck $content, string $locale): TranslationRevisionInterface
    {
        if ($content instanceof Page) {
            $live = $this->entityManager->getRepository(PageTranslation::class)
                ->findOneBy(['page' => $content, 'locale' => $locale]);
            if (!$live instanceof PageTranslation) {
                $live = new PageTranslation();
                $live->setPage($content);
                $live->setLocale($locale);
            }

            return PageTranslationRevision::fromTranslation($live);
        }

        if ($content instanceof Archetype) {
            $live = $this->entityManager->getRepository(ArchetypeTranslation::class)
                ->findOneBy(['archetype' => $content, 'locale' => $locale]);
            if (!$live instanceof ArchetypeTranslation) {
                $live = new ArchetypeTranslation();
                $live->setArchetype($content);
                $live->setLocale($locale);
            }

            return ArchetypeTranslationRevision::fromTranslation($live);
        }

        if ($content instanceof MenuCategory) {
            $live = $this->entityManager->getRepository(MenuCategoryTranslation::class)
                ->findOneBy(['menuCategory' => $content, 'locale' => $locale]);
            if (!$live instanceof MenuCategoryTranslation) {
                $live = new MenuCategoryTranslation();
                $live->setMenuCategory($content);
                $live->setLocale($locale);
            }

            return MenuCategoryTranslationRevision::fromTranslation($live);
        }

        $live = $this->entityManager->getRepository(DeckTranslation::class)
            ->findOneBy(['deck' => $content, 'locale' => $locale]);
        if (!$live instanceof DeckTranslation) {
            $live = new DeckTranslation();
            $live->setDeck($content);
            $live->setLocale($locale);
        }

        return DeckTranslationRevision::fromTranslation($live);
    }

    private function linkLatestSource(TranslationRevisionInterface $draft): void
    {
        $latestSource = $this->latestSourceRevisionProvider->latestFor($draft);
        if (null === $latestSource) {
            return;
        }

        if ($draft instanceof PageTranslationRevision && $latestSource instanceof PageTranslationRevision) {
            $draft->setSourceRevision($latestSource);
        } elseif ($draft instanceof ArchetypeTranslationRevision && $latestSource instanceof ArchetypeTranslationRevision) {
            $draft->setSourceRevision($latestSource);
        } elseif ($draft instanceof MenuCategoryTranslationRevision && $latestSource instanceof MenuCategoryTranslationRevision) {
            $draft->setSourceRevision($latestSource);
        } elseif ($draft instanceof DeckTranslationRevision && $latestSource instanceof DeckTranslationRevision) {
            $draft->setSourceRevision($latestSource);
        }
    }
}
