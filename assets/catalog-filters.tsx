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
import ArchetypeFilterSelect from './components/ArchetypeFilterSelect';
import AsyncAutocomplete from './components/AsyncAutocomplete';

import '@mantine/core/styles.css';

/**
 * Mantine autocomplete entry point for all deck catalog filters.
 *
 * Mounts ArchetypeFilterSelect for the archetype dropdown and
 * AsyncAutocomplete instances for event and owner search fields
 * in the catalog filter bar.
 *
 * @see docs/features.md F2.4 — Deck Catalog (Browse & Search)
 * @see docs/features.md F2.17 — Deck catalog archetype filter UX
 */

document.querySelectorAll<HTMLElement>('[data-catalog-archetype]').forEach((root) => {
    const catalogUrl = root.dataset.catalogUrl ?? '';
    const hiddenInput = root.dataset.hiddenInput ?? 'archetype';
    const placeholder = root.dataset.placeholder ?? 'Select archetype...';
    const initialValue = root.dataset.initialValue ?? '';
    const initialLabel = root.dataset.initialLabel ?? '';

    createRoot(root).render(
        <MantineProvider>
            <ArchetypeFilterSelect
                catalogUrl={catalogUrl}
                hiddenInputName={hiddenInput}
                placeholder={placeholder}
                initialValue={initialValue}
                initialLabel={initialLabel}
            />
        </MantineProvider>,
    );
});

document.querySelectorAll<HTMLElement>('[data-catalog-event]').forEach((root) => {
    const searchUrl = root.dataset.searchUrl ?? '';
    const hiddenInput = root.dataset.hiddenInput ?? 'event';
    const placeholder = root.dataset.placeholder ?? 'Search by event name...';
    const initialValue = root.dataset.initialValue ?? '';
    const initialId = root.dataset.initialId ?? '';

    createRoot(root).render(
        <MantineProvider>
            <AsyncAutocomplete
                searchUrl={searchUrl}
                hiddenInputName={hiddenInput}
                placeholder={placeholder}
                initialValue={initialValue}
                initialHiddenValue={initialId}
                mapResult={(item) => ({
                    value: String(item.id),
                    label: item.name as string,
                    secondary: `${item.date as string} · ${item.location as string}`,
                })}
            />
        </MantineProvider>,
    );
});

document.querySelectorAll<HTMLElement>('[data-catalog-owner]').forEach((root) => {
    const searchUrl = root.dataset.searchUrl ?? '';
    const hiddenInput = root.dataset.hiddenInput ?? 'owner';
    const placeholder = root.dataset.placeholder ?? 'Search by screen name...';
    const initialValue = root.dataset.initialValue ?? '';
    const initialId = root.dataset.initialId ?? '';

    createRoot(root).render(
        <MantineProvider>
            <AsyncAutocomplete
                searchUrl={searchUrl}
                hiddenInputName={hiddenInput}
                placeholder={placeholder}
                initialValue={initialValue}
                initialHiddenValue={initialId}
                mapResult={(item) => ({
                    value: String(item.id),
                    label: item.screenName as string,
                })}
            />
        </MantineProvider>,
    );
});
