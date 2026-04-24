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

namespace App\Controller;

use App\Entity\Channel;
use App\Service\Channel\ChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Serves a channel-aware robots.txt with per-channel crawl directives.
 *
 * The app channel exposes decks, events, and pages; the content channel
 * exposes archetypes and pages. Auth, admin, and internal paths are
 * blocked on both. Each response includes a Sitemap directive pointing
 * to the channel's own sitemap.
 *
 * @see docs/features.md F18.24 — Channel-aware robots.txt
 */
class RobotsTxtController extends AbstractController
{
    private const CACHE_MAX_AGE = 3600;

    /**
     * @see docs/features.md F18.24 — Channel-aware robots.txt
     */
    #[Route('/robots.txt', name: 'app_robots_txt', methods: ['GET'])]
    public function __invoke(ChannelContext $channelContext, UrlGeneratorInterface $urlGenerator): Response
    {
        $channel = $channelContext->getChannel();
        $scheme = $urlGenerator->getContext()->getScheme();
        $sitemapUrl = $scheme.'://'.$channel->getDomain().$urlGenerator->generate('app_sitemap');

        $lines = $this->buildDirectives($channel, $sitemapUrl);

        $response = new Response(implode("\n", $lines)."\n", Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);

        $response->setSharedMaxAge(self::CACHE_MAX_AGE);
        $response->headers->set('Cache-Control', 'public, max-age='.self::CACHE_MAX_AGE);

        return $response;
    }

    /**
     * @return list<string>
     */
    private function buildDirectives(Channel $channel, string $sitemapUrl): array
    {
        $lines = ['User-agent: *', 'Crawl-delay: 1'];

        if ($channel->getEnableArchetypes()) {
            $lines = [...$lines, ...$this->buildContentChannelRules()];
        } else {
            $lines = [...$lines, ...$this->buildAppChannelRules()];
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$sitemapUrl;

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function buildContentChannelRules(): array
    {
        return [
            'Allow: /',
            'Allow: /en/pages/',
            'Allow: /fr/pages/',
            'Allow: /en/archetypes',
            'Allow: /fr/archetypes',
            '',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Disallow: /build/',
        ];
    }

    /**
     * @return list<string>
     */
    private function buildAppChannelRules(): array
    {
        return [
            'Allow: /',
            'Allow: /en/pages/',
            'Allow: /fr/pages/',
            'Allow: /deck/',
            'Allow: /event',
            '',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Disallow: /build/',
            'Disallow: /archetypes',
            'Disallow: /en/archetypes',
            'Disallow: /fr/archetypes',
            'Disallow: /borrow',
            'Disallow: /borrows',
            'Disallow: /confirm-deletion/',
            'Disallow: /dashboard',
            'Disallow: /forgot-password',
            'Disallow: /health',
            'Disallow: /lends',
            'Disallow: /login',
            'Disallow: /logout',
            'Disallow: /mosaic/',
            'Disallow: /notifications',
            'Disallow: /profile',
            'Disallow: /register',
            'Disallow: /reset-password/',
            'Disallow: /verify/',
            'Disallow: /webhook/',
        ];
    }
}
