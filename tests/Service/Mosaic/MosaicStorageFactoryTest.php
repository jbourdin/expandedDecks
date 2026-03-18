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

namespace App\Tests\Service\Mosaic;

use App\Service\Mosaic\MosaicStorageFactory;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
final class MosaicStorageFactoryTest extends TestCase
{
    public function testCreateLocalReturnsFilesystemOperator(): void
    {
        $factory = new MosaicStorageFactory(
            mosaicStorageAdapter: 'local',
            mosaicStorageLocalDir: 'var/storage/mosaic',
            projectDir: sys_get_temp_dir(),
            scalewayS3Bucket: '',
            scalewayS3Region: '',
            scalewayS3Endpoint: '',
            scalewayS3AccessKey: '',
            scalewayS3SecretKey: '',
        );

        $filesystem = $factory->create();

        self::assertInstanceOf(FilesystemOperator::class, $filesystem);
    }

    public function testCreateDefaultsToLocalAdapter(): void
    {
        $factory = new MosaicStorageFactory(
            mosaicStorageAdapter: 'unknown',
            mosaicStorageLocalDir: 'var/storage/mosaic',
            projectDir: sys_get_temp_dir(),
            scalewayS3Bucket: '',
            scalewayS3Region: '',
            scalewayS3Endpoint: '',
            scalewayS3AccessKey: '',
            scalewayS3SecretKey: '',
        );

        $filesystem = $factory->create();

        self::assertInstanceOf(FilesystemOperator::class, $filesystem);
    }
}
