/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shared card image gallery modal — swipeable viewer with prev/next navigation.
 *
 * @see docs/features.md F2.23 — Interactive card mosaic with responsive grid and image modal
 */

import React, { useCallback, useEffect, useRef } from 'react';
import { CloseButton, Modal, Text, UnstyledButton } from '@mantine/core';
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-react';

export interface FlatCard {
    cardName: string;
    quantity: number;
    imageUrl: string;
    detail?: string;
    detailColor?: string;
}

interface CardImageModalProps {
    opened: boolean;
    cards: FlatCard[];
    currentIndex: number;
    onClose: () => void;
    onNavigate: (index: number) => void;
}

const CardImageModal: React.FC<CardImageModalProps> = ({ opened, cards, currentIndex, onClose, onNavigate }) => {
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

    const title = `${card.quantity} \u00d7 ${card.cardName}${card.detail ? ` ${card.detail}` : ''}`;

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            withCloseButton={false}
            centered
            padding={0}
            size="auto"
            styles={{
                body: { padding: 0 },
                content: {
                    overflow: 'hidden',
                    // Fit viewport, cap at TCGdex native resolution (600×825)
                    maxWidth: 'min(600px, 90vw)',
                    maxHeight: 'min(865px, 95dvh)', // 825px image + ~40px header
                },
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 12px' }}>
                <Text size="sm" fw={500} c={card.detailColor} style={{ flex: 1 }}>{title}</Text>
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
                    style={{
                        maxWidth: '100%',
                        maxHeight: 'calc(min(865px, 95dvh) - 48px)', // viewport cap minus header
                        objectFit: 'contain',
                        borderRadius: 4,
                    }}
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

export default CardImageModal;
