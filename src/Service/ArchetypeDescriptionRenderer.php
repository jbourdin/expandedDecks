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

namespace App\Service;

use App\Repository\ArchetypeRepository;
use App\Repository\DeckCardRepository;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Twig\Runtime\ArchetypeSpriteRuntime;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Renders archetype descriptions: Markdown → HTML with custom tag expansion.
 *
 * Supported custom tags:
 * - [[archetype:slug]]   → link to archetype detail page with sprites
 * - [[deck:SHORTTAG]]    → link to deck show page with short tag badge
 * - [[card:SET-NUMBER]]  → card name with hover image preview
 *
 * @see docs/features.md F2.10 — Archetype detail page
 */
class ArchetypeDescriptionRenderer
{
    public function __construct(
        private readonly MarkdownRenderer $markdownRenderer,
        private readonly ArchetypeRepository $archetypeRepository,
        private readonly DeckCardRepository $deckCardRepository,
        private readonly TcgdexApiClient $tcgdexApiClient,
        private readonly ArchetypeSpriteRuntime $spriteRuntime,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Render a Markdown description with custom tag expansion.
     *
     * The full rendered output is cached for 1 hour, keyed by content hash.
     * Cache invalidates automatically when the description text changes.
     */
    public function render(string $description): string
    {
        $cacheKey = 'archetype_desc.rendered.'.md5($description);

        /** @var string $html */
        $html = $this->cache->get($cacheKey, function (ItemInterface $item) use ($description): string {
            $item->expiresAfter(3600);

            $rendered = $this->markdownRenderer->render($description);
            $rendered = $this->expandArchetypeTags($rendered);
            $rendered = $this->expandDeckTags($rendered);
            $rendered = $this->expandCardTags($rendered);

            return $rendered;
        });

        return $html;
    }

    private function expandArchetypeTags(string $html): string
    {
        return (string) preg_replace_callback(
            '/\[\[archetype:([a-z0-9-]+)\]\]/',
            function (array $matches): string {
                $slug = $matches[1];
                $archetype = $this->archetypeRepository->findOneBy(['slug' => $slug]);

                if (null === $archetype) {
                    return htmlspecialchars($matches[0], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
                }

                $sprites = $this->spriteRuntime->renderSprites($archetype);
                $name = htmlspecialchars($archetype->getName(), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

                if (!$archetype->isPublished()) {
                    return \sprintf('%s %s', $sprites, $name);
                }

                $url = $this->urlGenerator->generate('app_archetype_show', ['slug' => $slug]);

                return \sprintf('<a href="%s">%s %s</a>', htmlspecialchars($url, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'), $sprites, $name);
            },
            $html,
        );
    }

    private function expandDeckTags(string $html): string
    {
        return (string) preg_replace_callback(
            '/\[\[deck:([A-HJ-NP-Z0-9]{6})\]\]/',
            function (array $matches): string {
                $shortTag = $matches[1];
                $url = $this->urlGenerator->generate('app_deck_show', ['short_tag' => $shortTag]);

                return \sprintf(
                    '<a href="%s" class="badge bg-dark badge-short-id">%s</a>',
                    htmlspecialchars($url, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars($shortTag, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
                );
            },
            $html,
        );
    }

    private function expandCardTags(string $html): string
    {
        return (string) preg_replace_callback(
            '/\[\[card:([A-Za-z0-9-]+)\]\]/',
            function (array $matches): string {
                $reference = $matches[1];
                $cardData = $this->resolveCardData($reference);

                if (null === $cardData) {
                    return htmlspecialchars($matches[0], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
                }

                $escapedName = htmlspecialchars($cardData['name'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

                if (null !== $cardData['imageUrl']) {
                    return \sprintf(
                        '<span class="card-hover">%s<img class="card-hover-img" src="%s" alt="%s"></span>',
                        $escapedName,
                        htmlspecialchars($cardData['imageUrl'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
                        $escapedName,
                    );
                }

                return $escapedName;
            },
            $html,
        );
    }

    /**
     * Parse SET-NUMBER reference and resolve card name + image URL.
     *
     * The set code may contain hyphens (e.g. PR-SV), so we split from the right:
     * the last hyphen-separated segment is the card number.
     *
     * @return array{name: string, imageUrl: string|null}|null
     */
    private function resolveCardData(string $reference): ?array
    {
        /** @var array{name: string, imageUrl: string|null}|null $data */
        $data = $this->cache->get('archetype_desc.card.'.strtoupper($reference), function (ItemInterface $item) use ($reference): ?array {
            $item->expiresAfter(86400);

            $lastHyphen = strrpos($reference, '-');
            if (false === $lastHyphen) {
                return null;
            }

            $setCode = substr($reference, 0, $lastHyphen);
            $cardNumber = substr($reference, $lastHyphen + 1);

            // Try local DB first (enriched cards)
            $deckCard = $this->deckCardRepository->findOneBySetCodeAndCardNumber($setCode, $cardNumber);
            if (null !== $deckCard) {
                return [
                    'name' => $deckCard->getCardName(),
                    'imageUrl' => $deckCard->getImageUrl(),
                ];
            }

            // Fall back to TCGdex API
            $tcgdexCard = $this->tcgdexApiClient->findCard($setCode, $cardNumber);
            if (null !== $tcgdexCard) {
                return [
                    'name' => $tcgdexCard->name,
                    'imageUrl' => $tcgdexCard->imageUrl,
                ];
            }

            return null;
        });

        return $data;
    }
}
