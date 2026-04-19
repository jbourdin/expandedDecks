/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F2.23 — Interactive card mosaic with responsive grid and image modal
 */

import React, { useCallback, useMemo, useState } from 'react';
import CardImageModal, { type FlatCard } from './CardImageModal';

interface CardData {
    cardName: string;
    quantity: number;
    setCode: string;
    cardNumber: string;
    cardType: string;
    trainerSubtype: string | null;
    imageUrl: string | null;
}

interface CardMosaicGridProps {
    groupedCards: Record<string, CardData[]>;
    mosaicAltLabel: string;
}

interface MosaicCard extends FlatCard {
    lowResUrl: string;
}

const SECTION_ORDER = ['pokemon', 'trainer', 'energy'];

/**
 * Derive the low-resolution thumbnail URL from the stored high-resolution URL.
 *
 * TCGdex CDN serves both resolutions at the same path — swap the suffix.
 * For non-TCGdex URLs (PokemonTCG.io, etc.), return the original URL as-is.
 */
function toLowRes(imageUrl: string): string {
    if (imageUrl.includes('/high.webp')) {
        return imageUrl.replace('/high.webp', '/low.webp');
    }

    return imageUrl;
}

/**
 * Interactive card mosaic grid — responsive 8-col (desktop) / 4-col (mobile)
 * grid of low-res card thumbnails with quantity badges and click-to-zoom modal.
 */
export default function CardMosaicGrid({ groupedCards, mosaicAltLabel }: CardMosaicGridProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [modalIndex, setModalIndex] = useState(0);

    const flatCards: MosaicCard[] = useMemo(() => {
        const entries: MosaicCard[] = [];

        for (const section of SECTION_ORDER) {
            for (const card of groupedCards[section] ?? []) {
                if (card.imageUrl) {
                    entries.push({
                        cardName: card.cardName,
                        quantity: card.quantity,
                        imageUrl: card.imageUrl,
                        lowResUrl: toLowRes(card.imageUrl),
                    });
                }
            }
        }

        return entries;
    }, [groupedCards]);

    const handleCardClick = useCallback((index: number) => {
        setModalIndex(index);
        setModalOpen(true);
    }, []);

    if (flatCards.length === 0) {
        return null;
    }

    return (
        <>
            <div className="card-mosaic-grid" role="grid" aria-label={mosaicAltLabel}>
                {flatCards.map((card, index) => (
                    <button
                        key={`${card.cardName}-${index}`}
                        type="button"
                        className="card-mosaic-cell"
                        onClick={() => handleCardClick(index)}
                        aria-label={`${card.quantity} \u00d7 ${card.cardName}`}
                    >
                        <img
                            src={card.lowResUrl}
                            alt={card.cardName}
                            loading="lazy"
                        />
                        <span className="card-mosaic-qty">
                            <span className="card-mosaic-qty-inner">{card.quantity}</span>
                        </span>
                    </button>
                ))}
            </div>

            <CardImageModal
                opened={modalOpen}
                cards={flatCards}
                currentIndex={modalIndex}
                onClose={() => setModalOpen(false)}
                onNavigate={setModalIndex}
            />
        </>
    );
}
