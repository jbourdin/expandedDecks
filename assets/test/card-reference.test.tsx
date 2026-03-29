/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React from 'react';
import { render } from '@testing-library/react';
import { MantineProvider } from '@mantine/core';
import MarkdownEditor from '../components/MarkdownEditor';

/**
 * @see docs/features.md F17.2 — Custom Tiptap extension for [[card:SET-NUM]] tags
 */

function renderEditor(initialContent: string) {
    const textarea = document.createElement('textarea');
    textarea.id = 'test-textarea';
    document.body.appendChild(textarea);

    const result = render(
        <MantineProvider>
            <MarkdownEditor
                textareaSelector="#test-textarea"
                initialContent={initialContent}
            />
        </MantineProvider>,
    );

    return { ...result, textarea };
}

describe('CardReference extension', () => {
    afterEach(() => {
        const textarea = document.getElementById('test-textarea');
        if (textarea) {
            textarea.remove();
        }
    });

    it('renders a card reference badge in the editor', async () => {
        renderEditor('Check out [[card:UPR-100]] for details.');

        // Wait for Tiptap to initialize and parse the content
        await vi.waitFor(() => {
            const badge = document.querySelector('[data-card-reference="UPR-100"]');
            expect(badge).toBeInTheDocument();
            expect(badge).toHaveClass('card-reference-badge');
            expect(badge?.textContent).toBe('UPR-100');
        });
    });

    it('renders multiple card references', async () => {
        renderEditor('Compare [[card:SFA-46]] with [[card:SCR-133]].');

        await vi.waitFor(() => {
            const badges = document.querySelectorAll('[data-card-reference]');
            expect(badges).toHaveLength(2);
            expect(badges[0].getAttribute('data-card-reference')).toBe('SFA-46');
            expect(badges[1].getAttribute('data-card-reference')).toBe('SCR-133');
        });
    });

    it('handles card references with complex set codes', async () => {
        renderEditor('See [[card:PR-SV-12]] for the promo.');

        await vi.waitFor(() => {
            const badge = document.querySelector('[data-card-reference="PR-SV-12"]');
            expect(badge).toBeInTheDocument();
            expect(badge?.textContent).toBe('PR-SV-12');
        });
    });
});
