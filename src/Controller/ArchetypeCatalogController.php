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
     */
    #[Route('/archetypes', name: 'app_archetype_list', methods: ['GET'], priority: 10)]
    public function list(Request $request, ArchetypeRepository $archetypeRepository): Response
    {
        /** @var list<string> $tags */
        $tags = $request->query->all('tags');
        $tags = array_values(array_filter($tags, static fn (string $tag): bool => '' !== $tag));
        $sort = $request->query->getString('sort', 'name');

        if (!\in_array($sort, ['name', 'decks'], true)) {
            $sort = 'name';
        }

        $results = $archetypeRepository->findPublishedWithDeckCounts($tags, $sort);
        $allTags = $archetypeRepository->findAllPublishedPlaystyleTags();

        return $this->render('archetype/list.html.twig', [
            'results' => $results,
            'allTags' => $allTags,
            'currentTags' => $tags,
            'currentSort' => $sort,
        ]);
    }
}
