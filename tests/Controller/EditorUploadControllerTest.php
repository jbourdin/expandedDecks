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

use App\Controller\EditorUploadController;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @see docs/features.md F17.4 — Image upload backend (Flysystem)
 */
final class EditorUploadControllerTest extends TestCase
{
    public function testUploadReturns400WhenNoFileProvided(): void
    {
        $storage = $this->createStub(FilesystemOperator::class);
        $controller = new EditorUploadController($storage);
        $controller->setContainer($this->createContainerWithRouter());

        $request = new Request();
        $response = $controller->upload($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('No file provided', (string) $response->getContent());
    }

    public function testUploadRejects422WhenInvalidMimeType(): void
    {
        $storage = $this->createStub(FilesystemOperator::class);
        $controller = new EditorUploadController($storage);
        $controller->setContainer($this->createContainerWithRouter());

        $file = $this->createTempUploadedFile('test.txt', 'text/plain', 'hello');
        $request = new Request(files: ['file' => $file]);
        $response = $controller->upload($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Invalid file type', (string) $response->getContent());
    }

    public function testUploadRejects422WhenFileTooLarge(): void
    {
        $storage = $this->createStub(FilesystemOperator::class);
        $controller = new EditorUploadController($storage);
        $controller->setContainer($this->createContainerWithRouter());

        // Create a valid PNG header followed by padding to exceed 5 MB
        $tempFile = tempnam(sys_get_temp_dir(), 'upload');
        self::assertNotFalse($tempFile);

        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        imagepng($image, $tempFile);

        // Append padding to make it exceed 5 MB
        $handle = fopen($tempFile, 'a');
        self::assertNotFalse($handle);
        fwrite($handle, str_repeat("\0", 6 * 1024 * 1024));
        fclose($handle);

        $file = new UploadedFile($tempFile, 'large.png', 'image/png', null, true);
        $request = new Request(files: ['file' => $file]);
        $response = $controller->upload($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('File too large', (string) $response->getContent());

        @unlink($tempFile);
    }

    public function testUploadSucceedsWithValidImage(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects(self::once())->method('writeStream');

        $controller = new EditorUploadController($storage);
        $controller->setContainer($this->createContainerWithRouter());

        $file = $this->createTempUploadedFile('test.png', 'image/png', $this->createMinimalPng());
        $request = new Request(files: ['file' => $file]);
        $response = $controller->upload($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('url', $data);
        self::assertStringContainsString('/api/editor/image/', $data['url']);
        self::assertStringEndsWith('.png', $data['url']);
    }

    public function testShowReturns404WhenFileDoesNotExist(): void
    {
        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('fileExists')->willReturn(false);

        $controller = new EditorUploadController($storage);

        $this->expectException(NotFoundHttpException::class);
        $controller->show('nonexistent.png');
    }

    public function testShowStreamsExistingFile(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);
        fwrite($stream, 'fake-image-data');
        rewind($stream);

        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('fileExists')->willReturn(true);
        $storage->method('readStream')->willReturn($stream);
        $storage->method('mimeType')->willReturn('image/png');
        $storage->method('lastModified')->willReturn(time());

        $controller = new EditorUploadController($storage);
        $response = $controller->show('test-file.png');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
    }

    private function createTempUploadedFile(string $name, string $mimeType, string $content): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'upload');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, $content);

        return new UploadedFile($tempFile, $name, $mimeType, null, true);
    }

    /**
     * Creates a minimal valid 1x1 PNG file content.
     */
    private function createMinimalPng(): string
    {
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image);
        $content = ob_get_clean();

        return (string) $content;
    }

    /**
     * Creates a minimal DI container with a router stub for AbstractController::generateUrl.
     */
    private function createContainerWithRouter(): \Psr\Container\ContainerInterface
    {
        $router = $this->createStub(\Symfony\Component\Routing\RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters): string => '/api/editor/image/'.$parameters['filename'],
        );

        $container = $this->createStub(\Psr\Container\ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            static fn (string $id): bool => 'router' === $id,
        );
        $container->method('get')->willReturnCallback(
            static fn (string $id) => 'router' === $id ? $router : null,
        );

        return $container;
    }
}
