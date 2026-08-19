/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import AppMantineProvider from './components/AppMantineProvider';
import '@mantine/core/styles.css';
import '@mantine/tiptap/styles.css';
import TranslationEditor from './components/TranslationEditor';

const root = document.getElementById('translation-editor-root');
if (root) {
    const sections = JSON.parse(root.dataset.sections ?? '[]');
    const locale = root.dataset.locale ?? 'fr';
    const baseUrl = root.dataset.baseUrl ?? '';
    const canTranslate = root.dataset.canTranslate === 'true';
    const canModerate = root.dataset.canModerate === 'true';
    const labels = JSON.parse(root.dataset.labels ?? '{}');

    createRoot(root).render(
        <AppMantineProvider>
            <TranslationEditor
                sections={sections}
                locale={locale}
                baseUrl={baseUrl}
                canTranslate={canTranslate}
                canModerate={canModerate}
                labels={labels}
            />
        </AppMantineProvider>,
    );
}
