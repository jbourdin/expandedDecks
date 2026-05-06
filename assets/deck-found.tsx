/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F4.16 — Lost & found deck alert
 */

import { createRoot } from 'react-dom/client';
import AppMantineProvider from './components/AppMantineProvider';
import '@mantine/core/styles.css';
import DeckFoundModal from './components/DeckFoundModal';

const root = document.getElementById('deck-found-root');
if (root) {
    const apiUrl = root.dataset.apiUrl ?? '';
    const csrfToken = root.dataset.csrfToken ?? '';
    const isLoggedIn = root.dataset.isLoggedIn === '1';
    const ownerDiscord = root.dataset.ownerDiscord ?? '';
    const sitekey = root.dataset.sitekey ?? '';
    const labels = JSON.parse(root.dataset.labels ?? '{}');

    createRoot(root).render(
        <AppMantineProvider>
            <DeckFoundModal
                apiUrl={apiUrl}
                csrfToken={csrfToken}
                isLoggedIn={isLoggedIn}
                ownerDiscord={ownerDiscord}
                sitekey={sitekey}
                labels={labels}
            />
        </AppMantineProvider>,
    );
}
