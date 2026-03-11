/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useState } from 'react';
import { MultiSelect } from '@mantine/core';

/**
 * @see docs/features.md F2.15 — Archetype playstyle tags
 */

interface PlaystyleTagSelectProps {
    tags: { value: string; label: string }[];
    initialValues?: string[];
    hiddenInputName: string;
    placeholder?: string;
}

export default function PlaystyleTagSelect({ tags, initialValues = [], hiddenInputName, placeholder }: PlaystyleTagSelectProps) {
    const [value, setValue] = useState<string[]>(initialValues);

    const handleChange = (newValue: string[]) => {
        setValue(newValue);

        const hiddenInput = document.querySelector<HTMLInputElement>(
            `input[name="${hiddenInputName}"]`,
        );
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(newValue);
        }
    };

    const hiddenInput = document.querySelector<HTMLInputElement>(
        `input[name="${hiddenInputName}"]`,
    );
    if (hiddenInput && initialValues.length > 0 && hiddenInput.value === '') {
        hiddenInput.value = JSON.stringify(initialValues);
    }

    return (
        <MultiSelect
            data={tags}
            value={value}
            onChange={handleChange}
            placeholder={placeholder ?? 'Select playstyle tags...'}
            searchable
            clearable
            hidePickedOptions
        />
    );
}
