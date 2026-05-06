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
import AppMantineProvider from './components/AppMantineProvider';
import { DeckCardList, type DeckCardListProps } from './components/DeckCardList';

import '@mantine/core/styles.css';

const root = document.getElementById('deck-card-list-root');

if (root) {
    const originalCards = JSON.parse(root.dataset.originalCards ?? '{}') as DeckCardListProps['originalCards'];
    const minifiedCards = JSON.parse(root.dataset.minifiedCards ?? '{}') as DeckCardListProps['minifiedCards'];

    const mosaicUrl = root.dataset.mosaicUrlNull === '1' ? null : (root.dataset.mosaicUrl || null);
    const minifiedMosaicUrl = root.dataset.minifiedMosaicUrlNull === '1' ? null : (root.dataset.minifiedMosaicUrl || null);
    const minifiedList = root.dataset.minifiedListNull === '1' ? null : (root.dataset.minifiedList || null);

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
        mosaicGenerating: root.dataset.labelMosaicGenerating ?? 'The visual mosaic is being generated…',
        minifiedGenerating: root.dataset.labelMinifiedGenerating ?? 'The minified list is being generated…',
    };

    createRoot(root).render(
        <AppMantineProvider>
            <DeckCardList
                originalCards={originalCards}
                minifiedCards={minifiedCards}
                mosaicUrl={mosaicUrl}
                minifiedMosaicUrl={minifiedMosaicUrl}
                rawList={root.dataset.rawList ?? ''}
                minifiedList={minifiedList}
                cardmarketWishlist={root.dataset.cardmarketWishlist || null}
                deckName={root.dataset.deckName ?? ''}
                labels={labels}
            />
        </AppMantineProvider>,
    );
}
