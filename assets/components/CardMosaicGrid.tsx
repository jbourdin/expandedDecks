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

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { CloseButton, Modal, Text, UnstyledButton } from '@mantine/core';
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-react';

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

interface FlatCard {
    cardName: string;
    quantity: number;
    imageUrl: string;
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
 * Card image gallery modal — swipeable viewer with prev/next navigation.
 */
const CardImageModal: React.FC<{
    opened: boolean;
    cards: FlatCard[];
    currentIndex: number;
    onClose: () => void;
    onNavigate: (index: number) => void;
}> = ({ opened, cards, currentIndex, onClose, onNavigate }) => {
    const touchStartX = useRef(0);
    const touchStartY = useRef(0);

    const card = cards[currentIndex];

    const navigate = useCallback((direction: number) => {
        onNavigate((currentIndex + direction + cards.length) % cards.length);
    }, [currentIndex, cards.length, onNavigate]);

    useEffect(() => {
        if (!opened) return;

        const handleKeyDown = (event: KeyboardEvent): void => {
            if (event.key === 'ArrowLeft') navigate(-1);
            if (event.key === 'ArrowRight') navigate(1);
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [opened, navigate]);

    if (!card) return null;

    const title = `${card.quantity} \u00d7 ${card.cardName}`;

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            withCloseButton={false}
            centered
            size="sm"
            padding={0}
            styles={{
                body: { padding: 0 },
                content: { overflow: 'hidden' },
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 12px' }}>
                <Text size="sm" fw={500} style={{ flex: 1 }}>{title}</Text>
                <Text size="xs" c="dimmed" style={{ marginRight: 8 }}>
                    {currentIndex + 1} / {cards.length}
                </Text>
                <CloseButton onClick={onClose} />
            </div>
            <div
                style={{ position: 'relative', textAlign: 'center', padding: '0 8px 8px' }}
                onTouchStart={(event) => {
                    touchStartX.current = event.touches[0].clientX;
                    touchStartY.current = event.touches[0].clientY;
                }}
                onTouchMove={(event) => {
                    const deltaX = Math.abs(event.touches[0].clientX - touchStartX.current);
                    const deltaY = Math.abs(event.touches[0].clientY - touchStartY.current);
                    if (deltaY > deltaX) {
                        event.preventDefault();
                    }
                }}
                onTouchEnd={(event) => {
                    const deltaX = event.changedTouches[0].clientX - touchStartX.current;
                    if (Math.abs(deltaX) > 50) {
                        navigate(deltaX < 0 ? 1 : -1);
                    }
                }}
            >
                <UnstyledButton
                    onClick={() => navigate(-1)}
                    style={{ position: 'absolute', left: 4, top: '50%', transform: 'translateY(-50%)', zIndex: 1 }}
                >
                    <IconChevronLeft size={28} />
                </UnstyledButton>
                <img
                    src={card.imageUrl}
                    alt={card.cardName}
                    style={{ maxWidth: '100%', borderRadius: 4 }}
                />
                <UnstyledButton
                    onClick={() => navigate(1)}
                    style={{ position: 'absolute', right: 4, top: '50%', transform: 'translateY(-50%)', zIndex: 1 }}
                >
                    <IconChevronRight size={28} />
                </UnstyledButton>
            </div>
        </Modal>
    );
};

/**
 * Interactive card mosaic grid — responsive 6-col (desktop) / 3-col (mobile)
 * grid of low-res card thumbnails with quantity badges and click-to-zoom modal.
 */
export default function CardMosaicGrid({ groupedCards, mosaicAltLabel }: CardMosaicGridProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [modalIndex, setModalIndex] = useState(0);

    const flatCards: FlatCard[] = useMemo(() => {
        const entries: FlatCard[] = [];

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
                        <span className="card-mosaic-qty">{card.quantity}</span>
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
