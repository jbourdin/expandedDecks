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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Handles image uploads for the rich text editor and serves uploaded images.
 *
 * @see docs/features.md F17.4 — Image upload backend (Flysystem)
 */
class EditorUploadController extends AbstractController
{
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    private const int CACHE_MAX_AGE = 86400 * 30; // 30 days

    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly FilesystemOperator $editorUploadStorage,
    ) {
    }

    #[Route('/api/editor/upload-image', name: 'app_editor_upload_image', methods: ['POST'])]
    #[IsGranted('ROLE_CMS_EDITOR')]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'No file provided.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$file->isValid()) {
            return $this->json(['error' => 'File upload failed.'], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = $file->getMimeType();
        if (null === $mimeType || !isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            return $this->json(
                ['error' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->json(
                ['error' => 'File too large. Maximum size: 5 MB.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $extension = self::ALLOWED_MIME_TYPES[$mimeType];
        $filename = Uuid::v4()->toRfc4122().'.'.$extension;

        try {
            $stream = fopen($file->getPathname(), 'r');
            if (false === $stream) {
                return $this->json(['error' => 'Failed to read uploaded file.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->editorUploadStorage->writeStream($filename, $stream);

            if (\is_resource($stream)) {
                fclose($stream);
            }
        } catch (FilesystemException) {
            return $this->json(['error' => 'Failed to store uploaded file.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $url = $this->generateUrl('app_editor_image_show', ['filename' => $filename]);

        return $this->json(['url' => $url]);
    }

    #[Route('/api/editor/image/{filename}', name: 'app_editor_image_show', requirements: ['filename' => '[a-f0-9-]+\.(jpg|png|gif|webp)'], methods: ['GET'])]
    public function show(string $filename): Response
    {
        try {
            if (!$this->editorUploadStorage->fileExists($filename)) {
                throw new NotFoundHttpException('Image not found.');
            }

            $stream = $this->editorUploadStorage->readStream($filename);
            $mimeType = $this->editorUploadStorage->mimeType($filename);
            $lastModified = $this->editorUploadStorage->lastModified($filename);
        } catch (FilesystemException $exception) {
            throw new NotFoundHttpException('Image not found.', $exception);
        }

        $response = new StreamedResponse(static function () use ($stream): void {
            /** @var resource $output */
            $output = fopen('php://output', 'w');
            stream_copy_to_stream($stream, $output);
            fclose($output);

            if (\is_resource($stream)) {
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', \sprintf('public, max-age=%d, immutable', self::CACHE_MAX_AGE));
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified).' GMT');

        return $response;
    }
}
