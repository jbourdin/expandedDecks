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

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves mosaic images from Flysystem storage with cache-friendly headers.
 *
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
class MosaicController
{
    private const int CACHE_MAX_AGE = 86400 * 30; // 30 days

    public function __construct(
        private readonly FilesystemOperator $mosaicStorage,
    ) {
    }

    #[Route('/mosaic/{deckId}/{versionId}.png', name: 'app_mosaic_show', requirements: ['deckId' => '\d+', 'versionId' => '\d+'], methods: ['GET'])]
    public function show(int $deckId, int $versionId): Response
    {
        $path = \sprintf('mosaic/%d/%d.png', $deckId, $versionId);

        try {
            if (!$this->mosaicStorage->fileExists($path)) {
                throw new NotFoundHttpException('Mosaic image not found.');
            }

            $stream = $this->mosaicStorage->readStream($path);
            $lastModified = $this->mosaicStorage->lastModified($path);
        } catch (FilesystemException $exception) {
            throw new NotFoundHttpException('Mosaic image not found.', $exception);
        }

        $response = new StreamedResponse(static function () use ($stream): void {
            $output = fopen('php://output', 'w');

            if (false !== $output) {
                stream_copy_to_stream($stream, $output);
                fclose($output);
            }

            if (\is_resource($stream)) {
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', \sprintf('public, max-age=%d, immutable', self::CACHE_MAX_AGE));
        $response->setLastModified((new \DateTimeImmutable())->setTimestamp($lastModified));

        return $response;
    }
}
