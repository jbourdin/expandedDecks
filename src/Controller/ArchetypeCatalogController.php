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

use App\Repository\ArchetypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F2.16 — Archetype catalog
 */
class ArchetypeCatalogController extends AbstractController
{
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

            if (!\in_array($sort, ['name', 'decks', 'position'], true)) {
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
}
