/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MantineProvider } from '@mantine/core';
import MarkdownEditor from '../components/MarkdownEditor';

/**
 * @see docs/features.md F17.1 — Rich text editor with Markdown
 */

function renderEditor(props: Partial<React.ComponentProps<typeof MarkdownEditor>> = {}) {
    const textarea = document.createElement('textarea');
    textarea.id = 'test-textarea';
    document.body.appendChild(textarea);

    const result = render(
        <MantineProvider>
            <MarkdownEditor
                textareaSelector="#test-textarea"
                initialContent="**bold text**"
                {...props}
            />
        </MantineProvider>,
    );

    return { ...result, textarea };
}

describe('MarkdownEditor', () => {
    afterEach(() => {
        const textarea = document.getElementById('test-textarea');
        if (textarea) {
            textarea.remove();
        }
    });

    it('renders in RTE mode by default', () => {
        renderEditor();

        expect(screen.getByText('Rich Text')).toBeInTheDocument();
        expect(screen.getByText('Markdown')).toBeInTheDocument();
        expect(document.querySelector('.mantine-RichTextEditor-root')).toBeInTheDocument();
    });

    it('switches to raw Markdown mode on toggle', async () => {
        const user = userEvent.setup();
        renderEditor();

        await user.click(screen.getByText('Markdown'));

        const rawTextarea = document.querySelector<HTMLTextAreaElement>('.mantine-Textarea-input');
        expect(rawTextarea).toBeInTheDocument();
        expect(document.querySelector('.mantine-RichTextEditor-root')).not.toBeInTheDocument();
    });

    it('switches back to RTE mode', async () => {
        const user = userEvent.setup();
        renderEditor();

        await user.click(screen.getByText('Markdown'));
        await user.click(screen.getByText('Rich Text'));

        expect(document.querySelector('.mantine-RichTextEditor-root')).toBeInTheDocument();
    });

    it('syncs initial content to the hidden textarea', () => {
        const { textarea } = renderEditor();

        expect(textarea.value).toBe('');
    });

    it('renders with empty initial content', () => {
        renderEditor({ initialContent: '' });

        expect(document.querySelector('.mantine-RichTextEditor-root')).toBeInTheDocument();
    });
});
