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
import EventTagSelect from './components/EventTagSelect';

import '@mantine/core/styles.css';

/**
 * @see docs/features.md F3.12 — Event tags
 */

const tagRoot = document.getElementById('event-tag-select-root');

if (tagRoot) {
    let existingTags: string[] = [];
    let initialValues: string[] = [];

    try {
        const rawTags = tagRoot.dataset.existingTags;
        if (rawTags) {
            existingTags = JSON.parse(rawTags);
        }
    } catch {
        // Invalid JSON — use empty array
    }

    try {
        const rawValues = tagRoot.dataset.values;
        if (rawValues) {
            initialValues = JSON.parse(rawValues);
        }
    } catch {
        // Invalid JSON — use empty array
    }

    const hiddenInputName = tagRoot.dataset.hiddenInputName ?? 'event_form[tagsInput]';
    const placeholder = tagRoot.dataset.placeholder ?? '';

    createRoot(tagRoot).render(
        <AppMantineProvider>
            <EventTagSelect
                existingTags={existingTags}
                initialValues={initialValues}
                hiddenInputName={hiddenInputName}
                placeholder={placeholder}
            />
        </AppMantineProvider>,
    );
}
