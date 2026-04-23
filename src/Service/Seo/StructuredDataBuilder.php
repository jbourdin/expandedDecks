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

namespace App\Service\Seo;

use App\Entity\Archetype;
use App\Entity\Deck;
use App\Entity\Event;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Service\Channel\ChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds JSON-LD structured data arrays for public pages.
 *
 * Each method returns a plain array that can be rendered as
 * <script type="application/ld+json"> via json_encode in Twig.
 * The organization name is read from the current channel's
 * brand_name parameter.
 *
 * @see docs/features.md F18.27 — JSON-LD structured data
 */
final readonly class StructuredDataBuilder
{
    private const TCG_GENRE = 'Pokémon TCG Expanded';

    /** @var array<string, string> */
    private const TCG_GAME = ['@type' => 'Game', 'name' => 'Pokémon Trading Card Game'];

    public function __construct(
        private ChannelContext $channelContext,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @see docs/features.md F18.27 — JSON-LD structured data
     *
     * @return array<string, mixed>
     */
    public function buildWebSite(string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $this->getBrandName(),
            'url' => $url,
        ];
    }

    /**
     * @see docs/features.md F18.27 — JSON-LD structured data
     *
     * @return array<string, mixed>
     */
    public function buildWebPage(PageTranslation $translation, Page $page, string $url): array
    {
        $lastModified = $page->getUpdatedAt() ?? $page->getCreatedAt();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $translation->getTitle(),
            'url' => $url,
            'dateModified' => $lastModified->format('c'),
            'publisher' => $this->buildOrganization(),
        ];
    }

    /**
     * @see docs/features.md F18.27 — JSON-LD structured data
     *
     * @param list<array{name: string, url: string}> $variants deck variants with name and anchor URL
     *
     * @return array<string, mixed>
     */
    public function buildArticle(Archetype $archetype, string $locale, string $url, array $variants = []): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'name' => $archetype->getLocalizedName($locale),
            'url' => $url,
            'genre' => self::TCG_GENRE,
            'about' => self::TCG_GAME,
            'author' => $this->buildOrganization(),
            'publisher' => $this->buildOrganization(),
        ];

        $lastModified = $archetype->getUpdatedAt() ?? $archetype->getCreatedAt();
        $data['dateModified'] = $lastModified->format('c');
        $data['datePublished'] = $archetype->getCreatedAt()->format('c');

        $description = $archetype->getLocalizedMetaDescription($locale);
        if (null !== $description && '' !== $description) {
            $data['description'] = $description;
        }

        if ([] !== $variants) {
            $data['hasPart'] = array_map(
                static fn (array $variant): array => [
                    '@type' => 'CreativeWork',
                    'name' => $variant['name'],
                    'url' => $variant['url'],
                    'genre' => self::TCG_GENRE,
                ],
                $variants,
            );
        }

        return $data;
    }

    /**
     * @see docs/features.md F18.27 — JSON-LD structured data
     *
     * @return array<string, mixed>
     */
    public function buildEvent(Event $event, string $url): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->getName(),
            'startDate' => $event->getDate()->format('c'),
            'url' => $url,
            'organizer' => [
                '@type' => 'Person',
                'name' => $event->getOrganizer()->getScreenName(),
            ],
            'eventStatus' => 'https://schema.org/EventScheduled',
        ];

        if (null !== $event->getEndDate()) {
            $data['endDate'] = $event->getEndDate()->format('c');
        }

        if (null !== $event->getLocation() && '' !== $event->getLocation()) {
            $data['location'] = [
                '@type' => 'Place',
                'name' => $event->getLocation(),
            ];
        }

        if (null !== $event->getDescription() && '' !== $event->getDescription()) {
            $data['description'] = $event->getDescription();
        }

        if (null !== $event->getCancelledAt()) {
            $data['eventStatus'] = 'https://schema.org/EventCancelled';
        }

        return $data;
    }

    /**
     * @see docs/features.md F18.27 — JSON-LD structured data
     *
     * @return array<string, mixed>
     */
    public function buildCreativeWork(Deck $deck, string $url): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $deck->getName(),
            'url' => $url,
            'genre' => self::TCG_GENRE,
            'dateCreated' => $deck->getCreatedAt()->format('c'),
        ];

        if (null !== $deck->getOwner()) {
            $data['author'] = [
                '@type' => 'Person',
                'name' => $deck->getOwner()->getScreenName(),
            ];
        }

        $lastModified = $deck->getUpdatedAt() ?? $deck->getCreatedAt();
        $data['dateModified'] = $lastModified->format('c');

        return $data;
    }

    /**
     * Build a CollectionPage with an ItemList for catalog/listing pages.
     *
     * @see docs/features.md F18.27 — JSON-LD structured data
     *
     * @param list<array{name: string, url: string}> $items ordered list of items with name and absolute URL
     *
     * @return array<string, mixed>
     */
    public function buildCollectionPage(string $name, string $url, array $items): array
    {
        $listItems = [];
        foreach ($items as $position => $item) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $item['name'],
                'url' => $item['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $url,
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $listItems,
            ],
        ];
    }

    private function getBrandName(): string
    {
        return $this->channelContext->getChannel()->getParameter('brand_name', 'Expanded Decks');
    }

    private function getOrganizationUrl(): string
    {
        $channel = $this->channelContext->getChannel();
        $scheme = $this->urlGenerator->getContext()->getScheme();

        return $scheme.'://'.$channel->getDomain();
    }

    /**
     * @return array<string, string>
     */
    private function buildOrganization(): array
    {
        return [
            '@type' => 'Organization',
            'name' => $this->getBrandName(),
            'url' => $this->getOrganizationUrl(),
        ];
    }
}
