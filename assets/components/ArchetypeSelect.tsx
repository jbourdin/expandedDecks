/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Combobox, InputBase, useCombobox, Modal, TextInput, Button, Group } from '@mantine/core';
import { useDebouncedValue } from '@mantine/hooks';

/**
 * @see docs/features.md F2.6 — Archetype management (create, browse, detail)
 */

interface Archetype {
    id: number;
    name: string;
    slug: string;
}

interface ArchetypeSelectProps {
    searchUrl: string;
    createUrl: string;
    initialId?: number;
    initialName?: string;
    hiddenInputName: string;
}

export default function ArchetypeSelect({ searchUrl, createUrl, initialId, initialName, hiddenInputName }: ArchetypeSelectProps) {
    const [selected, setSelected] = useState<Archetype | null>(
        initialId && initialName ? { id: initialId, name: initialName, slug: '' } : null,
    );
    const [search, setSearch] = useState(initialName ?? '');
    const [debouncedSearch] = useDebouncedValue(search, 300);
    const [options, setOptions] = useState<Archetype[]>([]);
    const [loading, setLoading] = useState(false);
    const [modalOpened, setModalOpened] = useState(false);
    const [createName, setCreateName] = useState('');
    const [creating, setCreating] = useState(false);
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

        setLoading(true);
        try {
            const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                signal: controller.signal,
            });
            const data: Archetype[] = await response.json();
            setOptions(data);
        } catch {
            // Aborted or network error
        } finally {
            setLoading(false);
        }
    }, [searchUrl]);

    useEffect(() => {
        fetchOptions(debouncedSearch);
    }, [debouncedSearch, fetchOptions]);

    const hasExactMatch = options.some(
        (o) => o.name.toLowerCase() === search.toLowerCase(),
    );

    const handleCreate = async () => {
        if (!createName.trim()) return;

        setCreating(true);
        try {
            const response = await fetch(createUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: createName.trim() }),
            });
            const data: Archetype = await response.json();
            setSelected(data);
            setSearch(data.name);
            setModalOpened(false);
            setCreateName('');
            combobox.closeDropdown();
        } finally {
            setCreating(false);
        }
    };

    const hiddenInput = document.querySelector<HTMLInputElement>(
        `input[name="${hiddenInputName}"]`,
    );
    if (hiddenInput) {
        hiddenInput.value = selected?.id?.toString() ?? '';
    }

    return (
        <>
            <Combobox
                store={combobox}
                onOptionSubmit={(val) => {
                    if (val === '__create__') {
                        setCreateName(search);
                        setModalOpened(true);
                    } else {
                        const found = options.find((o) => o.id.toString() === val);
                        if (found) {
                            setSelected(found);
                            setSearch(found.name);
                        }
                    }
                    combobox.closeDropdown();
                }}
            >
                <Combobox.Target>
                    <InputBase
                        rightSection={loading ? <Combobox.Chevron /> : <Combobox.Chevron />}
                        value={search}
                        onChange={(event) => {
                            setSearch(event.currentTarget.value);
                            if (event.currentTarget.value === '') {
                                setSelected(null);
                            }
                            combobox.openDropdown();
                            combobox.updateSelectedOptionIndex();
                        }}
                        onClick={() => combobox.openDropdown()}
                        onFocus={() => combobox.openDropdown()}
                        onBlur={() => combobox.closeDropdown()}
                        placeholder="Search archetype..."
                    />
                </Combobox.Target>

                <Combobox.Dropdown>
                    <Combobox.Options>
                        {options.map((item) => (
                            <Combobox.Option value={item.id.toString()} key={item.id}>
                                {item.name}
                            </Combobox.Option>
                        ))}
                        {!hasExactMatch && search.length >= 2 && (
                            <Combobox.Option value="__create__">
                                + Create &quot;{search}&quot;
                            </Combobox.Option>
                        )}
                        {options.length === 0 && search.length >= 2 && hasExactMatch && (
                            <Combobox.Empty>No results</Combobox.Empty>
                        )}
                    </Combobox.Options>
                </Combobox.Dropdown>
            </Combobox>

            <Modal opened={modalOpened} onClose={() => setModalOpened(false)} title="Create Archetype">
                <TextInput
                    label="Archetype name"
                    value={createName}
                    onChange={(e) => setCreateName(e.currentTarget.value)}
                    placeholder="e.g. Giratina VSTAR / Comfey"
                />
                <Group justify="flex-end" mt="md">
                    <Button variant="default" onClick={() => setModalOpened(false)}>
                        Cancel
                    </Button>
                    <Button onClick={handleCreate} loading={creating}>
                        Create
                    </Button>
                </Group>
            </Modal>
        </>
    );
}
