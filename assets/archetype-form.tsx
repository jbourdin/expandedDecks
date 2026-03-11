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

import '@mantine/core/styles.css';

/**
 * @see docs/features.md F2.15 — Archetype playstyle tags
 * @see docs/features.md F2.18 — Admin archetype create/edit form
 */

const playstyleRoot = document.getElementById('playstyle-tag-select-root');
if (playstyleRoot) {
    let tags: { value: string; label: string }[] = [];
    let initialValues: string[] = [];

    try {
        const rawTags = playstyleRoot.dataset.tags;
        if (rawTags) {
            tags = JSON.parse(rawTags);
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
                tags={tags}
                initialValues={initialValues}
                hiddenInputName="archetype_form[playstyleTags]"
                placeholder={placeholder}
            />
        </MantineProvider>,
    );
}
