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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @see docs/features.md F2.6 — Archetype management (create, browse, detail)
 */
#[Route('/api/archetype')]
#[IsGranted('ROLE_USER')]
class ArchetypeController extends AbstractController
{
    /**
     * @see docs/features.md F2.6 — Archetype management (create, browse, detail)
     */
    #[Route('/search', name: 'app_archetype_search', methods: ['GET'])]
    public function search(Request $request, ArchetypeRepository $archetypeRepository): JsonResponse
    {
        $query = $request->query->getString('q');

        if (\strlen($query) < 2) {
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
     * @see docs/features.md F2.6 — Archetype management (create, browse, detail)
     */
    #[Route('', name: 'app_archetype_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ArchetypeRepository $archetypeRepository,
        ValidatorInterface $validator,
    ): JsonResponse {
        /** @var array{name?: string} $payload */
        $payload = $request->toArray();
        $name = trim($payload['name'] ?? '');

        if ('' === $name) {
            return $this->json(['error' => 'Name is required.'], Response::HTTP_BAD_REQUEST);
        }

        // Check uniqueness by name
        $existing = $archetypeRepository->findOneBy(['name' => $name]);
        if (null !== $existing) {
            return $this->json([
                'id' => $existing->getId(),
                'name' => $existing->getName(),
                'slug' => $existing->getSlug(),
            ]);
        }

        $archetype = new Archetype();
        $archetype->setName($name);

        $violations = $validator->validate($archetype);
        if (\count($violations) > 0) {
            return $this->json(['error' => (string) $violations->get(0)->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($archetype);
        $em->flush();

        return $this->json([
            'id' => $archetype->getId(),
            'name' => $archetype->getName(),
            'slug' => $archetype->getSlug(),
        ], Response::HTTP_CREATED);
    }
}
