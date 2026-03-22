/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 * @see docs/features.md F6.7 — Export deck list as PTCGL text
 * @see docs/features.md F6.8 — Minified deck list export
 * @see docs/features.md F6.11 — Export deck list for Cardmarket wishlist
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { MantineProvider } from '@mantine/core';
import { DeckCardList, type DeckCardListProps } from './components/DeckCardList';
import { initCardHover } from './shared/card-hover';

import '@mantine/core/styles.css';

const root = document.getElementById('deck-card-list-root');

if (root) {
    const originalCards = JSON.parse(root.dataset.originalCards ?? '{}') as DeckCardListProps['originalCards'];
    const minifiedCards = JSON.parse(root.dataset.minifiedCards ?? '{}') as DeckCardListProps['minifiedCards'];

    const labels = {
        variantOriginal: root.dataset.labelVariantOriginal ?? 'Original',
        variantMinified: root.dataset.labelVariantMinified ?? 'Minified',
        viewTable: root.dataset.labelViewTable ?? 'Table',
        viewMosaic: root.dataset.labelViewMosaic ?? 'Mosaic',
        copyButton: root.dataset.labelCopyButton ?? 'Copy list',
        copied: root.dataset.labelCopied ?? 'Copied!',
        shareMosaic: root.dataset.labelShareMosaic ?? 'Share mosaic',
        mosaicAlt: root.dataset.labelMosaicAlt ?? 'Card mosaic',
        sectionPokemon: root.dataset.labelSectionPokemon ?? 'Pokémon',
        sectionTrainer: root.dataset.labelSectionTrainer ?? 'Trainer',
        sectionEnergy: root.dataset.labelSectionEnergy ?? 'Energy',
        tableQty: root.dataset.labelTableQty ?? 'Qty',
        tableCard: root.dataset.labelTableCard ?? 'Card',
        tableSet: root.dataset.labelTableSet ?? 'Set',
        nameMatchWarningTitle: root.dataset.labelNameMatchWarningTitle ?? '',
        nameMatchWarningBody: root.dataset.labelNameMatchWarningBody ?? '',
        copyCardmarket: root.dataset.labelCopyCardmarket ?? 'Copy for Cardmarket',
        copyCardmarketTooltip: root.dataset.labelCopyCardmarketTooltip ?? 'Copy card list for Cardmarket wishlist import (basic energies excluded)',
    };

    createRoot(root).render(
        <MantineProvider>
            <DeckCardList
                originalCards={originalCards}
                minifiedCards={minifiedCards}
                mosaicUrl={root.dataset.mosaicUrl ?? null}
                minifiedMosaicUrl={root.dataset.minifiedMosaicUrl ?? null}
                rawList={root.dataset.rawList ?? ''}
                minifiedList={root.dataset.minifiedList ?? null}
                cardmarketWishlist={root.dataset.cardmarketWishlist || null}
                deckName={root.dataset.deckName ?? ''}
                labels={labels}
            />
        </MantineProvider>,
    );
}

// Card hover for other parts of the page (borrow activity, etc.)
initCardHover();
