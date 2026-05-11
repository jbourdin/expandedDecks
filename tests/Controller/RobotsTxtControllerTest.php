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

use App\Controller\RobotsTxtController;
use App\Entity\Channel;
use App\Service\Channel\ChannelContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * @see docs/features.md F18.24 — Channel-aware robots.txt
 */
final class RobotsTxtControllerTest extends TestCase
{
    public function testAppChannelAllowsDecksAndEvents(): void
    {
        $channel = (new Channel())
            ->setCode('app')
            ->setDomain('expandeddecks.wip')
            ->setEnableDecks(true)
            ->setEnableEvents(true)
            ->setEnableArchetypes(false);

        $response = $this->invokeController($channel);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        self::assertStringContainsString('Allow: /en/pages/', $body);
        self::assertStringContainsString('Allow: /fr/pages/', $body);
        self::assertStringContainsString('Allow: /deck/', $body);
        self::assertStringContainsString('Allow: /event', $body);
        self::assertStringContainsString('Disallow: /archetypes', $body);
        self::assertStringContainsString('Disallow: /en/archetypes', $body);
        self::assertStringContainsString('Disallow: /fr/archetypes', $body);
        self::assertStringContainsString('Disallow: /login', $body);
        self::assertStringContainsString('Disallow: /register', $body);
        self::assertStringContainsString('Sitemap: https://expandeddecks.wip/sitemap.xml', $body);
    }

    public function testContentChannelAllowsArchetypes(): void
    {
        $channel = (new Channel())
            ->setCode('content')
            ->setDomain('expandedtalks.wip')
            ->setEnableArchetypes(true)
            ->setEnableDecks(false)
            ->setEnableEvents(false);

        $response = $this->invokeController($channel);

        $body = (string) $response->getContent();
        self::assertStringContainsString('Allow: /en/archetypes', $body);
        self::assertStringContainsString('Allow: /fr/archetypes', $body);
        self::assertStringContainsString('Allow: /en/pages/', $body);
        self::assertStringContainsString('Allow: /fr/pages/', $body);
        self::assertStringNotContainsString('Allow: /deck/', $body);
        self::assertStringNotContainsString('Allow: /event', $body);
        self::assertStringContainsString('Disallow: /admin/', $body);
        self::assertStringContainsString('Sitemap: https://expandedtalks.wip/sitemap.xml', $body);
    }

    public function testCrawlDelayIsPresent(): void
    {
        $channel = (new Channel())
            ->setCode('app')
            ->setDomain('expandeddecks.wip')
            ->setEnableArchetypes(false);

        $response = $this->invokeController($channel);

        $body = (string) $response->getContent();
        self::assertStringContainsString('Crawl-delay: 1', $body);
    }

    public function testCacheControlHeader(): void
    {
        $channel = (new Channel())
            ->setCode('app')
            ->setDomain('expandeddecks.wip')
            ->setEnableArchetypes(false);

        $response = $this->invokeController($channel);

        self::assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'));
    }

    public function testEditorImageEndpointIsAllowedOnBothChannels(): void
    {
        $appChannel = (new Channel())
            ->setCode('app')
            ->setDomain('expandeddecks.wip')
            ->setEnableArchetypes(false);

        $contentChannel = (new Channel())
            ->setCode('content')
            ->setDomain('expandedtalks.wip')
            ->setEnableArchetypes(true);

        $appBody = (string) $this->invokeController($appChannel)->getContent();
        $contentBody = (string) $this->invokeController($contentChannel)->getContent();

        self::assertStringContainsString('Allow: /api/editor/image/*', $appBody);
        self::assertStringContainsString('Allow: /api/editor/image/*', $contentBody);
    }

    private function invokeController(Channel $channel): \Symfony\Component\HttpFoundation\Response
    {
        $request = new Request();
        $request->attributes->set('_channel', $channel);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $channelContext = new ChannelContext($requestStack);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn(new RequestContext(scheme: 'https'));
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $routeName): string => match ($routeName) {
                'app_sitemap' => '/sitemap.xml',
                default => '/'.$routeName,
            },
        );

        $controller = new RobotsTxtController();

        return $controller($channelContext, $urlGenerator);
    }
}
