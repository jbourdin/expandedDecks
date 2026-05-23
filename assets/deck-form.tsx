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
import AppMantineProvider from './components/AppMantineProvider';
import ArchetypeSelect from './components/ArchetypeSelect';
import LanguageSelect from './components/LanguageSelect';
import PokemonSpriteSelect from './components/PokemonSpriteSelect';

import '@mantine/core/styles.css';

/**
 * @see docs/features.md F2.1 — Register a new deck (owner)
 * @see docs/features.md F2.6 — Archetype management (create, browse, detail)
 * @see docs/features.md F2.22 — Custom Pokemon sprites on decks
 */

const archetypeRoot = document.getElementById('archetype-select-root');
if (archetypeRoot) {
    const searchUrl = archetypeRoot.dataset.searchUrl ?? '';
    // createUrl is absent for non-editor users (F2.29): the React combobox falls back to
    // a "no matching archetype, ask an editor" empty state instead of offering inline create.
    const createUrl = archetypeRoot.dataset.createUrl;
    const initialId = archetypeRoot.dataset.archetypeId
        ? parseInt(archetypeRoot.dataset.archetypeId, 10)
        : undefined;
    const initialName = archetypeRoot.dataset.archetypeName ?? undefined;

    createRoot(archetypeRoot).render(
        <AppMantineProvider>
            <ArchetypeSelect
                searchUrl={searchUrl}
                createUrl={createUrl}
                initialId={initialId}
                initialName={initialName}
                hiddenInputName="deck_form[archetype]"
            />
        </AppMantineProvider>,
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
        <AppMantineProvider>
            <LanguageSelect
                initialLanguages={initialLanguages}
                hiddenInputName="deck_form[languages]"
            />
        </AppMantineProvider>,
    );
}

const spriteRoot = document.getElementById('pokemon-sprite-select-root');
if (spriteRoot) {
    let initialSlugs: string[] = [];
    try {
        const raw = spriteRoot.dataset.pokemonSlugs;
        if (raw) {
            initialSlugs = JSON.parse(raw);
        }
    } catch {
        // Invalid JSON — use empty array
    }

    createRoot(spriteRoot).render(
        <AppMantineProvider>
            <PokemonSpriteSelect
                initialValues={initialSlugs}
                hiddenInputName="deck_form[pokemonSlugs]"
            />
        </AppMantineProvider>,
    );
}
