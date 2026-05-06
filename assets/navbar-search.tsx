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
import NavbarSearch from './components/NavbarSearch';

import '@mantine/core/styles.css';

/**
 * @see docs/features.md F18.3 — Quick-search autocomplete (navbar)
 */

const root = document.getElementById('navbar-search-root');
if (root) {
    const searchUrl = root.dataset.searchUrl ?? '';
    const searchPageUrl = root.dataset.searchPageUrl ?? '/search';
    const labelPlaceholder = root.dataset.labelPlaceholder ?? 'Search…';
    const labelSeeAll = root.dataset.labelSeeAll ?? 'See all results';
    const labelNoResults = root.dataset.labelNoResults ?? 'No results';

    createRoot(root).render(
        <AppMantineProvider>
            <NavbarSearch
                searchUrl={searchUrl}
                searchPageUrl={searchPageUrl}
                labels={{
                    placeholder: labelPlaceholder,
                    seeAll: labelSeeAll,
                    noResults: labelNoResults,
                }}
            />
        </AppMantineProvider>,
    );
}
