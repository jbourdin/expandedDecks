/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Combobox, InputBase, Text, useCombobox } from '@mantine/core';
import { useDebouncedValue } from '@mantine/hooks';

export interface AutocompleteItem {
    value: string;
    label: string;
    secondary?: string;
}

interface AsyncAutocompleteProps {
    searchUrl: string;
    hiddenInputName: string;
    placeholder?: string;
    initialValue?: string;
    queryParam?: string;
    mapResult: (item: Record<string, unknown>) => AutocompleteItem;
    onSelect?: (item: AutocompleteItem) => void;
}

/**
 * Generic async autocomplete component built on Mantine Combobox.
 *
 * Fetches results from a search API with debounce, renders a dropdown
 * with primary/secondary labels, and syncs the selected value to a
 * hidden input for form submission.
 *
 * @see docs/features.md F3.5 — Assign event staff team
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 */
export default function AsyncAutocomplete({
    searchUrl,
    hiddenInputName,
    placeholder = 'Search...',
    initialValue = '',
    queryParam = 'q',
    mapResult,
    onSelect,
}: AsyncAutocompleteProps) {
    const [search, setSearch] = useState(initialValue);
    const [debouncedSearch] = useDebouncedValue(search, 300);
    const [options, setOptions] = useState<AutocompleteItem[]>([]);
    const [hiddenValue, setHiddenValue] = useState('');
    const abortRef = useRef<AbortController | null>(null);

    const combobox = useCombobox({
        onDropdownClose: () => combobox.resetSelectedOption(),
    });

    const fetchOptions = useCallback(async (query: string) => {
        if (query.length < 2) {
            setOptions([]);
            return;
        }

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        try {
            const separator = searchUrl.includes('?') ? '&' : '?';
            const url = `${searchUrl}${separator}${queryParam}=${encodeURIComponent(query)}`;
            const response = await fetch(url, { signal: controller.signal });
            if (!response.ok) return;
            const data: Record<string, unknown>[] = await response.json();
            setOptions(data.map(mapResult));
        } catch {
            // Aborted or network error
        }
    }, [searchUrl, queryParam, mapResult]);

    useEffect(() => {
        fetchOptions(debouncedSearch);
    }, [debouncedSearch, fetchOptions]);

    return (
        <>
            <input type="hidden" name={hiddenInputName} value={hiddenValue} />
            <Combobox
                store={combobox}
                onOptionSubmit={(val) => {
                    const found = options.find((o) => o.value === val);
                    if (found) {
                        setSearch(found.label);
                        setHiddenValue(found.value);
                        onSelect?.(found);
                    }
                    combobox.closeDropdown();
                }}
            >
                <Combobox.Target>
                    <InputBase
                        value={search}
                        onChange={(event) => {
                            const val = event.currentTarget.value;
                            setSearch(val);
                            setHiddenValue('');
                            combobox.openDropdown();
                            combobox.updateSelectedOptionIndex();
                        }}
                        onClick={() => combobox.openDropdown()}
                        onFocus={() => combobox.openDropdown()}
                        onBlur={() => combobox.closeDropdown()}
                        placeholder={placeholder}
                        rightSection={<Combobox.Chevron />}
                        rightSectionPointerEvents="none"
                    />
                </Combobox.Target>

                <Combobox.Dropdown>
                    <Combobox.Options>
                        {options.map((item) => (
                            <Combobox.Option value={item.value} key={item.value}>
                                <Text fw={600} size="sm">{item.label}</Text>
                                {item.secondary && (
                                    <Text size="xs" c="dimmed">{item.secondary}</Text>
                                )}
                            </Combobox.Option>
                        ))}
                        {options.length === 0 && debouncedSearch.length >= 2 && (
                            <Combobox.Empty>No results</Combobox.Empty>
                        )}
                    </Combobox.Options>
                </Combobox.Dropdown>
            </Combobox>
        </>
    );
}
