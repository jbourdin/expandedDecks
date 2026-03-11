/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useState } from 'react';
import { TagsInput } from '@mantine/core';

/**
 * @see docs/features.md F2.15 — Archetype playstyle tags
 */

interface PlaystyleTagSelectProps {
    existingTags: string[];
    initialValues?: string[];
    hiddenInputName: string;
    placeholder?: string;
}

/**
 * Normalize a tag: only alphanumeric and spaces, title case.
 */
function normalizeTag(tag: string): string {
    const cleaned = tag.replace(/[^a-zA-Z0-9 ]/g, '').replace(/\s+/g, ' ').trim();

    return cleaned.replace(/\w\S*/g, (word) =>
        word.charAt(0).toUpperCase() + word.slice(1).toLowerCase(),
    );
}

export default function PlaystyleTagSelect({ existingTags, initialValues = [], hiddenInputName, placeholder }: PlaystyleTagSelectProps) {
    const [value, setValue] = useState<string[]>(initialValues);

    const syncHidden = (newValue: string[]) => {
        const hiddenInput = document.querySelector<HTMLInputElement>(
            `input[name="${hiddenInputName}"]`,
        );
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(newValue);
        }
    };

    const handleChange = (newValue: string[]) => {
        const normalized = newValue.map(normalizeTag).filter((tag) => tag.length > 0);
        const unique = [...new Set(normalized)];
        setValue(unique);
        syncHidden(unique);
    };

    // Sync initial value on mount
    if (initialValues.length > 0) {
        const hiddenInput = document.querySelector<HTMLInputElement>(
            `input[name="${hiddenInputName}"]`,
        );
        if (hiddenInput && hiddenInput.value === '') {
            hiddenInput.value = JSON.stringify(initialValues);
        }
    }

    return (
        <TagsInput
            data={existingTags}
            value={value}
            onChange={handleChange}
            placeholder={placeholder ?? 'Select or create tags...'}
            clearable
            splitChars={[',']}
        />
    );
}
