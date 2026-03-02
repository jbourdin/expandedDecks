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
 * @see docs/features.md F2.8 — Update deck metadata
 */

const POKEMON_TCG_LANGUAGES = [
    { value: 'en', label: 'English' },
    { value: 'ja', label: 'Japanese' },
    { value: 'fr', label: 'French' },
    { value: 'de', label: 'German' },
    { value: 'es', label: 'Spanish' },
    { value: 'it', label: 'Italian' },
    { value: 'pt', label: 'Portuguese' },
    { value: 'ko', label: 'Korean' },
    { value: 'zh-Hans', label: 'Chinese (Simplified)' },
    { value: 'zh-Hant', label: 'Chinese (Traditional)' },
    { value: 'id', label: 'Indonesian' },
    { value: 'th', label: 'Thai' },
];

interface LanguageSelectProps {
    initialLanguages?: string[];
    hiddenInputName: string;
}

export default function LanguageSelect({ initialLanguages = [], hiddenInputName }: LanguageSelectProps) {
    const [value, setValue] = useState<string[]>(initialLanguages);

    const handleChange = (newValue: string[]) => {
        setValue(newValue);

        const hiddenInput = document.querySelector<HTMLInputElement>(
            `input[name="${hiddenInputName}"]`,
        );
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(newValue);
        }
    };

    // Set initial value on hidden input
    const hiddenInput = document.querySelector<HTMLInputElement>(
        `input[name="${hiddenInputName}"]`,
    );
    if (hiddenInput && initialLanguages.length > 0 && hiddenInput.value === '') {
        hiddenInput.value = JSON.stringify(initialLanguages);
    }

    return (
        <MultiSelect
            data={POKEMON_TCG_LANGUAGES}
            value={value}
            onChange={handleChange}
            placeholder="Select languages..."
            searchable
            clearable
            hidePickedOptions
        />
    );
}
