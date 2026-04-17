/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mounts a CardImageModal on the variant compare page, triggered by clicking
 * card names in the server-rendered diff table.
 */

import React, { useCallback, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { MantineProvider } from '@mantine/core';
import '@mantine/core/styles.css';
import CardImageModal, { type FlatCard } from './components/CardImageModal';

function VariantCompareModal({ cards }: { cards: FlatCard[] }) {
    const [opened, setOpened] = useState(false);
    const [currentIndex, setCurrentIndex] = useState(0);

    const handleOpen = useCallback((event: Event) => {
        const detail = (event as CustomEvent<{ cardName: string; imageUrl: string }>).detail;
        const index = cards.findIndex((card) => card.cardName === detail.cardName && card.imageUrl === detail.imageUrl);
        if (index >= 0) {
            setCurrentIndex(index);
            setOpened(true);
        }
    }, [cards]);

    useEffect(() => {
        document.addEventListener('open-card-modal', handleOpen);

        return () => document.removeEventListener('open-card-modal', handleOpen);
    }, [handleOpen]);

    return (
        <CardImageModal
            opened={opened}
            cards={cards}
            currentIndex={currentIndex}
            onClose={() => setOpened(false)}
            onNavigate={setCurrentIndex}
        />
    );
}

const root = document.getElementById('variant-compare-modal-root');
if (root) {
    const cards = JSON.parse(root.dataset.cards ?? '[]') as FlatCard[];

    createRoot(root).render(
        <MantineProvider>
            <VariantCompareModal cards={cards} />
        </MantineProvider>,
    );

    // Wire up click handlers on card names to dispatch custom event
    document.querySelectorAll<HTMLElement>('.card-modal-trigger').forEach((element) => {
        element.addEventListener('click', (event) => {
            event.stopPropagation();
            document.dispatchEvent(new CustomEvent('open-card-modal', {
                detail: {
                    cardName: element.dataset.cardName ?? '',
                    imageUrl: element.dataset.cardImage ?? '',
                },
            }));
        });
    });
}
