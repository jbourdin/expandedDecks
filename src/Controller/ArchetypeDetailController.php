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
use App\Service\ArchetypeDescriptionRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see docs/features.md F2.10 — Archetype detail page
 */
class ArchetypeDetailController extends AbstractController
{
    /**
     * @see docs/features.md F2.10 — Archetype detail page
     */
    #[Route('/archetypes/{slug}', name: 'app_archetype_show', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function show(
        string $slug,
        ArchetypeRepository $archetypeRepository,
        DeckRepository $deckRepository,
        ArchetypeDescriptionRenderer $descriptionRenderer,
    ): Response {
        $archetype = $archetypeRepository->findOneBy(['slug' => $slug]);

        if (null === $archetype) {
            throw $this->createNotFoundException();
        }

        if (!$archetype->isPublished() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException();
        }

        $htmlContent = null !== $archetype->getDescription()
            ? $descriptionRenderer->render($archetype->getDescription())
            : null;

        $latestDecks = $deckRepository->findLatestPublicByArchetype($archetype);
        $totalDeckCount = $deckRepository->countPublicByArchetype($archetype);

        return $this->render('archetype/show.html.twig', [
            'archetype' => $archetype,
            'htmlContent' => $htmlContent,
            'latestDecks' => $latestDecks,
            'totalDeckCount' => $totalDeckCount,
        ]);
    }
}
