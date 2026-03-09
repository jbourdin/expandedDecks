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
import NotificationBell from './components/NotificationBell';

import '@mantine/core/styles.css';

/**
 * @see docs/features.md F8.4 — In-app notification center
 */

const root = document.getElementById('notification-bell-root');
if (root) {
    const apiUrl = root.dataset.apiUrl ?? '';
    const listUrl = root.dataset.listUrl ?? '';
    const markReadUrlTemplate = root.dataset.markReadUrlTemplate ?? '';
    const markAllReadUrl = root.dataset.markAllReadUrl ?? '';
    const labelMarkRead = root.dataset.labelMarkRead ?? 'Mark as read';
    const labelMarkAllRead = root.dataset.labelMarkAllRead ?? 'Mark all as read';
    const labelViewAll = root.dataset.labelViewAll ?? 'View all notifications';
    const labelEmpty = root.dataset.labelEmpty ?? 'No notifications yet';

    createRoot(root).render(
        <MantineProvider>
            <NotificationBell
                apiUrl={apiUrl}
                listUrl={listUrl}
                markReadUrlTemplate={markReadUrlTemplate}
                markAllReadUrl={markAllReadUrl}
                labels={{
                    markRead: labelMarkRead,
                    markAllRead: labelMarkAllRead,
                    viewAll: labelViewAll,
                    empty: labelEmpty,
                }}
            />
        </MantineProvider>,
    );
}
