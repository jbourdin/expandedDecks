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
use App\Entity\User;
use App\Service\Channel\ChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds JSON-LD structured data arrays for public pages.
 *
 * Each method returns a plain array that can be rendered as
 * <script type="application/ld+json"> via json_encode in Twig.
 * The organization name is read from the current channel's
 * brand_name parameter. Contextual strings (genre, descriptions)
 * are translated via TranslatorInterface to match the page locale.
 *
 * @see docs/features.md F18.27 — JSON-LD structured data
 */
final readonly class StructuredDataBuilder
{
    public function __construct(
        private ChannelContext $channelContext,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
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
        $published = $page->getFirstPublishedAt();
        $modified = $page->getLastPublishedAt() ?? $published ?? $page->getUpdatedAt() ?? $page->getCreatedAt();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $translation->getTitle(),
            'url' => $url,
            'dateModified' => $modified->format('c'),
            'publisher' => $this->buildOrganization(),
        ];

        if ($published instanceof \DateTimeImmutable) {
            $data['datePublished'] = $published->format('c');
        }

        $author = $page->getAuthor();
        if ($author instanceof User) {
            $data['author'] = $this->buildPerson($author);
        }

        $translator = $translation->getTranslator();
        if ($translator instanceof User) {
            $data['translator'] = $this->buildPerson($translator);
        }

        return $data;
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
        $archetypeName = $archetype->getLocalizedName($locale);
        $genre = $this->translator->trans('app.seo.tcg_genre', locale: $locale);
        $author = $archetype->getAuthor();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'name' => $archetypeName,
            'headline' => $this->translator->trans('app.seo.archetype_headline', ['%name%' => $archetypeName], locale: $locale),
            'url' => $url,
            'genre' => $genre,
            'about' => [
                '@type' => 'Game',
                'name' => $this->translator->trans('app.seo.tcg_game_name', locale: $locale),
            ],
            'author' => $author instanceof User ? $this->buildPerson($author) : $this->buildOrganization(),
            'publisher' => $this->buildOrganization(),
        ];

        $translator = $archetype->getTranslation($locale)?->getTranslator();
        if ($translator instanceof User) {
            $data['translator'] = $this->buildPerson($translator);
        }

        $published = $archetype->getFirstPublishedAt() ?? $archetype->getCreatedAt();
        $modified = $archetype->getLastPublishedAt() ?? $archetype->getUpdatedAt() ?? $published;
        $data['datePublished'] = $published->format('c');
        $data['dateModified'] = $modified->format('c');

        $description = $archetype->getLocalizedMetaDescription($locale);
        if (null !== $description && '' !== $description) {
            $data['description'] = $description;
        }

        if ([] !== $variants) {
            $data['hasPart'] = array_map(
                fn (array $variant): array => [
                    '@type' => 'CreativeWork',
                    'name' => $variant['name'],
                    'url' => $variant['url'],
                    'genre' => $genre,
                    'description' => $this->translator->trans('app.seo.variant_description', ['%name%' => $variant['name']], locale: $locale),
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
            'genre' => $this->translator->trans('app.seo.tcg_genre'),
            'dateCreated' => $deck->getCreatedAt()->format('c'),
        ];

        $author = $deck->resolveAuthor();
        if ($author instanceof User) {
            $data['author'] = $this->buildPerson($author);
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
     * @return array<string, mixed>
     */
    private function buildOrganization(): array
    {
        $channel = $this->channelContext->getChannel();

        $organization = [
            '@type' => 'Organization',
            'name' => $this->getBrandName(),
            'url' => $this->getOrganizationUrl(),
        ];

        $logo = $channel->getParameter('org_logo');
        if ('' !== $logo) {
            $organization['logo'] = [
                '@type' => 'ImageObject',
                'url' => str_starts_with($logo, 'http') ? $logo : $this->getOrganizationUrl().$logo,
            ];
        }

        // org_same_as is a whitespace/comma-separated list of public profile URLs.
        $sameAsRaw = $channel->getParameter('org_same_as');
        if ('' !== $sameAsRaw) {
            $sameAs = array_values(array_filter(
                array_map(trim(...), preg_split('/[\s,]+/', $sameAsRaw) ?: []),
                static fn (string $url): bool => '' !== $url,
            ));
            if ([] !== $sameAs) {
                $organization['sameAs'] = $sameAs;
            }
        }

        return $organization;
    }

    /**
     * Build a schema.org Person from a user, exposing ONLY curated public
     * fields. It MUST NOT emit email, first name, or last name; the public
     * identity is the chosen screen name (F19.8).
     *
     * @return array<string, mixed>
     */
    private function buildPerson(User $user): array
    {
        $person = [
            '@type' => 'Person',
            'name' => $user->getScreenName(),
        ];

        // Rich profile signals are exposed only for opted-in public authors.
        if (!$user->isPublicAuthor()) {
            return $person;
        }

        if (null !== $user->getPrimaryUrl()) {
            $person['url'] = $user->getPrimaryUrl();
        }

        if (null !== $user->getCredential()) {
            $person['description'] = $user->getCredential();
        }

        if (null !== $user->getAvatarUrl()) {
            $person['image'] = $user->getAvatarUrl();
        }

        if ([] !== $user->getSameAs()) {
            $person['sameAs'] = $user->getSameAs();
        }

        return $person;
    }
}
