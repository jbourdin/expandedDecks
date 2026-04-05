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
     * If a fallback URL succeeds, updates CardPrinting.imageUrl in the database
     * so subsequent calls use the working URL directly.
     *
     * @return string|false the image data on success, false on failure
     */
    public function downloadImage(CardPrinting $printing): string|false
    {
        $primaryUrl = $printing->getImageUrl();

        if (null === $primaryUrl || '' === $primaryUrl) {
            return false;
        }

        // Try primary URL
        $imageData = @file_get_contents($primaryUrl);

        if (false !== $imageData) {
            return $imageData;
        }

        $this->logger->info('Primary image URL failed for "{card}", trying fallbacks.', [
            'card' => $printing->getCardIdentity()->getName(),
            'url' => $primaryUrl,
        ]);

        // Build fallback URLs from the TCGdex card link
        $fallbackUrls = $this->buildFallbackUrls($printing);

        foreach ($fallbackUrls as $fallbackUrl) {
            $imageData = @file_get_contents($fallbackUrl);

            if (false !== $imageData) {
                // Update the stored URL so we don't retry next time
                $printing->setImageUrl($fallbackUrl);
                $this->entityManager->flush();

                $this->logger->info('Fallback image URL succeeded for "{card}".', [
                    'card' => $printing->getCardIdentity()->getName(),
                    'url' => $fallbackUrl,
                ]);

                return $imageData;
            }
        }

        $this->logger->warning('All image URLs failed for "{card}".', [
            'card' => $printing->getCardIdentity()->getName(),
            'primaryUrl' => $primaryUrl,
        ]);

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
