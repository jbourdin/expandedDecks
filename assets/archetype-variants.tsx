/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F18.16 — Archetype detail: variant selector
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { MantineProvider } from '@mantine/core';
import '@mantine/core/styles.css';
import ArchetypeVariantSelector from './components/ArchetypeVariantSelector';

const root = document.getElementById('archetype-variant-selector-root');
if (root) {
    const variants = JSON.parse(root.dataset.variants ?? '[]');
    const labels = {
        viewTable: root.dataset.labelViewTable ?? 'Table',
        viewMosaic: root.dataset.labelViewMosaic ?? 'Mosaic',
        mosaicAlt: root.dataset.labelMosaicAlt ?? 'Deck mosaic',
        sectionPokemon: root.dataset.labelSectionPokemon ?? 'Pokemon',
        sectionTrainer: root.dataset.labelSectionTrainer ?? 'Trainer',
        sectionEnergy: root.dataset.labelSectionEnergy ?? 'Energy',
        tableQty: root.dataset.labelTableQty ?? 'Qty',
        tableCard: root.dataset.labelTableCard ?? 'Card',
        tableSet: root.dataset.labelTableSet ?? 'Set',
        moreVariants: root.dataset.labelMoreVariants ?? 'More variants\u2026',
        copyList: root.dataset.labelCopyList ?? 'Copy list',
        copied: root.dataset.labelCopied ?? 'Copied!',
        outdatedBadge: root.dataset.labelOutdatedBadge ?? 'Outdated variant',
        groupCurrent: root.dataset.labelGroupCurrent ?? 'Current',
        groupOutdated: root.dataset.labelGroupOutdated ?? 'Outdated',
        shareMosaic: root.dataset.labelShareMosaic ?? 'Share mosaic',
        enrichmentPending: root.dataset.labelEnrichmentPending ?? 'Card data is being generated…',
    };

    createRoot(root).render(
        <MantineProvider>
            <ArchetypeVariantSelector variants={variants} labels={labels} />
        </MantineProvider>,
    );
}
