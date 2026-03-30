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

namespace App\Service\EditorUpload;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Visibility;

/**
 * Factory that creates the appropriate Flysystem adapter for editor image uploads.
 *
 * Uses a dedicated storage path/bucket, separate from mosaic storage.
 *
 * @see docs/features.md F17.4 — Image upload backend (Flysystem)
 */
class EditorUploadStorageFactory
{
    public function __construct(
        private readonly string $editorUploadStorageAdapter,
        private readonly string $editorUploadStorageLocalDir,
        private readonly string $projectDir,
        private readonly string $scalewayS3Bucket,
        private readonly string $scalewayS3Region,
        private readonly string $scalewayS3Endpoint,
        private readonly string $scalewayS3AccessKey,
        private readonly string $scalewayS3SecretKey,
    ) {
    }

    public function create(): FilesystemOperator
    {
        if ('s3' === $this->editorUploadStorageAdapter) {
            return $this->createS3Filesystem();
        }

        return $this->createLocalFilesystem();
    }

    private function createLocalFilesystem(): FilesystemOperator
    {
        $path = $this->projectDir.'/'.$this->editorUploadStorageLocalDir;
        $adapter = new LocalFilesystemAdapter($path);

        return new Filesystem($adapter);
    }

    private function createS3Filesystem(): FilesystemOperator
    {
        $client = new S3Client([
            'region' => $this->scalewayS3Region,
            'endpoint' => $this->scalewayS3Endpoint,
            'credentials' => [
                'key' => $this->scalewayS3AccessKey,
                'secret' => $this->scalewayS3SecretKey,
            ],
            'version' => 'latest',
        ]);

        $adapter = new AwsS3V3Adapter(
            $client,
            $this->scalewayS3Bucket,
            'editor',
            new PortableVisibilityConverter(Visibility::PUBLIC),
        );

        return new Filesystem($adapter);
    }
}
