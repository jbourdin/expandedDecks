/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React from 'react';
import { MantineProvider } from '@mantine/core';

/**
 * Shared Mantine provider for all React islands in the app. Configures
 * Mantine to follow the operating-system color scheme (`auto`) so it stays
 * in sync with the `data-bs-theme` attribute set on `<html>` by the inline
 * bridge script in base.html.twig.
 *
 * @see docs/features.md F20.1 — Dark theme following OS preference
 */
export default function AppMantineProvider({ children }: { children: React.ReactNode }) {
    return (
        <MantineProvider defaultColorScheme="auto">
            {children}
        </MantineProvider>
    );
}
