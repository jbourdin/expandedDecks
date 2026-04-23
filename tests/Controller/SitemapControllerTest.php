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

use App\Controller\SitemapController;
use App\Entity\Channel;
use App\Service\Channel\ChannelContext;
use App\Service\Sitemap\SitemapGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @see docs/features.md F18.23 — Dynamic sitemap generation
 */
final class SitemapControllerTest extends TestCase
{
    private Channel $channel;

    protected function setUp(): void
    {
        $this->channel = (new Channel())
            ->setCode('app')
            ->setDomain('expandeddecks.wip')
            ->setEnableDecks(true)
            ->setEnableEvents(true);
    }

    public function testSitemapReturnsCombinedWhenUnderLimit(): void
    {
        $channelContext = $this->createChannelContext($this->channel);

        $sitemapGenerator = $this->createStub(SitemapGenerator::class);
        $sitemapGenerator->method('needsIndex')->willReturn(false);
        $sitemapGenerator->method('generateCombined')->willReturn('<urlset></urlset>');

        $controller = new SitemapController();
        $response = $controller->sitemap($channelContext, $sitemapGenerator);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/xml; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'));
        self::assertSame('<urlset></urlset>', $response->getContent());
    }

    public function testSitemapReturnsIndexWhenOverLimit(): void
    {
        $channelContext = $this->createChannelContext($this->channel);

        $sitemapGenerator = $this->createStub(SitemapGenerator::class);
        $sitemapGenerator->method('needsIndex')->willReturn(true);
        $sitemapGenerator->method('generateIndex')->willReturn('<sitemapindex></sitemapindex>');

        $controller = new SitemapController();
        $response = $controller->sitemap($channelContext, $sitemapGenerator);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<sitemapindex></sitemapindex>', $response->getContent());
    }

    public function testSectionReturnsXmlForValidSection(): void
    {
        $channelContext = $this->createChannelContext($this->channel);

        $sitemapGenerator = $this->createStub(SitemapGenerator::class);
        $sitemapGenerator->method('getAvailableSections')->willReturn(['pages', 'decks', 'events']);
        $sitemapGenerator->method('generateSection')->willReturn('<urlset></urlset>');

        $controller = new SitemapController();
        $response = $controller->section('decks', $channelContext, $sitemapGenerator);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/xml; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testSectionThrows404ForUnavailableSection(): void
    {
        $channelContext = $this->createChannelContext($this->channel);

        $sitemapGenerator = $this->createStub(SitemapGenerator::class);
        $sitemapGenerator->method('getAvailableSections')->willReturn(['pages', 'decks', 'events']);

        $controller = new SitemapController();

        $this->expectException(NotFoundHttpException::class);
        $controller->section('archetypes', $channelContext, $sitemapGenerator);
    }

    private function createChannelContext(Channel $channel): ChannelContext
    {
        $request = new Request();
        $request->attributes->set('_channel', $channel);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new ChannelContext($requestStack);
    }
}
