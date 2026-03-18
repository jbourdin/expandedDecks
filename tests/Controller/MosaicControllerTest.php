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

namespace App\Tests\Controller;

use App\Controller\MosaicController;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
final class MosaicControllerTest extends TestCase
{
    public function testShowReturns404WhenFileDoesNotExist(): void
    {
        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('fileExists')->willReturn(false);

        $controller = new MosaicController($storage);

        $this->expectException(NotFoundHttpException::class);

        $controller->show(1, '2');
    }

    public function testShowReturns404OnFilesystemException(): void
    {
        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('fileExists')->willThrowException(
            UnableToReadFile::fromLocation('mosaic/1/2.png'),
        );

        $controller = new MosaicController($storage);

        $this->expectException(NotFoundHttpException::class);

        $controller->show(1, '2');
    }

    public function testShowReturnsStreamedResponseWithCacheHeaders(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);
        fwrite($stream, 'PNG-DATA');
        rewind($stream);

        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('fileExists')->willReturn(true);
        $storage->method('readStream')->willReturn($stream);
        $storage->method('lastModified')->willReturn(1710000000);

        $controller = new MosaicController($storage);
        $response = $controller->show(42, '7');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        self::assertNotNull($response->getLastModified());
    }
}
