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
use App\Entity\Deck;
use App\Repository\ArchetypeRepository;
use App\Repository\DeckRepository;
use App\Service\DeckVersionDiffer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public comparison page for two archetype variant deck lists.
 *
 * @see docs/features.md F2.10 — Archetype detail page
 */
class ArchetypeVariantCompareController extends AbstractController
{
    /**
     * Display a side-by-side diff of two archetype variants' current deck lists.
     */
    #[Route('/{_locale}/archetypes/{slug}/compare/{shortTagA}/{shortTagB}', name: 'app_archetype_variant_compare', methods: ['GET'], requirements: ['_locale' => 'en|fr', 'slug' => '[a-z0-9-]+', 'shortTagA' => '[A-HJ-NP-Z0-9]{6}', 'shortTagB' => '[A-HJ-NP-Z0-9]{6}'])]
    public function compare(
        string $slug,
        string $shortTagA,
        string $shortTagB,
        Request $request,
        ArchetypeRepository $archetypeRepository,
        DeckRepository $deckRepository,
        DeckVersionDiffer $differ,
    ): Response {
        $archetype = $this->loadArchetypeOrThrow($slug, $request, $archetypeRepository);
        $variants = $deckRepository->findVariantsByArchetype($archetype);

        $variantA = $this->findVariantByShortTag($variants, $shortTagA);
        $variantB = $this->findVariantByShortTag($variants, $shortTagB);

        if (null === $variantA || null === $variantB) {
            throw $this->createNotFoundException();
        }

        $versionA = $variantA->getCurrentVersion();
        $versionB = $variantB->getCurrentVersion();

        $diff = null;
        $hasBothVersions = null !== $versionA && null !== $versionB;

        if ($hasBothVersions) {
            $diff = $differ->diff($versionA, $versionB, mergeByIdentity: true);
        }

        $pickerData = array_map(static fn (Deck $variant): array => [
            'shortTag' => $variant->getShortTag(),
            'name' => $variant->getName(),
            'outdated' => $variant->isOutdated(),
            'latestSetCode' => $variant->getLatestSet()?->getPtcgCode(),
            'sprites' => $variant->getPokemonSlugs(),
        ], $variants);

        return $this->render('archetype/variant_compare.html.twig', [
            'archetype' => $archetype,
            'variantA' => $variantA,
            'variantB' => $variantB,
            'variants' => $variants,
            'pickerData' => $pickerData,
            'diff' => $diff,
            'hasBothVersions' => $hasBothVersions,
        ]);
    }

    private function loadArchetypeOrThrow(string $slug, Request $request, ArchetypeRepository $archetypeRepository): Archetype
    {
        $archetype = $archetypeRepository->findOneBy(['slug' => $slug]);

        if (null === $archetype || null !== $archetype->getDeletedAt()) {
            throw $this->createNotFoundException();
        }

        $isPreview = $request->query->getBoolean('preview');

        if (!$archetype->isPublished() && !($isPreview && $this->isGranted('ROLE_ARCHETYPE_EDITOR'))) {
            throw $this->createNotFoundException();
        }

        return $archetype;
    }

    /**
     * @param list<Deck> $variants
     */
    private function findVariantByShortTag(array $variants, string $shortTag): ?Deck
    {
        foreach ($variants as $variant) {
            if ($variant->getShortTag() === $shortTag) {
                return $variant;
            }
        }

        return null;
    }
}
