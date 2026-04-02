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
import ImageUrlField from './components/ImageUrlField';

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

/**
 * @see docs/features.md F10.6 — ImageUrlField component with drag-and-drop upload
 */

const imageUrlRoots = document.querySelectorAll<HTMLDivElement>('.image-url-field-root');
imageUrlRoots.forEach((root) => {
    const inputId = root.dataset.inputId;
    const uploadUrl = root.dataset.uploadUrl ?? '';
    const serverError = root.dataset.error ?? '';

    if (!inputId) {
        return;
    }

    const hiddenInput = document.getElementById(inputId) as HTMLInputElement | null;
    if (!hiddenInput) {
        return;
    }

    const ImageUrlFieldWrapper = () => {
        const [value, setValue] = React.useState(hiddenInput.value);
        const [error, setError] = React.useState(serverError || null);

        const handleChange = (url: string) => {
            setValue(url);
            hiddenInput.value = url;
            setError(null);
        };

        return (
            <ImageUrlField
                value={value}
                onChange={handleChange}
                uploadUrl={uploadUrl}
                serverError={error}
            />
        );
    };

    createRoot(root).render(
        <MantineProvider>
            <ImageUrlFieldWrapper />
        </MantineProvider>,
    );
});
