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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the favicon as a gray Fairy-type energy SVG to prevent 404 noise.
 */
class FaviconController
{
    private const string FAIRY_ENERGY_SVG = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128">
          <circle cx="64" cy="64" r="60" fill="#e0e0e0" stroke="#999" stroke-width="3"/>
          <g transform="translate(64,64)">
            <!-- Fairy-type energy: six-pointed star with rounded petals -->
            <g fill="#888">
              <ellipse cx="0" cy="-28" rx="10" ry="18"/>
              <ellipse cx="0" cy="-28" rx="10" ry="18" transform="rotate(60)"/>
              <ellipse cx="0" cy="-28" rx="10" ry="18" transform="rotate(120)"/>
              <ellipse cx="0" cy="-28" rx="10" ry="18" transform="rotate(180)"/>
              <ellipse cx="0" cy="-28" rx="10" ry="18" transform="rotate(240)"/>
              <ellipse cx="0" cy="-28" rx="10" ry="18" transform="rotate(300)"/>
            </g>
            <circle cx="0" cy="0" r="8" fill="#aaa"/>
          </g>
        </svg>
        SVG;

    #[Route('/favicon.ico', name: 'app_favicon', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response(
            self::FAIRY_ENERGY_SVG,
            Response::HTTP_OK,
            [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'public, max-age=604800, immutable',
            ],
        );
    }
}
