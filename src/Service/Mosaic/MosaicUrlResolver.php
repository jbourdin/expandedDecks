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

use App\Entity\DeckVersion;
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
     * Uses the deck's shortTag for a human-readable URL (e.g. /mosaic/AB3K7N/5.png).
     */
    public function resolveForVersion(DeckVersion $version, string $variant = ''): string
    {
        $shortTag = $version->getDeck()->getShortTag();
        $versionId = (string) $version->getId();

        $fileName = '' !== $variant
            ? \sprintf('%s_%s', $versionId, $variant)
            : $versionId;

        return $this->urlGenerator->generate('app_mosaic_show', [
            'shortTag' => $shortTag,
            'versionId' => $fileName,
        ]);
    }
}
