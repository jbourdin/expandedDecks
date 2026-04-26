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

use App\Repository\PokemonSpriteMappingRepository;
use App\Service\Sprite\SpriteResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Proxy controller for Pokemon HOME sprites.
 *
 * Serves sprites from a filesystem cache, fetching from PokeAPI on cache miss.
 * Designed to sit behind a Bunny CDN pull zone for edge caching.
 *
 * @see docs/features.md F2.26 — Upgrade sprites to Pokemon HOME 3D renders
 */
class SpriteProxyController extends AbstractController
{
    #[Route('/sprites/pokemon/{slug}.png', name: 'app_sprite_pokemon', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function pokemon(string $slug, SpriteResolver $spriteResolver): Response
    {
        $path = $spriteResolver->resolve($slug);

        if (null === $path) {
            throw new NotFoundHttpException(\sprintf('Sprite not found: %s', $slug));
        }

        $content = file_get_contents($path);

        if (false === $content) {
            throw new NotFoundHttpException(\sprintf('Failed to read sprite: %s', $slug));
        }

        return new Response($content, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=2592000', // 30 days
        ]);
    }

    /**
     * Return all available sprite slugs for the PokemonSpriteSelect autocomplete.
     */
    #[Route('/api/sprites/slugs', name: 'app_sprite_slugs', methods: ['GET'])]
    public function slugs(PokemonSpriteMappingRepository $repository): JsonResponse
    {
        return new JsonResponse($repository->findAllSlugs());
    }
}
