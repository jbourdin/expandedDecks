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

use App\Service\Mosaic\MosaicUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
final class MosaicUrlResolverTest extends TestCase
{
    private UrlGeneratorInterface $urlGenerator;
    private MosaicUrlResolver $resolver;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->resolver = new MosaicUrlResolver($this->urlGenerator);
    }

    public function testResolveValidPathGeneratesRoute(): void
    {
        $this->urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_mosaic_show', ['deckId' => 42, 'versionId' => 7])
            ->willReturn('/mosaic/42/7.png');

        $result = $this->resolver->resolve('mosaic/42/7.png');

        self::assertSame('/mosaic/42/7.png', $result);
    }

    public function testResolveInvalidPathThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unexpected mosaic storage path format');

        $this->resolver->resolve('invalid/path.jpg');
    }

    public function testResolvePathWithoutPngExtensionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver->resolve('mosaic/1/2.jpg');
    }
}
