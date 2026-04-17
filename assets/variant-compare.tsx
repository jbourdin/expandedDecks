/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F2.10 — Archetype detail page
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { MantineProvider } from '@mantine/core';
import '@mantine/core/styles.css';
import VariantComparePicker from './components/VariantComparePicker';

const root = document.getElementById('variant-compare-picker-root');
if (root) {
    const variants = JSON.parse(root.dataset.variants ?? '[]');

    createRoot(root).render(
        <MantineProvider>
            <VariantComparePicker
                variants={variants}
                selectedTagA={root.dataset.selectedTagA ?? ''}
                selectedTagB={root.dataset.selectedTagB ?? ''}
                archetypeSlug={root.dataset.archetypeSlug ?? ''}
                labelFrom={root.dataset.labelFrom ?? 'First variant'}
                labelTo={root.dataset.labelTo ?? 'Second variant'}
                labelGroupCurrent={root.dataset.labelGroupCurrent ?? 'Current'}
                labelGroupOutdated={root.dataset.labelGroupOutdated ?? 'Outdated'}
            />
        </MantineProvider>,
    );
}
