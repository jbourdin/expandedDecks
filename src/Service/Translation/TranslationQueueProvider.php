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
use App\Entity\Deck;
use App\Entity\DeckTranslation;
use App\Entity\DeckTranslationRevision;
use App\Entity\MenuCategoryTranslation;
use App\Entity\MenuCategoryTranslationRevision;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Entity\TranslationRevisionInterface;
use App\Entity\User;
use App\Enum\TranslationRevisionState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the translation queue (F9.12) in its two flavors:
 *
 * - contributor: my in-workflow revisions (draft / pending / rejected) plus
 *   outdated translations in my target locales;
 * - reviewer: every revision awaiting review plus every outdated translation.
 *
 * Deck (variant) work never surfaces as its own rows — it aggregates into
 * the owning archetype's row as pending/outdated counters, so the archetype
 * tab shows one row per context.
 *
 * @see docs/features.md F9.12 — Translation queue
 */
final readonly class TranslationQueueProvider
{
    private const string TYPE_PAGE = 'page';
    private const string TYPE_ARCHETYPE = 'archetype';
    private const string TYPE_MENU_CATEGORY = 'menu_category';

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.default_locale%')]
        private string $sourceLocale,
    ) {
    }

    /**
     * @return array{pages: list<array<string, mixed>>, archetypes: list<array<string, mixed>>, menuCategories: list<array<string, mixed>>}
     */
    public function contributorQueue(User $user): array
    {
        return $this->buildQueue($user, false);
    }

    /**
     * @return array{pages: list<array<string, mixed>>, archetypes: list<array<string, mixed>>, menuCategories: list<array<string, mixed>>}
     */
    public function reviewerQueue(User $user): array
    {
        return $this->buildQueue($user, true);
    }

    /**
     * @return array{pages: list<array<string, mixed>>, archetypes: list<array<string, mixed>>, menuCategories: list<array<string, mixed>>}
     */
    private function buildQueue(User $user, bool $reviewer): array
    {
        $pages = $this->collectItems(PageTranslationRevision::class, PageTranslation::class, 'page', self::TYPE_PAGE, $user, $reviewer);
        $archetypes = $this->collectItems(ArchetypeTranslationRevision::class, ArchetypeTranslation::class, 'archetype', self::TYPE_ARCHETYPE, $user, $reviewer);
        $menuCategories = $this->collectItems(MenuCategoryTranslationRevision::class, MenuCategoryTranslation::class, 'menuCategory', self::TYPE_MENU_CATEGORY, $user, $reviewer);
        $variants = $this->collectItems(DeckTranslationRevision::class, DeckTranslation::class, 'deck', 'deck', $user, $reviewer);

        $archetypes = $this->foldVariantsIntoArchetypes($archetypes, $variants, $user, $reviewer);

        return [
            'pages' => array_map(static fn (TranslationQueueItem $item): array => $item->toArray(), $this->withLabels($pages, self::TYPE_PAGE)),
            'archetypes' => array_map(static fn (TranslationQueueItem $item): array => $item->toArray(), $this->withLabels($archetypes, self::TYPE_ARCHETYPE)),
            'menuCategories' => array_map(static fn (TranslationQueueItem $item): array => $item->toArray(), $this->withLabels($menuCategories, self::TYPE_MENU_CATEGORY)),
        ];
    }

    /**
     * Merges in-workflow revisions and outdated live rows for one content
     * type, deduplicated on (content, locale) with revision state winning.
     *
     * @param class-string<TranslationRevisionInterface> $revisionClass
     * @param class-string                               $translationClass
     *
     * @return array<string, TranslationQueueItem> keyed by "contentId:locale"
     */
    private function collectItems(string $revisionClass, string $translationClass, string $subjectField, string $contentType, User $user, bool $reviewer): array
    {
        $items = [];

        $revisionQuery = $this->entityManager->createQueryBuilder()
            ->select('revision')
            ->from($revisionClass, 'revision')
            ->where('revision.state != :validated')
            ->andWhere('revision.locale != :sourceLocale')
            ->setParameter('validated', TranslationRevisionState::Validated)
            ->setParameter('sourceLocale', $this->sourceLocale);
        if ($reviewer) {
            $revisionQuery->andWhere('revision.state = :pending')
                ->setParameter('pending', TranslationRevisionState::PendingReview);
        } else {
            $revisionQuery->andWhere('revision.author = :author')
                ->setParameter('author', $user);
        }

        /** @var list<TranslationRevisionInterface> $revisions */
        $revisions = $revisionQuery->getQuery()->getResult();
        foreach ($revisions as $revision) {
            $subjectId = $this->subjectIdOf($revision);
            if (null === $subjectId) {
                continue;
            }
            $items[$subjectId.':'.$revision->getLocale()] = new TranslationQueueItem(
                $contentType,
                $subjectId,
                '',
                $revision->getLocale(),
                $revision->getState()->value,
                false,
            );
        }

        $outdatedQuery = $this->entityManager->createQueryBuilder()
            ->select('translation')
            ->from($translationClass, 'translation')
            ->where('translation.sourceOutdated = true');
        if (!$reviewer) {
            if ([] === $user->getTranslationLocales()) {
                return $items;
            }
            $outdatedQuery->andWhere('translation.locale IN (:locales)')
                ->setParameter('locales', $user->getTranslationLocales());
        }

        /** @var list<object> $outdatedRows */
        $outdatedRows = $outdatedQuery->getQuery()->getResult();
        foreach ($outdatedRows as $row) {
            [$subjectId, $locale] = $this->liveRowIdentity($row, $subjectField);
            if (null === $subjectId) {
                continue;
            }
            $key = $subjectId.':'.$locale;
            $existing = $items[$key] ?? null;
            $items[$key] = new TranslationQueueItem(
                $contentType,
                $subjectId,
                '',
                $locale,
                $existing?->state,
                true,
                null !== $existing ? $existing->pendingVariants : 0,
                null !== $existing ? $existing->outdatedVariants : 0,
            );
        }

        return $items;
    }

    /**
     * Deck items never surface directly: they become pending/outdated
     * counters on their archetype's row (one row per context).
     *
     * @param array<string, TranslationQueueItem> $archetypes
     * @param array<string, TranslationQueueItem> $variants
     *
     * @return array<string, TranslationQueueItem>
     */
    private function foldVariantsIntoArchetypes(array $archetypes, array $variants, User $user, bool $reviewer): array
    {
        if ([] === $variants) {
            return $archetypes;
        }

        $deckIds = array_values(array_unique(array_map(static fn (TranslationQueueItem $item): int => $item->contentId, array_values($variants))));

        /** @var list<array{deckId: int, archetypeId: int}> $mapping */
        $mapping = $this->entityManager->createQueryBuilder()
            ->select('deck.id AS deckId', 'archetype.id AS archetypeId')
            ->from(Deck::class, 'deck')
            ->join('deck.archetype', 'archetype')
            ->where('deck.id IN (:ids)')
            ->setParameter('ids', $deckIds)
            ->getQuery()
            ->getArrayResult();
        $archetypeByDeck = [];
        foreach ($mapping as $row) {
            $archetypeByDeck[$row['deckId']] = $row['archetypeId'];
        }

        foreach ($variants as $item) {
            $archetypeId = $archetypeByDeck[$item->contentId] ?? null;
            if (null === $archetypeId) {
                continue;
            }
            $key = $archetypeId.':'.$item->locale;
            $existing = $archetypes[$key] ?? new TranslationQueueItem(self::TYPE_ARCHETYPE, $archetypeId, '', $item->locale, null, false);
            $archetypes[$key] = new TranslationQueueItem(
                $existing->contentType,
                $existing->contentId,
                $existing->label,
                $existing->locale,
                $existing->state,
                $existing->sourceOutdated,
                $existing->pendingVariants + (null !== $item->state ? 1 : 0),
                $existing->outdatedVariants + ($item->sourceOutdated ? 1 : 0),
            );
        }

        return $archetypes;
    }

    /**
     * Resolves the source-locale display label for every item in one query.
     *
     * @param array<string, TranslationQueueItem> $items
     *
     * @return list<TranslationQueueItem>
     */
    private function withLabels(array $items, string $contentType): array
    {
        if ([] === $items) {
            return [];
        }

        $ids = array_values(array_unique(array_map(static fn (TranslationQueueItem $item): int => $item->contentId, array_values($items))));
        $labels = $this->labelsFor($contentType, $ids);

        $result = [];
        foreach ($items as $item) {
            $result[] = new TranslationQueueItem(
                $item->contentType,
                $item->contentId,
                $labels[$item->contentId] ?? \sprintf('#%d', $item->contentId),
                $item->locale,
                $item->state,
                $item->sourceOutdated,
                $item->pendingVariants,
                $item->outdatedVariants,
            );
        }
        usort($result, static fn (TranslationQueueItem $a, TranslationQueueItem $b): int => [$a->label, $a->locale] <=> [$b->label, $b->locale]);

        return $result;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, string>
     */
    private function labelsFor(string $contentType, array $ids): array
    {
        [$translationClass, $subjectField, $labelField] = match ($contentType) {
            self::TYPE_PAGE => [PageTranslation::class, 'page', 'title'],
            self::TYPE_ARCHETYPE => [ArchetypeTranslation::class, 'archetype', 'name'],
            self::TYPE_MENU_CATEGORY => [MenuCategoryTranslation::class, 'menuCategory', 'name'],
            default => throw new \LogicException(\sprintf('Unknown queue content type "%s".', $contentType)),
        };

        /** @var list<array{subjectId: int, label: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(\sprintf('IDENTITY(translation.%s) AS subjectId', $subjectField), \sprintf('translation.%s AS label', $labelField))
            ->from($translationClass, 'translation')
            ->where(\sprintf('translation.%s IN (:ids)', $subjectField))
            ->andWhere('translation.locale = :sourceLocale')
            ->setParameter('ids', $ids)
            ->setParameter('sourceLocale', $this->sourceLocale)
            ->getQuery()
            ->getArrayResult();

        $labels = [];
        foreach ($rows as $row) {
            $labels[(int) $row['subjectId']] = $row['label'];
        }

        return $labels;
    }

    private function subjectIdOf(TranslationRevisionInterface $revision): ?int
    {
        $subject = $revision->getSubject();

        return $subject->getId();
    }

    /**
     * @return array{?int, string}
     */
    private function liveRowIdentity(object $row, string $subjectField): array
    {
        if ($row instanceof PageTranslation) {
            return [$row->getPage()->getId(), $row->getLocale()];
        }
        if ($row instanceof ArchetypeTranslation) {
            return [$row->getArchetype()->getId(), $row->getLocale()];
        }
        if ($row instanceof MenuCategoryTranslation) {
            return [$row->getMenuCategory()->getId(), $row->getLocale()];
        }
        if ($row instanceof DeckTranslation) {
            return [$row->getDeck()->getId(), $row->getLocale()];
        }

        throw new \LogicException(\sprintf('Unknown live translation row "%s" (subject field "%s").', $row::class, $subjectField));
    }
}
