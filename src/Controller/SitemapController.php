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

use App\Service\Channel\ChannelContext;
use App\Service\Sitemap\SitemapGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves dynamic XML sitemaps per channel.
 *
 * When the total entry count for the channel is small (under 50 000),
 * /sitemap.xml returns a single combined sitemap. When it exceeds
 * the limit, /sitemap.xml returns a sitemap index pointing to
 * per-section sub-sitemaps (/sitemap-pages.xml, /sitemap-decks.xml, etc.).
 *
 * @see docs/features.md F18.23 — Dynamic sitemap generation
 */
class SitemapController extends AbstractController
{
    private const CACHE_MAX_AGE = 3600;

    /**
     * @see docs/features.md F18.23 — Dynamic sitemap generation
     */
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(ChannelContext $channelContext, SitemapGenerator $sitemapGenerator): Response
    {
        $channel = $channelContext->getChannel();

        if ($sitemapGenerator->needsIndex($channel)) {
            $xml = $sitemapGenerator->generateIndex($channel);
        } else {
            $xml = $sitemapGenerator->generateCombined($channel);
        }

        return $this->createXmlResponse($xml);
    }

    /**
     * @see docs/features.md F18.23 — Dynamic sitemap generation
     */
    #[Route('/sitemap-{section}.xml', name: 'app_sitemap_section', methods: ['GET'], requirements: ['section' => 'pages|archetypes|decks|events'])]
    public function section(string $section, ChannelContext $channelContext, SitemapGenerator $sitemapGenerator): Response
    {
        $channel = $channelContext->getChannel();
        $availableSections = $sitemapGenerator->getAvailableSections($channel);

        if (!\in_array($section, $availableSections, true)) {
            throw new NotFoundHttpException(\sprintf('Sitemap section "%s" is not available on this channel.', $section));
        }

        $xml = $sitemapGenerator->generateSection($channel, $section);

        return $this->createXmlResponse($xml);
    }

    private function createXmlResponse(string $xml): Response
    {
        $response = new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);

        $response->setSharedMaxAge(self::CACHE_MAX_AGE);
        $response->headers->set('Cache-Control', 'public, max-age='.self::CACHE_MAX_AGE);

        return $response;
    }
}
