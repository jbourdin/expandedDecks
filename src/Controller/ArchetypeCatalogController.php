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

namespace App\Controller;

use App\Entity\Archetype;
use App\Repository\ArchetypeRepository;
use App\Repository\DeckRepository;
use App\Service\MarkdownExcerptGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see docs/features.md F2.16 — Archetype catalog
 */
class ArchetypeCatalogController extends AbstractController
{
    private const FEED_ITEMS = 20;

    /**
     * @see docs/features.md F2.16 — Archetype catalog
     * @see docs/features.md F7.11 — Draft state with preview
     */
    #[Route('/{_locale}/archetypes', name: 'app_archetype_list', methods: ['GET'], requirements: ['_locale' => 'en|fr'], priority: 10)]
    public function list(Request $request, ArchetypeRepository $archetypeRepository): Response
    {
        $showDrafts = $request->query->getBoolean('drafts') && $this->isGranted('ROLE_ARCHETYPE_EDITOR');

        $tagsMode = $request->query->getString('tagsMode', 'and');
        if (!\in_array($tagsMode, ['and', 'or'], true)) {
            $tagsMode = 'and';
        }

        if ($showDrafts) {
            $results = $archetypeRepository->findUnpublishedWithDeckCounts();
            $allTags = [];
            $tags = [];
            $sort = 'name';
        } else {
            /** @var list<string> $tags */
            $tags = $request->query->all('tags');
            $tags = array_values(array_filter($tags, static fn (string $tag): bool => '' !== $tag));
            $sort = $request->query->getString('sort', 'position');

            if (!\in_array($sort, ['name', 'decks', 'position', 'updatedAt'], true)) {
                $sort = 'position';
            }

            $results = $archetypeRepository->findPublishedWithDeckCounts($tags, $sort, $tagsMode);
            $allTags = $archetypeRepository->findAllPublishedPlaystyleTags();
        }

        return $this->render('archetype/list.html.twig', [
            'results' => $results,
            'allTags' => $allTags,
            'currentTags' => $tags,
            'currentSort' => $sort,
            'currentTagsMode' => $tagsMode,
            'showDrafts' => $showDrafts,
        ]);
    }

    /**
     * RSS 2.0 feed of the most recently published archetype variants.
     *
     * Each item links to the archetype page anchored on the variant's short
     * tag, so subscribers land with the right variant selected.
     *
     * @see docs/features.md F21.2 — RSS feed of archetype variants
     */
    #[Route('/{_locale}/archetypes/feed.xml', name: 'app_archetype_feed', methods: ['GET'], requirements: ['_locale' => 'en|fr'], priority: 20)]
    public function feed(
        Request $request,
        DeckRepository $deckRepository,
        MarkdownExcerptGenerator $markdownExcerptGenerator,
    ): Response {
        $locale = $request->getLocale();
        $variants = $deckRepository->findLatestPublishedVariants(self::FEED_ITEMS);

        $items = [];
        foreach ($variants as $variant) {
            $archetype = $variant->getArchetype();
            if (!$archetype instanceof Archetype) {
                continue;
            }

            $archetypeUrl = $this->generateUrl(
                'app_archetype_show',
                ['slug' => $archetype->getSlug(), '_locale' => $locale],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $publishedAt = $variant->getCreatedAt();
            $archetypePublishedAt = $archetype->getFirstPublishedAt();
            if ($archetypePublishedAt instanceof \DateTimeImmutable && $archetypePublishedAt > $publishedAt) {
                $publishedAt = $archetypePublishedAt;
            }

            $items[] = [
                'title' => $archetype->getLocalizedName($locale).' — '.$variant->getName(),
                'url' => $archetypeUrl.'#'.$variant->getShortTag(),
                'publishedAt' => $publishedAt,
                'description' => $markdownExcerptGenerator->excerpt($variant->getNotes() ?? ''),
                // Only an image explicitly set on the variant — no archetype or
                // mosaic fallback: the 60-card mosaic is too big to be a relevant
                // feed thumbnail.
                'image' => $variant->getOgImage(),
            ];
        }

        $response = $this->render('archetype/feed.xml.twig', [
            'locale' => $locale,
            'items' => $items,
        ]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }
}
