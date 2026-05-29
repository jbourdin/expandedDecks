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
import PlaystyleTagSelect from './components/PlaystyleTagSelect';
import PokemonSpriteSelect from './components/PokemonSpriteSelect';
import MarkdownEditor from './components/MarkdownEditor';
import { mountImageUrlFields } from './shared/mount-image-url-field';

import '@mantine/core/styles.css';
import '@mantine/tiptap/styles.css';

/**
 * @see docs/features.md F2.15 — Archetype playstyle tags
 * @see docs/features.md F2.18 — Admin archetype create/edit form
 * @see docs/features.md F2.22 — Custom Pokemon sprites on decks
 * @see docs/features.md F17.1 — Rich text editor with Markdown
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

    const hiddenInputName = spriteRoot.dataset.hiddenInputName ?? 'archetype_form[pokemonSlugs]';

    createRoot(spriteRoot).render(
        <AppMantineProvider>
            <PokemonSpriteSelect
                initialValues={initialSlugs}
                hiddenInputName={hiddenInputName}
            />
        </AppMantineProvider>,
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
        <AppMantineProvider>
            <PlaystyleTagSelect
                existingTags={existingTags}
                initialValues={initialValues}
                hiddenInputName="archetype_form[playstyleTags]"
                placeholder={placeholder}
            />
        </AppMantineProvider>,
    );
}

/**
 * @see docs/features.md F18.30 — Editor-defined OG image and description on decks, archetypes, variants
 */
mountImageUrlFields();

const editorRoots = document.querySelectorAll<HTMLDivElement>('.rich-text-editor-root');
editorRoots.forEach((root) => {
    const textareaId = root.dataset.textareaId;
    if (!textareaId) {
        return;
    }

    const textarea = document.getElementById(textareaId) as HTMLTextAreaElement | null;
    if (!textarea) {
        return;
    }

    createRoot(root).render(
        <AppMantineProvider>
            <MarkdownEditor
                textareaSelector={`#${textareaId}`}
                initialContent={textarea.value}
                placeholder={textarea.placeholder}
            />
        </AppMantineProvider>,
    );
});
