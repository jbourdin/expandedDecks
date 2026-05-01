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
 * @see docs/features.md F3.12 — Event tags
 */

interface EventTagSelectProps {
    existingTags: string[];
    initialValues?: string[];
    hiddenInputName: string;
    placeholder?: string;
}

const normalizeTag = (tag: string): string => tag.replace(/\s+/g, ' ').trim();

const EventTagSelect: React.FC<EventTagSelectProps> = ({ existingTags, initialValues = [], hiddenInputName, placeholder }) => {
    const [value, setValue] = useState<string[]>(initialValues);

    const syncHidden = (newValue: string[]): void => {
        const hiddenInput = document.querySelector<HTMLInputElement>(
            `input[name="${hiddenInputName}"]`,
        );

        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(newValue);
        }
    };

    if (initialValues.length > 0) {
        const hiddenInput = document.querySelector<HTMLInputElement>(
            `input[name="${hiddenInputName}"]`,
        );
        if (hiddenInput && hiddenInput.value === '') {
            hiddenInput.value = JSON.stringify(initialValues);
        }
    }

    const handleChange = (newValue: string[]): void => {
        const normalized = newValue.map(normalizeTag).filter((tag) => tag.length >= 2);
        const seen = new Set<string>();
        const unique: string[] = [];

        for (const tag of normalized) {
            const key = tag.toLowerCase();

            if (!seen.has(key)) {
                seen.add(key);
                unique.push(tag);
            }
        }

        setValue(unique);
        syncHidden(unique);
    };

    return (
        <TagsInput
            data={existingTags}
            value={value}
            onChange={handleChange}
            placeholder={placeholder ?? ''}
            clearable
            splitChars={[',']}
        />
    );
};

export default EventTagSelect;
