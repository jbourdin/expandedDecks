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

use App\Controller\FaviconController;
use PHPUnit\Framework\TestCase;

final class FaviconControllerTest extends TestCase
{
    public function testReturns200WithSvgContent(): void
    {
        $controller = new FaviconController();

        $response = $controller();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        self::assertStringContainsString('<svg', (string) $response->getContent());
    }

    public function testResponseIsCacheable(): void
    {
        $controller = new FaviconController();

        $response = $controller();

        self::assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('max-age=604800', (string) $response->headers->get('Cache-Control'));
    }
}
