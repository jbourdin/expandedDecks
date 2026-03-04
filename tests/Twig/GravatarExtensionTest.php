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

namespace App\Tests\Twig;

use App\Twig\Runtime\GravatarRuntime;
use PHPUnit\Framework\TestCase;

class GravatarExtensionTest extends TestCase
{
    public function testUrlReturnsGravatarUrl(): void
    {
        $runtime = new GravatarRuntime();
        $url = $runtime->url('test@example.com');

        $expectedHash = md5('test@example.com');
        self::assertSame(
            \sprintf('https://www.gravatar.com/avatar/%s?s=32&d=mp', $expectedHash),
            $url,
        );
    }

    public function testUrlNormalizesEmailCase(): void
    {
        $runtime = new GravatarRuntime();

        self::assertSame(
            $runtime->url('test@example.com'),
            $runtime->url('TEST@Example.COM'),
        );
    }

    public function testUrlTrimsWhitespace(): void
    {
        $runtime = new GravatarRuntime();

        self::assertSame(
            $runtime->url('test@example.com'),
            $runtime->url('  test@example.com  '),
        );
    }

    public function testUrlRespectsCustomSize(): void
    {
        $runtime = new GravatarRuntime();
        $url = $runtime->url('test@example.com', 128);

        self::assertStringContainsString('s=128', $url);
    }
}
