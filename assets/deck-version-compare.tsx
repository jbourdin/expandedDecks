/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { MantineProvider } from '@mantine/core';
import DeckVersionCompare from './components/DeckVersionCompare';

import '@mantine/core/styles.css';

/**
 * @see docs/features.md F2.9 — Deck version history
 */

interface VersionInfo {
    versionNumber: number;
    createdAt: string;
    cardCount: number;
}

document.querySelectorAll<HTMLElement>('[data-version-compare]').forEach((root) => {
    const shortTag = root.dataset.shortTag ?? '';
    const versions = JSON.parse(root.dataset.versions ?? '[]') as VersionInfo[];
    const labels = {
        from: root.dataset.labelFrom ?? 'From version',
        to: root.dataset.labelTo ?? 'To version',
        added: root.dataset.labelAdded ?? 'Added',
        removed: root.dataset.labelRemoved ?? 'Removed',
        changed: root.dataset.labelChanged ?? 'Changed',
        unchanged: root.dataset.labelUnchanged ?? 'Unchanged',
        showUnchanged: root.dataset.labelShowUnchanged ?? 'Show unchanged cards',
        noChanges: root.dataset.labelNoChanges ?? 'These versions are identical.',
        card: root.dataset.labelCard ?? 'Card',
        set: root.dataset.labelSet ?? 'Set',
        qty: root.dataset.labelQty ?? 'Qty',
        change: root.dataset.labelChange ?? 'Change',
    };

    createRoot(root).render(
        <MantineProvider>
            <DeckVersionCompare shortTag={shortTag} versions={versions} labels={labels} />
        </MantineProvider>,
    );
});
