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
use App\Entity\BannedCard;
use App\Entity\BannedCardTranslation;
use App\Entity\BannedCardTranslationRevision;
use App\Entity\Deck;
use App\Entity\DeckTranslation;
use App\Entity\DeckTranslationRevision;
use App\Entity\MenuCategoryTranslation;
use App\Entity\MenuCategoryTranslationRevision;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Entity\StapleCard;
use App\Entity\StapleCardTranslation;
use App\Entity\StapleCardTranslationRevision;
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
    private const string TYPE_BANNED_CARD = 'banned_card';
    private const string TYPE_STAPLE_CARD = 'staple_card';

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.default_locale%')]
        private string $sourceLocale,
    ) {
    }

    /**
     * @return array{pages: list<array<string, mixed>>, archetypes: list<array<string, mixed>>, menuCategories: list<array<string, mixed>>, bannedCards: list<array<string, mixed>>, stapleCards: list<array<string, mixed>>}
     */
    public function contributorQueue(User $user): array
    {
        return $this->buildQueue($user, false);
    }

    /**
     * @return array{pages: list<array<string, mixed>>, archetypes: list<array<string, mixed>>, menuCategories: list<array<string, mixed>>, bannedCards: list<array<string, mixed>>, stapleCards: list<array<string, mixed>>}
     */
    public function reviewerQueue(User $user): array
    {
        return $this->buildQueue($user, true);
    }

    /**
     * @return array{pages: list<array<string, mixed>>, archetypes: list<array<string, mixed>>, menuCategories: list<array<string, mixed>>, bannedCards: list<array<string, mixed>>, stapleCards: list<array<string, mixed>>}
     */
    private function buildQueue(User $user, bool $reviewer): array
    {
        $pages = $this->collectItems(PageTranslationRevision::class, PageTranslation::class, 'page', self::TYPE_PAGE, $user, $reviewer);
        $archetypes = $this->collectItems(ArchetypeTranslationRevision::class, ArchetypeTranslation::class, 'archetype', self::TYPE_ARCHETYPE, $user, $reviewer);
        $menuCategories = $this->collectItems(MenuCategoryTranslationRevision::class, MenuCategoryTranslation::class, 'menuCategory', self::TYPE_MENU_CATEGORY, $user, $reviewer);
        $variants = $this->collectItems(DeckTranslationRevision::class, DeckTranslation::class, 'deck', 'deck', $user, $reviewer);

        $archetypes = $this->foldVariantsIntoArchetypes($archetypes, $variants, $user, $reviewer);

        // Cards (F9.17/F9.19): banned-card explanations and staple-card notes
        // each get their own tab. Contributors additionally see untranslated
        // cards with source copy — the worklist for opening a new locale.
        $cards = array_merge(
            array_values($this->collectItems(BannedCardTranslationRevision::class, BannedCardTranslation::class, 'bannedCard', self::TYPE_BANNED_CARD, $user, $reviewer)),
            array_values($this->collectItems(StapleCardTranslationRevision::class, StapleCardTranslation::class, 'stapleCard', self::TYPE_STAPLE_CARD, $user, $reviewer)),
        );
        if (!$reviewer) {
            $cards = array_merge($cards, $this->untranslatedCardItems($user, $cards));
        }
        $cards = $this->withCardLabels($cards);

        return [
            'pages' => array_map(static fn (TranslationQueueItem $item): array => $item->toArray(), $this->withLabels($pages, self::TYPE_PAGE)),
            'archetypes' => array_map(static fn (TranslationQueueItem $item): array => $item->toArray(), $this->withLabels($archetypes, self::TYPE_ARCHETYPE)),
            'menuCategories' => array_map(static fn (TranslationQueueItem $item): array => $item->toArray(), $this->withLabels($menuCategories, self::TYPE_MENU_CATEGORY)),
            'bannedCards' => array_values(array_map(
                static fn (TranslationQueueItem $item): array => $item->toArray(),
                array_filter($cards, static fn (TranslationQueueItem $item): bool => self::TYPE_BANNED_CARD === $item->contentType),
            )),
            'stapleCards' => array_values(array_map(
                static fn (TranslationQueueItem $item): array => $item->toArray(),
                array_filter($cards, static fn (TranslationQueueItem $item): bool => self::TYPE_STAPLE_CARD === $item->contentType),
            )),
        ];
    }

    /**
     * Cards with source copy but no translation row and no in-workflow
     * revision for one of the contributor's locales (F9.17).
     *
     * @param list<TranslationQueueItem> $existingItems
     *
     * @return list<TranslationQueueItem>
     */
    private function untranslatedCardItems(User $user, array $existingItems): array
    {
        $existingKeys = [];
        foreach ($existingItems as $item) {
            $existingKeys[$item->contentType.':'.$item->contentId.':'.$item->locale] = true;
        }

        $items = [];
        foreach ($user->getTranslationLocales() as $locale) {
            /** @var list<array{id: int}> $bannedRows */
            $bannedRows = $this->entityManager->createQueryBuilder()
                ->select('bannedCard.id AS id')
                ->from(BannedCard::class, 'bannedCard')
                ->where('bannedCard.explanation IS NOT NULL')
                ->andWhere("bannedCard.explanation != ''")
                ->andWhere('bannedCard.deletedAt IS NULL')
                ->andWhere(\sprintf(
                    'NOT EXISTS (SELECT 1 FROM %s existing WHERE existing.bannedCard = bannedCard AND existing.locale = :locale)',
                    BannedCardTranslation::class,
                ))
                ->setParameter('locale', $locale)
                ->getQuery()
                ->getArrayResult();
            foreach ($bannedRows as $row) {
                if (!isset($existingKeys[self::TYPE_BANNED_CARD.':'.$row['id'].':'.$locale])) {
                    $items[] = new TranslationQueueItem(self::TYPE_BANNED_CARD, $row['id'], '', $locale, null, false);
                }
            }

            /** @var list<array{id: int}> $stapleRows */
            $stapleRows = $this->entityManager->createQueryBuilder()
                ->select('stapleCard.id AS id')
                ->from(StapleCard::class, 'stapleCard')
                ->where('stapleCard.note IS NOT NULL')
                ->andWhere("stapleCard.note != ''")
                ->andWhere('stapleCard.deletedAt IS NULL')
                ->andWhere(\sprintf(
                    'NOT EXISTS (SELECT 1 FROM %s existing WHERE existing.stapleCard = stapleCard AND existing.locale = :locale)',
                    StapleCardTranslation::class,
                ))
                ->setParameter('locale', $locale)
                ->getQuery()
                ->getArrayResult();
            foreach ($stapleRows as $row) {
                if (!isset($existingKeys[self::TYPE_STAPLE_CARD.':'.$row['id'].':'.$locale])) {
                    $items[] = new TranslationQueueItem(self::TYPE_STAPLE_CARD, $row['id'], '', $locale, null, false);
                }
            }
        }

        return $items;
    }

    /**
     * Card labels are the untranslated card names, straight from the entity.
     *
     * @param list<TranslationQueueItem> $items
     *
     * @return list<TranslationQueueItem>
     */
    private function withCardLabels(array $items): array
    {
        if ([] === $items) {
            return [];
        }

        $labels = [];
        foreach ([self::TYPE_BANNED_CARD => BannedCard::class, self::TYPE_STAPLE_CARD => StapleCard::class] as $contentType => $entityClass) {
            $ids = array_values(array_unique(array_map(
                static fn (TranslationQueueItem $item): int => $item->contentId,
                array_values(array_filter($items, static fn (TranslationQueueItem $item): bool => $item->contentType === $contentType)),
            )));
            if ([] === $ids) {
                continue;
            }
            /** @var list<array{id: int, cardName: string}> $rows */
            $rows = $this->entityManager->createQueryBuilder()
                ->select('card.id AS id', 'card.cardName AS cardName')
                ->from($entityClass, 'card')
                ->where('card.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getArrayResult();
            foreach ($rows as $row) {
                $labels[$contentType.':'.$row['id']] = $row['cardName'];
            }
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = new TranslationQueueItem(
                $item->contentType,
                $item->contentId,
                $labels[$item->contentType.':'.$item->contentId] ?? \sprintf('#%d', $item->contentId),
                $item->locale,
                $item->state,
                $item->sourceOutdated,
            );
        }
        usort($result, static fn (TranslationQueueItem $a, TranslationQueueItem $b): int => [$a->label, $a->locale] <=> [$b->label, $b->locale]);

        return $result;
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
            $subjectId = $revision->getSubject()->getId();
            \assert(null !== $subjectId);
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
            \assert(null !== $subjectId);
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
        if ($row instanceof BannedCardTranslation) {
            return [$row->getBannedCard()->getId(), $row->getLocale()];
        }
        if ($row instanceof StapleCardTranslation) {
            return [$row->getStapleCard()->getId(), $row->getLocale()];
        }

        throw new \LogicException(\sprintf('Unknown live translation row "%s" (subject field "%s").', $row::class, $subjectField));
    }
}
