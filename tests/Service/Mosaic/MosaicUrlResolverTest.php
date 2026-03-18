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

use App\Entity\Deck;
use App\Entity\DeckVersion;
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

    public function testResolveForVersionGeneratesRouteWithShortTag(): void
    {
        $version = $this->createVersion('AB3K7N', 7);

        $this->urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_mosaic_show', ['shortTag' => 'AB3K7N', 'versionId' => '7'])
            ->willReturn('/mosaic/AB3K7N/7.png');

        $result = $this->resolver->resolveForVersion($version);

        self::assertSame('/mosaic/AB3K7N/7.png', $result);
    }

    public function testResolveForVersionWithVariant(): void
    {
        $version = $this->createVersion('XY9Z2P', 3);

        $this->urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_mosaic_show', ['shortTag' => 'XY9Z2P', 'versionId' => '3_minified'])
            ->willReturn('/mosaic/XY9Z2P/3_minified.png');

        $result = $this->resolver->resolveForVersion($version, 'minified');

        self::assertSame('/mosaic/XY9Z2P/3_minified.png', $result);
    }

    private function createVersion(string $shortTag, int $versionId): DeckVersion
    {
        $deck = new Deck();
        $deckReflection = new \ReflectionProperty(Deck::class, 'shortTag');
        $deckReflection->setValue($deck, $shortTag);

        $version = new DeckVersion();
        $version->setDeck($deck);
        $versionReflection = new \ReflectionProperty(DeckVersion::class, 'id');
        $versionReflection->setValue($version, $versionId);

        return $version;
    }
}
