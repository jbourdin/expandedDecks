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
import MarkdownEditor from './components/MarkdownEditor';

import '@mantine/core/styles.css';
import '@mantine/tiptap/styles.css';

/**
 * @see docs/features.md F17.1 — Rich text editor with Markdown
 */

const editorRoots = document.querySelectorAll<HTMLDivElement>('.rich-text-editor-root');
editorRoots.forEach((root) => {
    const textareaId = root.dataset.textareaId;
    if (!textareaId) {
        return;
    }

    const textarea = document.getElementById(textareaId) as HTMLTextAreaElement | null;
    if (!textarea) {
        return;
    }

    createRoot(root).render(
        <MantineProvider>
            <MarkdownEditor
                textareaSelector={`#${textareaId}`}
                initialContent={textarea.value}
                placeholder={textarea.placeholder}
            />
        </MantineProvider>,
    );
});
