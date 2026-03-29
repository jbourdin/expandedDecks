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
 * @see docs/features.md F17.3 — Custom Tiptap extension for [[archetype:slug]] tags
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

describe('ArchetypeReference extension', () => {
    afterEach(() => {
        const textarea = document.getElementById('test-textarea');
        if (textarea) {
            textarea.remove();
        }
    });

    it('renders an archetype reference badge in the editor', async () => {
        renderEditor('Pair with [[archetype:kyurem]] for board control.');

        await vi.waitFor(() => {
            const badge = document.querySelector('[data-archetype-slug="kyurem"]');
            expect(badge).toBeInTheDocument();
            expect(badge).toHaveClass('archetype-reference-badge');
            expect(badge?.textContent).toBe('kyurem');
        });
    });

    it('renders multiple archetype references', async () => {
        renderEditor('Choose between [[archetype:kyurem]] and [[archetype:salamence-ex]].');

        await vi.waitFor(() => {
            const badges = document.querySelectorAll('[data-archetype-slug]');
            expect(badges).toHaveLength(2);
            expect(badges[0].getAttribute('data-archetype-slug')).toBe('kyurem');
            expect(badges[1].getAttribute('data-archetype-slug')).toBe('salamence-ex');
        });
    });

    it('renders alongside card references', async () => {
        renderEditor('Use [[card:UPR-100]] in [[archetype:regidrago]].');

        await vi.waitFor(() => {
            expect(document.querySelector('[data-card-reference="UPR-100"]')).toBeInTheDocument();
            expect(document.querySelector('[data-archetype-slug="regidrago"]')).toBeInTheDocument();
        });
    });
});
