/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mounts the `ImageUrlField` React component over every `.image-url-field-root`
 * element on the page, syncing its URL value back to a hidden Symfony input.
 *
 * Twig templates that render a Symfony image field via the
 * `admin/_image_url_field.html.twig` macro produce both:
 *   - a hidden `<input type="text">` with the underlying Symfony field ID
 *   - a sibling `<div class="image-url-field-root" data-input-id=... data-upload-url=... data-error=...>`
 *
 * This helper finds those roots, replaces the visible UI with the Mantine
 * drag-and-drop component, and pipes the resulting URL back into the hidden
 * input so the standard Symfony form submission carries the value.
 *
 * @see docs/features.md F10.6 — ImageUrlField component with drag-and-drop upload
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import AppMantineProvider from '../components/AppMantineProvider';
import ImageUrlField from '../components/ImageUrlField';

export function mountImageUrlFields(): void {
    const roots = document.querySelectorAll<HTMLDivElement>('.image-url-field-root');
    roots.forEach((root) => {
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

        const Wrapper = () => {
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
            <AppMantineProvider>
                <Wrapper />
            </AppMantineProvider>,
        );
    });
}
