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
import OgImageBuilder from './components/OgImageBuilder';

import '@mantine/core/styles.css';

/**
 * @see docs/features.md F18.32 — Card-fan OG image builder
 */

const root = document.querySelector<HTMLDivElement>('.og-image-builder-root');
if (root) {
    const { generateUrl } = root.dataset;
    if (generateUrl) {
        createRoot(root).render(
            <AppMantineProvider>
                <OgImageBuilder
                    generateUrl={generateUrl}
                    labels={{
                        codesLabel: root.dataset.labelCodes ?? 'Card codes',
                        codesHelp: root.dataset.labelCodesHelp ?? 'One card code per line, e.g. SV08-128',
                        generate: root.dataset.labelGenerate ?? 'Generate',
                        copyUrl: root.dataset.labelCopyUrl ?? 'Copy URL',
                        copied: root.dataset.labelCopied ?? 'Copied',
                        notFound: root.dataset.labelNotFound ?? 'not found',
                        errorCardCount: root.dataset.labelErrorCardCount ?? 'Enter between 2 and 6 card codes.',
                        errorNoneResolved: root.dataset.labelErrorNoneResolved ?? 'No card could be resolved.',
                        errorGeneric: root.dataset.labelErrorGeneric ?? 'Generation failed. Try again.',
                    }}
                />
            </AppMantineProvider>,
        );
    }
}
