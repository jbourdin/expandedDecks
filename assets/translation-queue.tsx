/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F9.12 — Translation queue
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import AppMantineProvider from './components/AppMantineProvider';
import '@mantine/core/styles.css';
import TranslationQueue from './components/TranslationQueue';

const root = document.getElementById('translation-queue-root');
if (root) {
    const queueUrl = root.dataset.queueUrl ?? '';
    const baseUrl = root.dataset.baseUrl ?? '';
    const labels = JSON.parse(root.dataset.labels ?? '{}');

    createRoot(root).render(
        <AppMantineProvider>
            <TranslationQueue queueUrl={queueUrl} baseUrl={baseUrl} labels={labels} />
        </AppMantineProvider>,
    );
}
