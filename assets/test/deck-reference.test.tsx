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
 * @see docs/features.md F17.2 — Custom Tiptap extension for [[deck:SHORT_TAG]] tags
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

describe('DeckReference extension', () => {
    afterEach(() => {
        const textarea = document.getElementById('test-textarea');
        if (textarea) {
            textarea.remove();
        }
    });

    it('renders a deck reference badge in the editor', async () => {
        renderEditor('Check out [[deck:A445HP]] for the list.');

        await vi.waitFor(() => {
            const badge = document.querySelector('[data-deck-short-tag="A445HP"]');
            expect(badge).toBeInTheDocument();
            expect(badge).toHaveClass('deck-reference-badge');
            expect(badge?.textContent).toBe('A445HP');
        });
    });

    it('renders alongside card and archetype references', async () => {
        renderEditor('Use [[card:UPR-100]] in [[archetype:regidrago]] list [[deck:X7B2FN]].');

        await vi.waitFor(() => {
            expect(document.querySelector('[data-card-reference="UPR-100"]')).toBeInTheDocument();
            expect(document.querySelector('[data-archetype-slug="regidrago"]')).toBeInTheDocument();
            expect(document.querySelector('[data-deck-short-tag="X7B2FN"]')).toBeInTheDocument();
        });
    });

    it('does not match invalid short tags', async () => {
        renderEditor('Invalid: [[deck:abc]] and [[deck:TOOLONG1]].');

        // Wait for editor init, then verify no badges rendered
        await vi.waitFor(() => {
            expect(document.querySelector('.mantine-RichTextEditor-root')).toBeInTheDocument();
        });

        expect(document.querySelectorAll('[data-deck-short-tag]')).toHaveLength(0);
    });
});
