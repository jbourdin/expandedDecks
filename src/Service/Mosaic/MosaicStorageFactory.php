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

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Factory that creates the appropriate Flysystem adapter for mosaic storage.
 *
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 * @see docs/technicalities/mosaic.md
 */
class MosaicStorageFactory
{
    public function __construct(
        private readonly string $mosaicStorageAdapter,
        private readonly string $mosaicStorageLocalDir,
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
        if ('s3' === $this->mosaicStorageAdapter) {
            return $this->createS3Filesystem();
        }

        return $this->createLocalFilesystem();
    }

    private function createLocalFilesystem(): FilesystemOperator
    {
        $path = $this->projectDir.'/'.$this->mosaicStorageLocalDir;
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

        $adapter = new AwsS3V3Adapter($client, $this->scalewayS3Bucket);

        return new Filesystem($adapter);
    }
}
