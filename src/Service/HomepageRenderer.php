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
use App\Service\Channel\ChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ChannelContext $channelContext,
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
            $resolvedData = $this->resolveBlockData($type, $block, $locale, $translated);

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
     * @param array<string, mixed> $translated
     *
     * @return array<string, mixed>
     */
    private function resolveBlockData(HomepageBlockType $type, array $block, string $locale, array $translated = []): array
    {
        return match ($type) {
            HomepageBlockType::RichText => $this->resolveRichText($block, $locale, $translated),
            HomepageBlockType::PageEmbed => $this->resolvePageEmbed($block, $locale),
            HomepageBlockType::LatestPages => $this->resolveLatestPages($block, $locale),
            HomepageBlockType::FeaturedEvent => $this->resolveFeaturedEvent($block, $translated),
            HomepageBlockType::FeaturedDeck => $this->resolveFeaturedDeck($block, $translated),
            HomepageBlockType::Carousel => $this->resolveCarousel($block),
            default => [],
        };
    }

    /**
     * Resolve inline rich text block — renders translatable Markdown content to HTML.
     * The Markdown content comes from HomepageLayoutTranslation, not from a CMS page.
     *
     * @param array<string, mixed> $block
     * @param array<string, mixed> $translated
     *
     * @return array<string, mixed>
     */
    private function resolveRichText(array $block, string $locale, array $translated = []): array
    {
        $content = $translated['content'] ?? null;
        if (!\is_string($content) || '' === $content) {
            return [];
        }

        return [
            'html' => $this->markdownRenderer->render($content),
        ];
    }

    /**
     * Resolve page embed block — fetches a CMS page by slug and renders its Markdown content.
     *
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function resolvePageEmbed(array $block, string $locale): array
    {
        $pageSlug = $block['pageSlug'] ?? null;
        if (!\is_string($pageSlug) || '' === $pageSlug) {
            return [];
        }

        $page = $this->pageRepository->findBySlug($pageSlug);
        if (null === $page || !$page->isPublished()) {
            return [];
        }

        $translation = $page->getDisplayTranslation($locale);
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
        $limitValue = $block['limit'] ?? 5;
        $limit = \is_int($limitValue) ? $limitValue : 5;

        $channel = $this->channelContext->getChannel();
        $category = null;

        // Prefer the new `categoryId` field; fall back to legacy `categorySlug` (a category
        // name match, kept for blocks that haven't been re-saved since #536 landed).
        $categoryIdValue = $block['categoryId'] ?? null;
        if (\is_int($categoryIdValue) || (\is_string($categoryIdValue) && ctype_digit($categoryIdValue))) {
            $categoryId = (int) $categoryIdValue;
            foreach ($this->menuCategoryRepository->findAllOrdered($channel) as $menuCategory) {
                if ($menuCategory->getId() === $categoryId) {
                    $category = $menuCategory;
                    break;
                }
            }
        }

        if (null === $category) {
            $categorySlug = $block['categorySlug'] ?? null;
            if (\is_string($categorySlug) && '' !== $categorySlug) {
                foreach ($this->menuCategoryRepository->findAllOrdered($channel) as $menuCategory) {
                    if (strtolower($menuCategory->getName('en')) === strtolower($categorySlug)) {
                        $category = $menuCategory;
                        break;
                    }
                }
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
     * Resolve a featured event block — fetches the event by ID.
     *
     * @param array<string, mixed> $block
     * @param array<string, mixed> $translated
     *
     * @return array<string, mixed>
     */
    private function resolveFeaturedEvent(array $block, array $translated): array
    {
        $eventId = $block['eventId'] ?? null;
        if (!\is_int($eventId)) {
            return [];
        }

        $event = $this->eventRepository->find($eventId);
        if (null === $event) {
            return [];
        }

        $descriptionHtml = null;
        $description = $translated['description'] ?? null;
        if (\is_string($description) && '' !== $description) {
            $descriptionHtml = $this->markdownRenderer->render($description);
        }

        return [
            'event' => $event,
            'url' => $this->urlGenerator->generate('app_event_show', ['id' => $event->getId()]),
            'descriptionHtml' => $descriptionHtml,
        ];
    }

    /**
     * Resolve a featured deck block — fetches the deck by shortTag.
     *
     * @param array<string, mixed> $block
     * @param array<string, mixed> $translated
     *
     * @return array<string, mixed>
     */
    private function resolveFeaturedDeck(array $block, array $translated): array
    {
        $shortTag = $block['shortTag'] ?? null;
        if (!\is_string($shortTag) || '' === $shortTag) {
            return [];
        }

        $deck = $this->deckRepository->findOneBy(['shortTag' => $shortTag]);
        if (null === $deck) {
            return [];
        }

        $mosaicUrl = $deck->getCurrentVersion()?->getMosaicImageUrl();

        $descriptionHtml = null;
        $description = $translated['description'] ?? null;
        if (\is_string($description) && '' !== $description) {
            $descriptionHtml = $this->markdownRenderer->render($description);
        }

        return [
            'deck' => $deck,
            'url' => $this->urlGenerator->generate('app_deck_show', ['short_tag' => $deck->getShortTag()]),
            'mosaicUrl' => $mosaicUrl,
            'descriptionHtml' => $descriptionHtml,
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
