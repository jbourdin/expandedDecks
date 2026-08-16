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
        #[Autowire('%kernel.default_locale%')]
        private readonly string $sourceLocale,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        /** @var list<PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation|Deck> $candidates */
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
                ?? $this->latestSourceRevision($entityManager, $revision);
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

        $connection = $args->getObjectManager()->getConnection();
        foreach ($subjects as [$subjectClass, $subjectId]) {
            [$table, $foreignKeyColumn] = self::OUTDATED_TARGETS[$subjectClass];
            $connection->executeStatement(
                \sprintf('UPDATE %s SET source_outdated = 1 WHERE %s = :subjectId AND locale <> :sourceLocale', $table, $foreignKeyColumn),
                ['subjectId' => $subjectId, 'sourceLocale' => $this->sourceLocale],
            );
        }
    }

    /**
     * @phpstan-assert-if-true PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation|Deck $entity
     */
    private function isRelevantInsertion(object $entity): bool
    {
        if ($entity instanceof PageTranslation || $entity instanceof ArchetypeTranslation || $entity instanceof MenuCategoryTranslation || $entity instanceof DeckTranslation) {
            return true;
        }

        // A brand-new variant only enters the workflow once it has notes.
        if ($entity instanceof Deck) {
            return $entity->isArchetypeVariant() && null !== $entity->getNotes() && '' !== $entity->getNotes();
        }

        return false;
    }

    /**
     * @param list<string> $changedFields
     *
     * @phpstan-assert-if-true PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation|Deck $entity
     */
    private function isRelevantUpdate(object $entity, array $changedFields): bool
    {
        if ($entity instanceof Deck && !$entity->isArchetypeVariant()) {
            return false;
        }

        if (!$entity instanceof PageTranslation && !$entity instanceof ArchetypeTranslation && !$entity instanceof MenuCategoryTranslation && !$entity instanceof DeckTranslation && !$entity instanceof Deck) {
            return false;
        }

        return [] !== array_intersect($changedFields, self::translatableFields($entity::class));
    }

    private function createRevision(PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation|Deck $entity): TranslationRevisionInterface
    {
        $revision = match (true) {
            $entity instanceof PageTranslation => PageTranslationRevision::fromTranslation($entity),
            $entity instanceof ArchetypeTranslation => ArchetypeTranslationRevision::fromTranslation($entity),
            $entity instanceof MenuCategoryTranslation => MenuCategoryTranslationRevision::fromTranslation($entity),
            $entity instanceof DeckTranslation => DeckTranslationRevision::fromTranslation($entity),
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

    private function latestSourceRevision(EntityManagerInterface $entityManager, TranslationRevisionInterface $revision): ?TranslationRevisionInterface
    {
        $found = $entityManager->getRepository($revision::class)->findOneBy(
            [$revision::subjectFieldName() => $revision->getSubject(), 'locale' => $this->sourceLocale],
            ['id' => 'DESC'],
        );

        return $found instanceof TranslationRevisionInterface ? $found : null;
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
        }
    }

    private function localeOf(PageTranslation|ArchetypeTranslation|MenuCategoryTranslation|DeckTranslation|Deck $entity): string
    {
        // Deck carries the canonical source-locale notes; every other entity
        // is a translation row with an explicit locale.
        return $entity instanceof Deck ? $this->sourceLocale : $entity->getLocale();
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
