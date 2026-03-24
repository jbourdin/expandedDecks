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
import PlaystyleTagSelect from './components/PlaystyleTagSelect';
import PokemonSpriteSelect from './components/PokemonSpriteSelect';

import '@mantine/core/styles.css';

/**
 * @see docs/features.md F2.15 — Archetype playstyle tags
 * @see docs/features.md F2.18 — Admin archetype create/edit form
 * @see docs/features.md F2.22 — Custom Pokemon sprites on decks
 */

const spriteRoot = document.getElementById('pokemon-sprite-select-root');
if (spriteRoot) {
    let initialSlugs: string[] = [];
    try {
        const raw = spriteRoot.dataset.values;
        if (raw) {
            initialSlugs = JSON.parse(raw);
        }
    } catch {
        // Invalid JSON — use empty array
    }

    createRoot(spriteRoot).render(
        <MantineProvider>
            <PokemonSpriteSelect
                initialValues={initialSlugs}
                hiddenInputName="archetype_form[pokemonSlugs]"
            />
        </MantineProvider>,
    );
}

const playstyleRoot = document.getElementById('playstyle-tag-select-root');
if (playstyleRoot) {
    let existingTags: string[] = [];
    let initialValues: string[] = [];

    try {
        const rawTags = playstyleRoot.dataset.existingTags;
        if (rawTags) {
            existingTags = JSON.parse(rawTags);
        }
    } catch {
        // Invalid JSON — use empty array
    }

    try {
        const rawValues = playstyleRoot.dataset.values;
        if (rawValues) {
            initialValues = JSON.parse(rawValues);
        }
    } catch {
        // Invalid JSON — use empty array
    }

    const placeholder = playstyleRoot.dataset.placeholder ?? undefined;

    createRoot(playstyleRoot).render(
        <MantineProvider>
            <PlaystyleTagSelect
                existingTags={existingTags}
                initialValues={initialValues}
                hiddenInputName="archetype_form[playstyleTags]"
                placeholder={placeholder}
            />
        </MantineProvider>,
    );
}
