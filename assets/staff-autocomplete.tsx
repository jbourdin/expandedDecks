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
import AsyncAutocomplete from './components/AsyncAutocomplete';

import '@mantine/core/styles.css';

/**
 * Staff autocomplete entry point.
 *
 * Mounts an AsyncAutocomplete instance into each [data-staff-autocomplete]
 * mount point, wiring the user search API to the hidden user_query input.
 *
 * @see docs/features.md F3.5 — Assign event staff team
 */

document.querySelectorAll<HTMLElement>('[data-staff-autocomplete]').forEach((root) => {
    const searchUrl = root.dataset.searchUrl ?? '';
    const hiddenInput = root.dataset.hiddenInput ?? 'user_query';
    const placeholder = root.dataset.placeholder ?? 'Screen name, email, or Pokemon ID';

    createRoot(root).render(
        <MantineProvider>
            <AsyncAutocomplete
                searchUrl={searchUrl}
                hiddenInputName={hiddenInput}
                placeholder={placeholder}
                mapResult={(item) => ({
                    value: item.screenName as string,
                    label: item.screenName as string,
                    secondary: [item.email, item.playerId].filter(Boolean).join(' · '),
                })}
            />
        </MantineProvider>,
    );
});
