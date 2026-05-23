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
use App\Repository\DeckRepository;
use App\Service\Tcgdex\TcgdexApiClient;
use App\Twig\Runtime\ArchetypeSpriteRuntime;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Renders archetype descriptions: Markdown → HTML with custom tag expansion.
 *
 * Supported custom tags:
 * - [[archetype:slug]]            → link to archetype detail page with sprites
 * - [[archetype:slug:shortTag]]  → link to archetype variant with variant sprites and name
 * - [[deck:SHORTTAG]]            → link to deck show page with short tag badge
 * - [[card:SET-NUMBER]]          → card name with hover image preview
 *
 * @see docs/features.md F2.10 — Archetype detail page
 * @see docs/features.md F2.25 — Archetype variant URL anchors & enhanced archetype tags
 */
class ArchetypeDescriptionRenderer
{
    public function __construct(
        private readonly MarkdownRenderer $markdownRenderer,
        private readonly ArchetypeRepository $archetypeRepository,
        private readonly DeckCardRepository $deckCardRepository,
        private readonly DeckRepository $deckRepository,
        private readonly TcgdexApiClient $tcgdexApiClient,
        private readonly ArchetypeSpriteRuntime $spriteRuntime,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Render a Markdown description with custom tag expansion.
     *
     * The full rendered output is cached for 1 hour, keyed by content hash and locale.
     * Cache invalidates automatically when the description text changes.
     *
     * @see docs/features.md F9.6 — Archetype localization
     */
    public function render(string $description, string $locale = 'en'): string
    {
        $cacheKey = 'archetype_desc.rendered.'.$locale.'.'.md5($description);

        /** @var string $html */
        $html = $this->cache->get($cacheKey, function (ItemInterface $item) use ($description, $locale): string {
            $item->expiresAfter(3600);

            $rendered = $this->markdownRenderer->render($description);
            $rendered = $this->expandArchetypeTags($rendered, $locale);
            $rendered = $this->expandDeckTags($rendered);
            $rendered = $this->expandCardTags($rendered);

            return $rendered;
        });

        return $html;
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     * @see docs/features.md F2.25 — Archetype variant URL anchors & enhanced archetype tags
     */
    private function expandArchetypeTags(string $html, string $locale): string
    {
        return (string) preg_replace_callback(
            '/\[\[archetype:([a-z0-9-]+)(?::([A-HJ-NP-Z0-9]{6}))?\]\]/',
            function (array $matches) use ($locale): string {
                $slug = $matches[1];
                $variantShortTag = $matches[2] ?? null;
                $archetype = $this->archetypeRepository->findOneBy(['slug' => $slug]);

                if (null === $archetype) {
                    return htmlspecialchars($matches[0], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
                }

                // Resolve variant-specific display when a shortTag is provided.
                $variant = null;
                if (null !== $variantShortTag) {
                    $variant = $this->deckRepository->findOneBy(['shortTag' => $variantShortTag]);

                    // Ignore the variant if it doesn't belong to this archetype.
                    if (null !== $variant && $variant->getArchetype() !== $archetype) {
                        $variant = null;
                    }
                }

                if (null !== $variant) {
                    $sprites = $this->spriteRuntime->renderDeckSprites($variant, 'inline');
                    $name = htmlspecialchars($variant->getName(), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
                } else {
                    $sprites = $this->spriteRuntime->renderSprites($archetype, 'inline');
                    $name = htmlspecialchars($archetype->getLocalizedName($locale), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
                }

                if (!$archetype->isPublished()) {
                    return \sprintf('%s %s', $sprites, $name);
                }

                $url = $this->urlGenerator->generate('app_archetype_show', ['slug' => $slug]);
                if (null !== $variant && null !== $variantShortTag) {
                    $url .= '#'.urlencode($variantShortTag);
                }

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
            $lastHyphen = strrpos($reference, '-');
            if (false === $lastHyphen) {
                $item->expiresAfter(300);

                return null;
            }

            $setCode = substr($reference, 0, $lastHyphen);
            $cardNumber = substr($reference, $lastHyphen + 1);

            // Try local DB first (enriched cards)
            $deckCard = $this->deckCardRepository->findOneBySetCodeAndCardNumber($setCode, $cardNumber);
            if (null !== $deckCard) {
                $item->expiresAfter(null !== $deckCard->getImageUrl() ? 86400 : 300);

                return [
                    'name' => $deckCard->getCardName(),
                    'imageUrl' => $deckCard->getImageUrl(),
                ];
            }

            // Fall back to TCGdex API
            $tcgdexCard = $this->tcgdexApiClient->findCard($setCode, $cardNumber);
            if (null !== $tcgdexCard) {
                $item->expiresAfter(null !== $tcgdexCard->imageUrl ? 86400 : 300);

                return [
                    'name' => $tcgdexCard->name,
                    'imageUrl' => $tcgdexCard->imageUrl,
                ];
            }

            // Card not found — cache briefly so we retry soon
            $item->expiresAfter(300);

            return null;
        });

        return $data;
    }
}
