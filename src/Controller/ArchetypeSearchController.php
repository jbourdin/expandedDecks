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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public archetype search endpoints for autocomplete widgets.
 *
 * Separated from ArchetypeController (which requires ROLE_USER) so that
 * the catalog filter can search archetypes without authentication.
 *
 * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
 */
class ArchetypeSearchController extends AbstractController
{
    #[Route('/api/archetype/search', name: 'app_archetype_search', methods: ['GET'])]
    public function search(Request $request, ArchetypeRepository $archetypeRepository): JsonResponse
    {
        $query = trim($request->query->getString('q'));

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        $archetypes = $archetypeRepository->searchByName($query);

        $data = array_map(static fn (Archetype $a): array => [
            'id' => $a->getId(),
            'name' => $a->getName(),
            'slug' => $a->getSlug(),
        ], $archetypes);

        return $this->json($data);
    }

    /**
     * Returns all published archetypes that have at least one public deck,
     * including pokemon slugs for sprite rendering in the filter dropdown.
     *
     * @see docs/features.md F2.17 — Deck catalog archetype filter UX
     */
    #[Route('/api/archetype/catalog', name: 'app_archetype_catalog', methods: ['GET'])]
    public function catalog(ArchetypeRepository $archetypeRepository): JsonResponse
    {
        $archetypes = $archetypeRepository->findPublishedWithPublicDecks();

        $data = array_map(static fn (Archetype $archetype): array => [
            'name' => $archetype->getName(),
            'slug' => $archetype->getSlug(),
            'pokemonSlugs' => $archetype->getPokemonSlugs(),
        ], $archetypes);

        return $this->json($data);
    }
}
