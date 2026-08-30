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

namespace App\EventListener;

use App\Attribute\Translatable;
use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Entity\ArchetypeTranslationRevision;
use App\Entity\BannedCard;
use App\Entity\BannedCardTranslation;
use App\Entity\BannedCardTranslationRevision;
use App\Entity\Deck;
use App\Entity\DeckTranslation;
use App\Entity\DeckTranslationRevision;
use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use App\Entity\MenuCategoryTranslationRevision;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\PageTranslationRevision;
use App\Entity\StapleCard;
use App\Entity\StapleCardTranslation;
use App\Entity\StapleCardTranslationRevision;
use App\Entity\TranslationRevisionInterface;
use App\Entity\User;
use App\Service\Translation\LatestSourceRevisionProvider;
use App\Service\Translation\TranslationNotificationService;
use App\Service\Translation\TranslationRevisionSuppression;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Snapshots every live write to translated content into the revision tables.
 *
 * Invariant: every live write leaves a validated revision behind, whichever
 * door it came through — a CMS-editor save, a source-locale edit, or (later,
 * F9.9) a translation approval. Revisions are created inside the same flush
 * (onFlush + computeChangeSet) so history is transactionally consistent with
 * the live write.
 *
 * A change is only content activity when it touches a `#[Translatable]`
 * field: an `ogImage`-only save produces no revision and flags nothing —
 * same philosophy as {@see \App\Entity\TimestampExemptChangeTrait}.
 *
 * When a source-locale revision is created, sibling non-source live rows are
 * flagged `sourceOutdated` via a bulk SQL UPDATE in postFlush (same pattern
 * as {@see ArchetypeFreshnessListener} to avoid re-entering the UnitOfWork).
 *
 * @see docs/features.md F9.7 — Translation foundation
 * @see docs/technicalities/translation_workflow.md
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class TranslationRevisionListener
{
    /**
     * Live-table coordinates for the sourceOutdated bulk update, per subject
     * class: [table, subject FK column].
     *
     * @var array<class-string, array{string, string}>
     */
    private const array OUTDATED_TARGETS = [
        Page::class => ['page_translation', 'page_id'],
        Archetype::class => ['archetype_translation', 'archetype_id'],
        MenuCategory::class => ['menu_category_translation', 'menu_category_id'],
        Deck::class => ['deck_translation', 'deck_id'],
        BannedCard::class => ['banned_card_translation', 'banned_card_id'],
        StapleCard::class => ['staple_card_translation', 'staple_card_id'],
    ];

    /** @var array<class-string, list<string>> */
    private static array $translatableFieldsCache = [];

    /**
     * Subjects whose source content changed this flush, keyed for dedup:
     * "<class>:<id>" => [class, id].
     *
     * @var array<string, array{class-string, int}>
     */
    private array $pendingOutdatedSubjects = [];

    public function __construct(
        private readonly Security $security,
        private readonly TranslationRevisionSuppression $suppression,
        private readonly LatestSourceRevisionProvider $latestSourceRevisionProvider,
        private readonly TranslationNotificationService $notificationService,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $sourceLocale,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        // An approval write (F9.9) copies an already-validated revision into
        // the live row — snapshotting it again would duplicate history.
        if ($this->suppression->isSuppressed()) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        /** @var list<PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation|Deck|BannedCard|StapleCard|BannedCardTranslation|StapleCardTranslation> $candidates */
        $candidates = [];
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($this->isRelevantInsertion($entity)) {
                $candidates[] = $entity;
            }
        }
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($this->isRelevantUpdate($entity, array_keys($unitOfWork->getEntityChangeSet($entity)))) {
                $candidates[] = $entity;
            }
        }

        if ([] === $candidates) {
            return;
        }

        // Source-locale revisions are created first so a translation changed
        // in the same flush can reference its in-flush source revision.
        /** @var array<string, TranslationRevisionInterface> $sourceRevisionsThisFlush */
        $sourceRevisionsThisFlush = [];

        foreach ($candidates as $entity) {
            if ($this->localeOf($entity) !== $this->sourceLocale) {
                continue;
            }

            $revision = $this->createRevision($entity);
            $this->registerRevision($entityManager, $revision);
            $sourceRevisionsThisFlush[$this->subjectKey($revision)] = $revision;
            $this->collectOutdatedSubject($revision);
        }

        foreach ($candidates as $entity) {
            if ($this->localeOf($entity) === $this->sourceLocale) {
                continue;
            }

            $revision = $this->createRevision($entity);
            $sourceRevision = $sourceRevisionsThisFlush[$this->subjectKey($revision)]
                ?? $this->latestSourceRevisionProvider->latestFor($revision);
            $this->linkSourceRevision($revision, $sourceRevision);
            $this->registerRevision($entityManager, $revision);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pendingOutdatedSubjects) {
            return;
        }

        $subjects = $this->pendingOutdatedSubjects;
        $this->pendingOutdatedSubjects = [];

        $entityManager = $args->getObjectManager();
        $connection = $entityManager->getConnection();
        /** @var list<array{class-string, int, int, string}> $newlyOutdated */
        $newlyOutdated = [];
        foreach ($subjects as [$subjectClass, $subjectId]) {
            [$table, $foreignKeyColumn] = self::OUTDATED_TARGETS[$subjectClass];

            // Rows flipping 0 -> 1 right now: their credited translator gets
            // one notification per source change, never repeats while the
            // translation stays outdated (F9.15). MenuCategory rows carry no
            // translator credit, so there is nobody to notify.
            if ('menu_category_translation' !== $table) {
                /** @var list<array{locale: string, translator_id: int|string}> $rows */
                $rows = $connection->fetchAllAssociative(
                    \sprintf('SELECT locale, translator_id FROM %s WHERE %s = :subjectId AND locale <> :sourceLocale AND source_outdated = 0 AND translator_id IS NOT NULL', $table, $foreignKeyColumn),
                    ['subjectId' => $subjectId, 'sourceLocale' => $this->sourceLocale],
                );
                foreach ($rows as $row) {
                    $newlyOutdated[] = [$subjectClass, $subjectId, (int) $row['translator_id'], $row['locale']];
                }
            }

            $connection->executeStatement(
                \sprintf('UPDATE %s SET source_outdated = 1 WHERE %s = :subjectId AND locale <> :sourceLocale', $table, $foreignKeyColumn),
                ['subjectId' => $subjectId, 'sourceLocale' => $this->sourceLocale],
            );
        }

        foreach ($newlyOutdated as [$subjectClass, $subjectId, $translatorId, $locale]) {
            $content = $entityManager->find($subjectClass, $subjectId);
            $translatorUser = $entityManager->find(User::class, $translatorId);
            if ($translatorUser instanceof User
                && ($content instanceof Page || $content instanceof Archetype || $content instanceof MenuCategory || $content instanceof Deck || $content instanceof BannedCard || $content instanceof StapleCard)) {
                $this->notificationService->notifySourceOutdated($translatorUser, $content, $locale);
            }
        }
    }

    /**
     * @phpstan-assert-if-true PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation|Deck|BannedCard|StapleCard|BannedCardTranslation|StapleCardTranslation $entity
     */
    private function isRelevantInsertion(object $entity): bool
    {
        if ($entity instanceof PageTranslation || $entity instanceof ArchetypeTranslation || $entity instanceof MenuCategoryTranslation || $entity instanceof DeckTranslation || $entity instanceof BannedCardTranslation || $entity instanceof StapleCardTranslation) {
            return true;
        }

        // A brand-new variant only enters the workflow once it has notes.
        if ($entity instanceof Deck) {
            return $entity->isArchetypeVariant() && null !== $entity->getNotes() && '' !== $entity->getNotes();
        }

        // Cards enter the workflow once they carry source copy (F9.17).
        if ($entity instanceof BannedCard) {
            return null !== $entity->getExplanation() && '' !== $entity->getExplanation();
        }
        if ($entity instanceof StapleCard) {
            return null !== $entity->getNote() && '' !== $entity->getNote();
        }

        return false;
    }

    /**
     * @param list<string> $changedFields
     *
     * @phpstan-assert-if-true PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation|Deck|BannedCard|StapleCard|BannedCardTranslation|StapleCardTranslation $entity
     */
    private function isRelevantUpdate(object $entity, array $changedFields): bool
    {
        if ($entity instanceof Deck && !$entity->isArchetypeVariant()) {
            return false;
        }

        if (!$entity instanceof PageTranslation && !$entity instanceof ArchetypeTranslation && !$entity instanceof MenuCategoryTranslation && !$entity instanceof DeckTranslation && !$entity instanceof Deck && !$entity instanceof BannedCard && !$entity instanceof StapleCard && !$entity instanceof BannedCardTranslation && !$entity instanceof StapleCardTranslation) {
            return false;
        }

        return [] !== array_intersect($changedFields, self::translatableFields($entity::class));
    }

    private function createRevision(PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation|Deck|BannedCard|StapleCard|BannedCardTranslation|StapleCardTranslation $entity): TranslationRevisionInterface
    {
        $revision = match (true) {
            $entity instanceof PageTranslation => PageTranslationRevision::fromTranslation($entity),
            $entity instanceof ArchetypeTranslation => ArchetypeTranslationRevision::fromTranslation($entity),
            $entity instanceof MenuCategoryTranslation => MenuCategoryTranslationRevision::fromTranslation($entity),
            $entity instanceof DeckTranslation => DeckTranslationRevision::fromTranslation($entity),
            $entity instanceof BannedCardTranslation => BannedCardTranslationRevision::fromTranslation($entity),
            $entity instanceof StapleCardTranslation => StapleCardTranslationRevision::fromTranslation($entity),
            $entity instanceof BannedCard => BannedCardTranslationRevision::fromBannedCardExplanation($entity, $this->sourceLocale),
            $entity instanceof StapleCard => StapleCardTranslationRevision::fromStapleCardNote($entity, $this->sourceLocale),
            default => DeckTranslationRevision::fromDeckNotes($entity, $this->sourceLocale),
        };

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $revision->setAuthor($user);
        }

        return $revision;
    }

    private function registerRevision(EntityManagerInterface $entityManager, TranslationRevisionInterface $revision): void
    {
        $entityManager->persist($revision);
        $entityManager->getUnitOfWork()->computeChangeSet(
            $entityManager->getClassMetadata($revision::class),
            $revision,
        );
    }

    private function linkSourceRevision(TranslationRevisionInterface $revision, ?TranslationRevisionInterface $sourceRevision): void
    {
        if (null === $sourceRevision) {
            return;
        }

        // Both sides always share the concrete class: sourceRevision lookups
        // are keyed on the revision's own class and subject.
        if ($revision instanceof PageTranslationRevision && $sourceRevision instanceof PageTranslationRevision) {
            $revision->setSourceRevision($sourceRevision);
        } elseif ($revision instanceof ArchetypeTranslationRevision && $sourceRevision instanceof ArchetypeTranslationRevision) {
            $revision->setSourceRevision($sourceRevision);
        } elseif ($revision instanceof MenuCategoryTranslationRevision && $sourceRevision instanceof MenuCategoryTranslationRevision) {
            $revision->setSourceRevision($sourceRevision);
        } elseif ($revision instanceof DeckTranslationRevision && $sourceRevision instanceof DeckTranslationRevision) {
            $revision->setSourceRevision($sourceRevision);
        } elseif ($revision instanceof BannedCardTranslationRevision && $sourceRevision instanceof BannedCardTranslationRevision) {
            $revision->setSourceRevision($sourceRevision);
        } elseif ($revision instanceof StapleCardTranslationRevision && $sourceRevision instanceof StapleCardTranslationRevision) {
            $revision->setSourceRevision($sourceRevision);
        }
    }

    private function localeOf(PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation|Deck|BannedCard|StapleCard|BannedCardTranslation|StapleCardTranslation $entity): string
    {
        // Deck, BannedCard, and StapleCard carry the canonical source-locale
        // copy on the entity itself; every other candidate is a translation
        // row with an explicit locale.
        if ($entity instanceof Deck || $entity instanceof BannedCard || $entity instanceof StapleCard) {
            return $this->sourceLocale;
        }

        return $entity->getLocale();
    }

    private function subjectKey(TranslationRevisionInterface $revision): string
    {
        return \sprintf('%s:%s', $revision::class, spl_object_id($revision->getSubject()));
    }

    private function collectOutdatedSubject(TranslationRevisionInterface $revision): void
    {
        $subject = $revision->getSubject();
        $subjectId = $subject->getId();

        // Freshly inserted subjects have no siblings to flag yet.
        if (null === $subjectId) {
            return;
        }

        $this->pendingOutdatedSubjects[\sprintf('%s:%d', $subject::class, $subjectId)] = [$subject::class, $subjectId];
    }

    /**
     * @param class-string $entityClass
     *
     * @return list<string>
     */
    private static function translatableFields(string $entityClass): array
    {
        if (isset(self::$translatableFieldsCache[$entityClass])) {
            return self::$translatableFieldsCache[$entityClass];
        }

        $fields = [];
        foreach ((new \ReflectionClass($entityClass))->getProperties() as $property) {
            if ([] !== $property->getAttributes(Translatable::class)) {
                $fields[] = $property->getName();
            }
        }

        return self::$translatableFieldsCache[$entityClass] = $fields;
    }
}
