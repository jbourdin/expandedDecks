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
use App\Entity\BannedCard;
use App\Entity\BannedCardTranslation;
use App\Entity\Deck;
use App\Entity\DeckTranslation;
use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslation;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\StapleCard;
use App\Entity\StapleCardTranslation;
use App\Entity\TranslationRevisionInterface;
use App\Service\MarkdownRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Assembles the props of the contextual translation view (F9.10) — and, for
 * archetypes, the combined context including one section per variant (F9.13).
 *
 * Every section carries: field metadata (kind + length budget), the current
 * source values (rendered HTML for markdown fields, raw text for the diff),
 * the pending revision's values when one exists, and the staleness pair —
 * the pinned source revision's values vs the latest source revision's, which
 * the client diffs word-by-word (US-T5).
 *
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 * @see docs/features.md F9.13 — Archetype + variants combined translation view
 */
final readonly class TranslationViewDataBuilder
{
    /**
     * Field metadata per content type: kind drives the input widget,
     * maxLength the counter (160 = SEO snippet budget, F19.7).
     *
     * @var array<string, array<string, array{kind: string, maxLength: ?int}>>
     */
    private const array FIELD_METADATA = [
        'page' => [
            'title' => ['kind' => 'text', 'maxLength' => 200],
            'content' => ['kind' => 'markdown', 'maxLength' => null],
            'ogDescription' => ['kind' => 'textarea', 'maxLength' => 160],
        ],
        'archetype' => [
            'name' => ['kind' => 'text', 'maxLength' => 100],
            'description' => ['kind' => 'markdown', 'maxLength' => null],
            'metaDescription' => ['kind' => 'textarea', 'maxLength' => 160],
            'ogDescription' => ['kind' => 'textarea', 'maxLength' => 160],
        ],
        'menu_category' => [
            'name' => ['kind' => 'text', 'maxLength' => 100],
        ],
        'deck' => [
            'notes' => ['kind' => 'markdown', 'maxLength' => null],
        ],
        'banned_card' => [
            'explanation' => ['kind' => 'markdown', 'maxLength' => null],
        ],
        'staple_card' => [
            'note' => ['kind' => 'markdown', 'maxLength' => null],
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslationDraftProvider $draftProvider,
        private LatestSourceRevisionProvider $latestSourceRevisionProvider,
        private MarkdownRenderer $markdownRenderer,
        #[Autowire('%kernel.default_locale%')]
        private string $sourceLocale,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSection(Page|Archetype|MenuCategory|Deck|BannedCard|StapleCard $content, string $locale): array
    {
        $contentType = self::contentTypeOf($content);
        $metadata = self::FIELD_METADATA[$contentType];
        $pending = $this->draftProvider->findPending($content, $locale);

        $sourceValues = $this->sourceValues($content);
        $draftValues = $pending instanceof TranslationRevisionInterface
            ? $this->revisionValues($pending, array_keys($metadata))
            : $this->liveTargetValues($content, $locale, array_keys($metadata));

        [$pinnedValues, $latestValues, $latestSourceRevisionId, $pinnedSourceRevisionId] = $this->stalenessPair($content, $locale, $pending, array_keys($metadata));

        $fields = [];
        foreach ($metadata as $field => $fieldMetadata) {
            $sourceRaw = $sourceValues[$field] ?? null;
            $fields[] = [
                'name' => $field,
                'kind' => $fieldMetadata['kind'],
                'maxLength' => $fieldMetadata['maxLength'],
                'source' => $sourceRaw,
                'sourceHtml' => 'markdown' === $fieldMetadata['kind'] && \is_string($sourceRaw) && '' !== $sourceRaw
                    ? $this->markdownRenderer->render($sourceRaw)
                    : null,
                'target' => $draftValues[$field] ?? null,
                'pinnedSource' => $pinnedValues[$field] ?? null,
                'latestSource' => $latestValues[$field] ?? null,
            ];
        }

        return [
            'contentType' => $contentType,
            'contentId' => $content->getId(),
            'locale' => $locale,
            'state' => $pending?->getState()->value,
            'revisionId' => $pending?->getId(),
            'reviewComment' => $pending?->getReviewComment(),
            // A section is only stale when work already exists on an older
            // source: a fresh translation has nothing pinned yet — its draft
            // gets pinned to the latest source revision on creation (US-T4).
            'sourceStale' => $pending instanceof TranslationRevisionInterface
                && null !== $latestSourceRevisionId
                && $latestSourceRevisionId !== $pinnedSourceRevisionId,
            'fields' => $fields,
        ];
    }

    /**
     * Archetype context: the archetype's own section plus one per variant in
     * board order (F9.13).
     *
     * @return list<array<string, mixed>>
     */
    public function buildArchetypeContext(Archetype $archetype, string $locale): array
    {
        $sections = [$this->buildSection($archetype, $locale)];

        /** @var list<Deck> $variants */
        $variants = $this->entityManager->createQueryBuilder()
            ->select('deck')
            ->from(Deck::class, 'deck')
            ->where('deck.archetype = :archetype')
            ->andWhere('deck.owner IS NULL')
            ->orderBy('deck.position', 'ASC')
            ->addOrderBy('deck.id', 'ASC')
            ->setParameter('archetype', $archetype)
            ->getQuery()
            ->getResult();

        foreach ($variants as $variant) {
            $section = $this->buildSection($variant, $locale);
            $section['variantName'] = $variant->getName();
            $sections[] = $section;
        }

        return $sections;
    }

    private static function contentTypeOf(Page|Archetype|MenuCategory|Deck|BannedCard|StapleCard $content): string
    {
        return match (true) {
            $content instanceof Page => 'page',
            $content instanceof Archetype => 'archetype',
            $content instanceof MenuCategory => 'menu_category',
            $content instanceof Deck => 'deck',
            $content instanceof BannedCard => 'banned_card',
            $content instanceof StapleCard => 'staple_card',
        };
    }

    /**
     * Current source-locale values, straight from the live source row (for a
     * deck, from `Deck.notes`).
     *
     * @return array<string, ?string>
     */
    private function sourceValues(Page|Archetype|MenuCategory|Deck|BannedCard|StapleCard $content): array
    {
        if ($content instanceof Deck) {
            return ['notes' => $content->getNotes()];
        }
        if ($content instanceof BannedCard) {
            return ['explanation' => $content->getExplanation()];
        }
        if ($content instanceof StapleCard) {
            return ['note' => $content->getNote()];
        }

        return $this->liveTargetValues($content, $this->sourceLocale, array_keys(self::FIELD_METADATA[self::contentTypeOf($content)]));
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, ?string>
     */
    private function liveTargetValues(Page|Archetype|MenuCategory|Deck|BannedCard|StapleCard $content, string $locale, array $fields): array
    {
        $live = match (true) {
            $content instanceof Page => $this->entityManager->getRepository(PageTranslation::class)->findOneBy(['page' => $content, 'locale' => $locale]),
            $content instanceof Archetype => $this->entityManager->getRepository(ArchetypeTranslation::class)->findOneBy(['archetype' => $content, 'locale' => $locale]),
            $content instanceof MenuCategory => $this->entityManager->getRepository(MenuCategoryTranslation::class)->findOneBy(['menuCategory' => $content, 'locale' => $locale]),
            $content instanceof Deck => $this->entityManager->getRepository(DeckTranslation::class)->findOneBy(['deck' => $content, 'locale' => $locale]),
            $content instanceof BannedCard => $this->entityManager->getRepository(BannedCardTranslation::class)->findOneBy(['bannedCard' => $content, 'locale' => $locale]),
            $content instanceof StapleCard => $this->entityManager->getRepository(StapleCardTranslation::class)->findOneBy(['stapleCard' => $content, 'locale' => $locale]),
        };

        if (null === $live) {
            return [];
        }

        return $this->objectValues($live, $fields);
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, ?string>
     */
    private function revisionValues(TranslationRevisionInterface $revision, array $fields): array
    {
        return $this->objectValues($revision, $fields);
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, ?string>
     */
    private function objectValues(object $entity, array $fields): array
    {
        $reflection = new \ReflectionClass($entity);
        $values = [];
        foreach ($fields as $field) {
            if (!$reflection->hasProperty($field)) {
                continue;
            }
            $value = $reflection->getProperty($field)->getValue($entity);
            $values[$field] = \is_string($value) ? $value : null;
        }

        return $values;
    }

    /**
     * Old-vs-new source values for the staleness diff: the revision the draft
     * is pinned to vs the latest source revision.
     *
     * @param list<string> $fields
     *
     * @return array{array<string, ?string>, array<string, ?string>, ?int, ?int}
     */
    private function stalenessPair(Page|Archetype|MenuCategory|Deck|BannedCard|StapleCard $content, string $locale, ?TranslationRevisionInterface $pending, array $fields): array
    {
        // The latest-source lookup needs a revision of the right class as a
        // probe; the pending one serves when present, a transient prefilled
        // draft otherwise (never persisted — probe only).
        $probe = $pending ?? $this->draftProvider->probeFor($content, $locale);
        $latest = $this->latestSourceRevisionProvider->latestFor($probe);
        $pinned = $pending?->getSourceRevision();

        return [
            $pinned instanceof TranslationRevisionInterface ? $this->revisionValues($pinned, $fields) : [],
            $latest instanceof TranslationRevisionInterface ? $this->revisionValues($latest, $fields) : [],
            $latest?->getId(),
            $pinned?->getId(),
        ];
    }
}
