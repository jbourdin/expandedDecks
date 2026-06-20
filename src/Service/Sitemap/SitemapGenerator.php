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

namespace App\Service\Sitemap;

use App\Entity\Channel;
use App\Repository\ArchetypeRepository;
use App\Repository\DeckRepository;
use App\Repository\EventRepository;
use App\Repository\PageRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generates XML sitemap content for a given channel.
 *
 * Each channel serves its own sitemap at /sitemap.xml, containing only
 * the content types enabled by the channel's feature flags. URLs are
 * always absolute, using the channel's domain.
 *
 * @see docs/features.md F18.23 — Dynamic sitemap generation
 */
readonly class SitemapGenerator
{
    private const MAX_ENTRIES_PER_SITEMAP = 50_000;

    public function __construct(
        private PageRepository $pageRepository,
        private ArchetypeRepository $archetypeRepository,
        private DeckRepository $deckRepository,
        private EventRepository $eventRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Return the list of available sitemap sections for a channel.
     *
     * @return list<string>
     */
    public function getAvailableSections(Channel $channel): array
    {
        $sections = ['pages'];

        if ($channel->getEnableArchetypes()) {
            $sections[] = 'archetypes';
        }

        if ($channel->getEnableDecks()) {
            $sections[] = 'decks';
        }

        if ($channel->getEnableEvents()) {
            $sections[] = 'events';
        }

        return $sections;
    }

    /**
     * Generate XML for the sitemap index.
     */
    public function generateIndex(Channel $channel): string
    {
        $sections = $this->getAvailableSections($channel);
        $scheme = $this->urlGenerator->getContext()->getScheme();
        $domain = $channel->getDomain();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($sections as $section) {
            $location = $scheme.'://'.$domain.$this->urlGenerator->generate('app_sitemap_section', ['section' => $section]);
            $xml .= '  <sitemap>'."\n";
            $xml .= '    <loc>'.self::escapeXml($location).'</loc>'."\n";
            $xml .= '  </sitemap>'."\n";
        }

        $xml .= '</sitemapindex>'."\n";

        return $xml;
    }

    /**
     * Generate XML for a specific sitemap section.
     */
    public function generateSection(Channel $channel, string $section): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        $xml .= $this->generateHomepageEntries($channel);

        $xml .= match ($section) {
            'pages' => $this->generatePageEntries($channel),
            'archetypes' => $this->generateArchetypeEntries($channel),
            'decks' => $this->generateDeckEntries($channel),
            'events' => $this->generateEventEntries($channel),
            default => '',
        };

        $xml .= '</urlset>'."\n";

        return $xml;
    }

    /**
     * Check if the total entry count warrants a sitemap index.
     */
    public function needsIndex(Channel $channel): bool
    {
        $localeCount = \count($channel->getLocales());

        // Homepage + pages + archetypes are locale-multiplied; decks and events are not
        $count = $localeCount; // homepage entries
        $count += \count($this->pageRepository->findPublishedForSitemap($channel)) * $localeCount;

        if ($channel->getEnableArchetypes()) {
            $count += \count($this->archetypeRepository->findPublishedForSitemap()) * $localeCount;
        }

        if ($channel->getEnableDecks()) {
            $count += \count($this->deckRepository->findPublicForSitemap());
        }

        if ($channel->getEnableEvents()) {
            $count += \count($this->eventRepository->findPublicForSitemap());
        }

        return $count > self::MAX_ENTRIES_PER_SITEMAP;
    }

    /**
     * Generate a single combined sitemap with all entries for the channel.
     */
    public function generateCombined(Channel $channel): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        $xml .= $this->generateHomepageEntries($channel);
        $xml .= $this->generatePageEntries($channel);

        if ($channel->getEnableArchetypes()) {
            $xml .= $this->generateArchetypeEntries($channel);
        }

        if ($channel->getEnableDecks()) {
            $xml .= $this->generateDeckEntries($channel);
        }

        if ($channel->getEnableEvents()) {
            $xml .= $this->generateEventEntries($channel);
        }

        $xml .= '</urlset>'."\n";

        return $xml;
    }

    private function generateHomepageEntries(Channel $channel): string
    {
        $xml = '';

        foreach ($channel->getLocales() as $locale) {
            $xml .= $this->buildUrlEntry(
                $this->buildAbsoluteUrl($channel, 'app_home_localized', ['_locale' => $locale]),
                null,
                'daily',
                '1.0',
            );
        }

        return $xml;
    }

    private function generatePageEntries(Channel $channel): string
    {
        $pages = $this->pageRepository->findPublishedForSitemap($channel);
        $xml = '';

        foreach ($pages as $page) {
            foreach ($channel->getLocales() as $locale) {
                $xml .= $this->buildUrlEntry(
                    $this->buildAbsoluteUrl($channel, 'app_page_show', ['slug' => $page['slug'], '_locale' => $locale]),
                    $page['updatedAt'] ?? $page['createdAt'],
                    'monthly',
                    '0.6',
                );
            }
        }

        return $xml;
    }

    private function generateArchetypeEntries(Channel $channel): string
    {
        $archetypes = $this->archetypeRepository->findPublishedForSitemap();
        $xml = '';

        foreach ($archetypes as $archetype) {
            foreach ($channel->getLocales() as $locale) {
                $xml .= $this->buildUrlEntry(
                    $this->buildAbsoluteUrl($channel, 'app_archetype_show', ['slug' => $archetype['slug'], '_locale' => $locale]),
                    $archetype['updatedAt'] ?? $archetype['createdAt'],
                    'weekly',
                    '0.8',
                );
            }
        }

        return $xml;
    }

    private function generateDeckEntries(Channel $channel): string
    {
        $decks = $this->deckRepository->findPublicForSitemap();
        $xml = '';

        foreach ($decks as $deck) {
            $xml .= $this->buildUrlEntry(
                $this->buildAbsoluteUrl($channel, 'app_deck_show', ['short_tag' => $deck['shortTag']]),
                $deck['updatedAt'] ?? $deck['createdAt'],
                'weekly',
                '0.5',
            );
        }

        return $xml;
    }

    private function generateEventEntries(Channel $channel): string
    {
        $events = $this->eventRepository->findPublicForSitemap();
        $xml = '';

        foreach ($events as $event) {
            $xml .= $this->buildUrlEntry(
                $this->buildAbsoluteUrl($channel, 'app_event_show', ['id' => $event['id']]),
                $event['date'],
                'monthly',
                '0.5',
            );
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function buildAbsoluteUrl(Channel $channel, string $routeName, array $parameters = []): string
    {
        $path = $this->urlGenerator->generate($routeName, $parameters);
        $scheme = $this->urlGenerator->getContext()->getScheme();

        return $scheme.'://'.$channel->getDomain().$path;
    }

    private function buildUrlEntry(string $location, ?\DateTimeInterface $lastModified, string $changeFrequency, string $priority): string
    {
        $xml = '  <url>'."\n";
        $xml .= '    <loc>'.self::escapeXml($location).'</loc>'."\n";

        if (null !== $lastModified) {
            $xml .= '    <lastmod>'.$lastModified->format('Y-m-d').'</lastmod>'."\n";
        }

        $xml .= '    <changefreq>'.$changeFrequency.'</changefreq>'."\n";
        $xml .= '    <priority>'.$priority.'</priority>'."\n";
        $xml .= '  </url>'."\n";

        return $xml;
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }
}
