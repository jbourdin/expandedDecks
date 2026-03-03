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
use App\Repository\DeckRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
 */
class DeckCatalogController extends AbstractController
{
    private const int PER_PAGE = 12;

    #[Route('/deck', name: 'app_deck_list', methods: ['GET'], priority: 10)]
    public function list(Request $request, DeckRepository $deckRepository, ArchetypeRepository $archetypeRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        $filters = [
            'search' => $request->query->getString('q'),
            'archetype' => $request->query->getString('archetype'),
            'status' => $request->query->getString('status'),
            'format' => $request->query->getString('format'),
        ];

        $ownerId = $request->query->getInt('owner');
        if ($ownerId > 0) {
            $filters['owner'] = $ownerId;
        }

        $qb = $deckRepository->createCatalogQueryBuilder($filters);
        $qb->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        return $this->render('deck/list.html.twig', [
            'decks' => $paginator,
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'archetypes' => $archetypeRepository->findBy([], ['name' => 'ASC']),
        ]);
    }
}
