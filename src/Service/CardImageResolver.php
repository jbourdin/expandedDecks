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

use App\Entity\CardPrinting;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves card images with a fallback chain for unreliable CDN URLs.
 *
 * When the primary image URL (TCGdex CDN) fails, tries alternative sources
 * and updates the CardPrinting so subsequent requests use the working URL.
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
class CardImageResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Download the card image, trying fallbacks on failure.
     *
     * Fallback chain:
     * 1. Primary URL from CardPrinting.imageUrl
     * 2. Alternative CDN URLs (TCGdex variants, PokemonTCG.io)
     * 3. Image from a sibling printing of the same CardIdentity
     *
     * If a fallback URL succeeds, updates CardPrinting.imageUrl in the database
     * so subsequent calls use the working URL directly.
     *
     * @return string|false the image data on success, false on failure
     */
    /**
     * @param string $resolution 'high' for full resolution, 'low' for thumbnails
     */
    public function downloadImage(CardPrinting $printing, string $resolution = 'high'): string|false
    {
        $primaryUrl = $printing->getImageUrl();

        if ('low' === $resolution && null !== $primaryUrl) {
            $primaryUrl = str_replace('/high.webp', '/low.webp', $primaryUrl);
        }
        $cardName = $printing->getCardIdentity()->getName();

        // Try primary URL
        if (null !== $primaryUrl && '' !== $primaryUrl) {
            $imageData = @file_get_contents($primaryUrl);

            if (false !== $imageData) {
                return $imageData;
            }

            $this->logger->info('Primary image URL failed for "{card}", trying fallbacks.', [
                'card' => $cardName,
                'url' => $primaryUrl,
            ]);
        }

        // Build fallback URLs from the TCGdex card link
        $fallbackUrls = $this->buildFallbackUrls($printing);

        foreach ($fallbackUrls as $fallbackUrl) {
            $imageData = @file_get_contents($fallbackUrl);

            if (false !== $imageData) {
                return $this->persistFallbackUrl($printing, $fallbackUrl, $cardName, $imageData);
            }
        }

        // Final fallback: try a sibling printing of the same card
        $siblingImageData = $this->tryFromSiblingPrinting($printing);

        if (false !== $siblingImageData) {
            return $siblingImageData;
        }

        $this->logger->warning('All image URLs failed for "{card}".', [
            'card' => $cardName,
            'primaryUrl' => $primaryUrl,
        ]);

        return false;
    }

    /**
     * Persist a working fallback URL and return the image data.
     */
    private function persistFallbackUrl(CardPrinting $printing, string $url, string $cardName, string $imageData): string
    {
        $printing->setImageUrl($url);
        $this->entityManager->flush();

        $this->logger->info('Fallback image URL succeeded for "{card}".', [
            'card' => $cardName,
            'url' => $url,
        ]);

        return $imageData;
    }

    /**
     * Try to download an image from a sibling printing of the same CardIdentity.
     *
     * When all CDN fallbacks fail for a specific printing, another printing of the
     * same card (e.g. a different set release) may have a working image. If found,
     * the working URL is persisted on the current printing.
     *
     * @return string|false the image data on success, false on failure
     */
    private function tryFromSiblingPrinting(CardPrinting $printing): string|false
    {
        $identity = $printing->getCardIdentity();
        $cardName = $identity->getName();

        foreach ($identity->getPrintings() as $sibling) {
            if ($sibling === $printing) {
                continue;
            }

            $siblingUrl = $sibling->getImageUrl();

            if (null === $siblingUrl || '' === $siblingUrl) {
                continue;
            }

            $imageData = @file_get_contents($siblingUrl);

            if (false !== $imageData) {
                return $this->persistFallbackUrl($printing, $siblingUrl, $cardName, $imageData);
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function buildFallbackUrls(CardPrinting $printing): array
    {
        $tcgdexCard = $printing->getTcgdexCard();
        $urls = [];

        if (null !== $tcgdexCard) {
            $setId = $tcgdexCard->getSet()->getId();
            $serieId = $tcgdexCard->getSet()->getSerie()->getId();
            $localId = $tcgdexCard->getLocalId();

            // Fallback 1: TCGdex CDN with dots removed from set ID (works for SM sets)
            if (str_contains($setId, '.')) {
                $setIdNoDots = str_replace('.', '', $setId);
                $urls[] = \sprintf('https://assets.tcgdex.net/en/%s/%s/%s/high.webp', $serieId, $setIdNoDots, $localId);
            }

            // Fallback 2: PokemonTCG.io CDN
            $pokemontcgSetId = str_replace('.', '', $setId);
            $urls[] = \sprintf('https://images.pokemontcg.io/%s/%s_hires.png', $pokemontcgSetId, $localId);
        } else {
            // No tcgdex_card link — try to derive from tcgdex_id
            $tcgdexId = $printing->getTcgdexId();

            if (str_contains($tcgdexId, '-')) {
                $dashPos = (int) strpos($tcgdexId, '-');
                $setId = substr($tcgdexId, 0, $dashPos);
                $localId = substr($tcgdexId, $dashPos + 1);

                $pokemontcgSetId = str_replace('.', '', $setId);
                $urls[] = \sprintf('https://images.pokemontcg.io/%s/%s_hires.png', $pokemontcgSetId, $localId);
            }
        }

        return $urls;
    }
}
