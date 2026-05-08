/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React from 'react';
import { MantineProvider, MantineColorSchemeManager, MantineColorScheme } from '@mantine/core';

/**
 * Bridge Mantine's color scheme to the resolved `data-bs-theme` attribute set
 * on `<html>` by the inline script in base.html.twig. Without this, Mantine's
 * "auto" mode follows the OS preference and overrides the user's app-level
 * toggle — surfaces like the RichTextEditor toolbar render light over a dark
 * page when the OS is light but the app is forced dark.
 */
function dataBsThemeColorSchemeManager(): MantineColorSchemeManager {
    let observer: MutationObserver | null = null;
    let lastNotified: MantineColorScheme | null = null;

    const readScheme = (): MantineColorScheme => {
        if (typeof document === 'undefined') {
            return 'auto';
        }
        const value = document.documentElement.getAttribute('data-bs-theme');
        return value === 'dark' ? 'dark' : 'light';
    };

    return {
        get: () => {
            const scheme = readScheme();
            lastNotified = scheme;
            return scheme;
        },
        set: () => {
            // Intentional no-op. Persistence is owned by `window.__edTheme` (called by
            // the in-page theme switcher); writing back here would loop because
            // MutationObserver fires on every `setAttribute` even when the value
            // is unchanged, and Mantine calls `set` from its post-update effect.
        },
        subscribe: (onUpdate) => {
            if (typeof window === 'undefined') {
                return;
            }
            observer = new MutationObserver(() => {
                const next = readScheme();
                if (next === lastNotified) {
                    return;
                }
                lastNotified = next;
                onUpdate(next);
            });
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
        },
        unsubscribe: () => {
            observer?.disconnect();
            observer = null;
        },
        clear: () => {
            // Persistence lives in localStorage via window.__edTheme; nothing to clear here.
        },
    };
}

const colorSchemeManager = dataBsThemeColorSchemeManager();

/**
 * Shared Mantine provider for all React islands in the app.
 *
 * @see docs/features.md F20.1 — Dark theme following OS preference
 */
export default function AppMantineProvider({ children }: { children: React.ReactNode }) {
    return (
        <MantineProvider defaultColorScheme="auto" colorSchemeManager={colorSchemeManager}>
            {children}
        </MantineProvider>
    );
}
