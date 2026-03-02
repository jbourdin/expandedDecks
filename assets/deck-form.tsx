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
import ArchetypeSelect from './components/ArchetypeSelect';
import LanguageSelect from './components/LanguageSelect';

import '@mantine/core/styles.css';

/**
 * @see docs/features.md F2.1 — Register a new deck (owner)
 * @see docs/features.md F2.6 — Archetype management (create, browse, detail)
 */

const archetypeRoot = document.getElementById('archetype-select-root');
if (archetypeRoot) {
    const searchUrl = archetypeRoot.dataset.searchUrl ?? '';
    const createUrl = archetypeRoot.dataset.createUrl ?? '';
    const initialId = archetypeRoot.dataset.archetypeId
        ? parseInt(archetypeRoot.dataset.archetypeId, 10)
        : undefined;
    const initialName = archetypeRoot.dataset.archetypeName ?? undefined;

    createRoot(archetypeRoot).render(
        <MantineProvider>
            <ArchetypeSelect
                searchUrl={searchUrl}
                createUrl={createUrl}
                initialId={initialId}
                initialName={initialName}
                hiddenInputName="deck_form[archetype]"
            />
        </MantineProvider>,
    );
}

const languageRoot = document.getElementById('language-select-root');
if (languageRoot) {
    let initialLanguages: string[] = [];
    try {
        const raw = languageRoot.dataset.languages;
        if (raw) {
            initialLanguages = JSON.parse(raw);
        }
    } catch {
        // Invalid JSON — use empty array
    }

    createRoot(languageRoot).render(
        <MantineProvider>
            <LanguageSelect
                initialLanguages={initialLanguages}
                hiddenInputName="deck_form[languages]"
            />
        </MantineProvider>,
    );
}
