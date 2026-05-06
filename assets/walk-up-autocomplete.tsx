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
import AsyncAutocomplete from './components/AsyncAutocomplete';

import '@mantine/core/styles.css';

/**
 * Walk-up autocomplete entry point.
 *
 * Mounts AsyncAutocomplete instances for deck and borrower search
 * on the walk-up lending page.
 *
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 */

// Deck autocomplete
document.querySelectorAll<HTMLElement>('[data-walk-up-deck-autocomplete]').forEach((root) => {
    const searchUrl = root.dataset.searchUrl ?? '';
    const hiddenInput = root.dataset.hiddenInput ?? 'deck_id';
    const placeholder = root.dataset.placeholder ?? 'Search by deck name or tag...';

    createRoot(root).render(
        <AppMantineProvider>
            <AsyncAutocomplete
                searchUrl={searchUrl}
                hiddenInputName={hiddenInput}
                placeholder={placeholder}
                mapResult={(item) => ({
                    value: String(item.id),
                    label: item.name as string,
                    secondary: `${item.ownerName as string} · ${item.shortTag as string}`,
                })}
            />
        </AppMantineProvider>,
    );
});

// User autocomplete
document.querySelectorAll<HTMLElement>('[data-walk-up-user-autocomplete]').forEach((root) => {
    const searchUrl = root.dataset.searchUrl ?? '';
    const hiddenInput = root.dataset.hiddenInput ?? 'borrower_id';
    const placeholder = root.dataset.placeholder ?? 'Search by screen name, email, or Pokemon ID...';

    createRoot(root).render(
        <AppMantineProvider>
            <AsyncAutocomplete
                searchUrl={searchUrl}
                hiddenInputName={hiddenInput}
                placeholder={placeholder}
                mapResult={(item) => ({
                    value: String(item.id),
                    label: item.screenName as string,
                    secondary: [item.email, item.playerId].filter(Boolean).join(' · '),
                })}
            />
        </AppMantineProvider>,
    );
});
