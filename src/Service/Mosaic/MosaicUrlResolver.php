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

namespace App\Service\Mosaic;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Resolves a Flysystem storage path to a publicly accessible URL
 * served by the application's mosaic controller.
 *
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
class MosaicUrlResolver
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Build the application URL that serves the mosaic image.
     *
     * The storage path follows the pattern "mosaic/{deckId}/{versionId}.png".
     */
    public function resolve(string $storagePath): string
    {
        // Extract deckId and versionId from "mosaic/{deckId}/{versionId}.png"
        if (!preg_match('#^mosaic/(\d+)/(\d+)\.png$#', $storagePath, $matches)) {
            throw new \InvalidArgumentException(\sprintf('Unexpected mosaic storage path format: "%s".', $storagePath));
        }

        return $this->urlGenerator->generate('app_mosaic_show', [
            'deckId' => (int) $matches[1],
            'versionId' => (int) $matches[2],
        ]);
    }
}
