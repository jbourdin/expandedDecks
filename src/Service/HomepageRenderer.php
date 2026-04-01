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

namespace App\Service;

use App\DTO\ResolvedBlock;
use App\Entity\HomepageLayout;
use App\Enum\HomepageBlockType;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\MenuCategoryRepository;
use App\Repository\PageRepository;

/**
 * Resolves a HomepageLayout into an ordered list of ResolvedBlock DTOs,
 * filtering by scheduling, resolving dynamic data, and merging translations.
 *
 * @see docs/features.md F10.4 — Homepage rendering service and Twig block partials
 */
class HomepageRenderer
{
    public function __construct(
        private readonly MarkdownRenderer $markdownRenderer,
        private readonly PageRepository $pageRepository,
        private readonly MenuCategoryRepository $menuCategoryRepository,
        private readonly EventRepository $eventRepository,
        private readonly DeckRepository $deckRepository,
    ) {
    }

    /**
     * Resolve all visible blocks for the given locale.
     *
     * @return list<ResolvedBlock>
     */
    public function resolve(HomepageLayout $layout, string $locale): array
    {
        $now = new \DateTimeImmutable();
        $translation = $layout->getTranslation($locale);
        $blockTranslations = $translation?->getBlockTranslations() ?? [];
        $resolvedBlocks = [];

        foreach ($layout->getBlocks() as $index => $block) {
            if (!$this->isBlockVisible($block, $now)) {
                continue;
            }

            $typeValue = $block['type'] ?? '';
            $type = HomepageBlockType::tryFrom(\is_string($typeValue) ? $typeValue : '');
            if (null === $type) {
                continue;
            }

            $translated = $blockTranslations[$index] ?? $blockTranslations[(string) $index] ?? [];
            $resolvedData = $this->resolveBlockData($type, $block, $locale);

            $columnWidth = $block['columnWidth'] ?? null;
            $cssClasses = $block['cssClasses'] ?? null;

            $resolvedBlocks[] = new ResolvedBlock(
                type: $type,
                columnWidth: \is_int($columnWidth) ? $columnWidth : null,
                cssClasses: \is_string($cssClasses) ? $cssClasses : null,
                settings: $block,
                translations: $translated,
                resolvedData: $resolvedData,
            );
        }

        return $resolvedBlocks;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function isBlockVisible(array $block, \DateTimeImmutable $now): bool
    {
        $startAt = $block['startAt'] ?? null;
        if (\is_string($startAt) && '' !== $startAt) {
            if ($now < new \DateTimeImmutable($startAt)) {
                return false;
            }
        }

        $endAt = $block['endAt'] ?? null;
        if (\is_string($endAt) && '' !== $endAt) {
            if ($now > new \DateTimeImmutable($endAt)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve dynamic runtime data for a block.
     *
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function resolveBlockData(HomepageBlockType $type, array $block, string $locale): array
    {
        return match ($type) {
            HomepageBlockType::RichText => $this->resolveRichText($block, $locale),
            HomepageBlockType::LatestPages => $this->resolveLatestPages($block, $locale),
            HomepageBlockType::FeaturedEvent => $this->resolveFeaturedEvent(),
            HomepageBlockType::FeaturedDeck => $this->resolveFeaturedDeck(),
            HomepageBlockType::Carousel => $this->resolveCarousel($block),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function resolveRichText(array $block, string $locale): array
    {
        $pageSlug = $block['pageSlug'] ?? null;
        if (!\is_string($pageSlug) || '' === $pageSlug) {
            return [];
        }

        $page = $this->pageRepository->findBySlug($pageSlug);
        if (null === $page || !$page->isPublished()) {
            return [];
        }

        $translation = $page->getTranslation($locale);
        if (null === $translation) {
            return [];
        }

        return [
            'html' => $this->markdownRenderer->render($translation->getContent()),
        ];
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function resolveLatestPages(array $block, string $locale): array
    {
        $categorySlug = $block['categorySlug'] ?? null;
        $limitValue = $block['limit'] ?? 5;
        $limit = \is_int($limitValue) ? $limitValue : 5;

        if (!\is_string($categorySlug) || '' === $categorySlug) {
            return ['pages' => [], 'totalCount' => 0, 'category' => null, 'locale' => $locale];
        }

        $category = null;
        foreach ($this->menuCategoryRepository->findAllOrdered() as $menuCategory) {
            if (strtolower($menuCategory->getName('en')) === strtolower($categorySlug)) {
                $category = $menuCategory;
                break;
            }
        }

        if (null === $category) {
            return ['pages' => [], 'totalCount' => 0, 'category' => null, 'locale' => $locale];
        }

        return [
            'pages' => $this->pageRepository->findPublishedByCategory($category, $limit),
            'totalCount' => $this->pageRepository->countPublishedByCategory($category),
            'category' => $category,
            'locale' => $locale,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFeaturedEvent(): array
    {
        return [
            'count' => $this->eventRepository->countUpcoming(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFeaturedDeck(): array
    {
        return [
            'count' => $this->deckRepository->countPublicDecks(),
        ];
    }

    /**
     * Filter carousel items by their own startAt/endAt scheduling.
     *
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function resolveCarousel(array $block): array
    {
        $items = $block['items'] ?? [];
        if (!\is_array($items)) {
            return ['items' => []];
        }

        $now = new \DateTimeImmutable();
        $visibleItems = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            /** @var array<string, mixed> $item */
            if ($this->isBlockVisible($item, $now)) {
                $visibleItems[] = $item;
            }
        }

        return ['items' => $visibleItems];
    }
}
