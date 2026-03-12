/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useEffect, useRef, useState } from 'react';
import { CloseButton, Combobox, Group, InputBase, Text, useCombobox } from '@mantine/core';

interface ArchetypeOption {
    name: string;
    slug: string;
    pokemonSlugs: string[];
}

interface ArchetypeFilterSelectProps {
    catalogUrl: string;
    hiddenInputName: string;
    placeholder?: string;
    initialValue?: string;
    initialLabel?: string;
}

/**
 * Searchable archetype dropdown for the deck catalog filter bar.
 *
 * Loads all published archetypes with public decks on mount, displays
 * sprites alongside names, and supports client-side type-ahead filtering.
 *
 * @see docs/features.md F2.17 — Deck catalog archetype filter UX
 */
export default function ArchetypeFilterSelect({
    catalogUrl,
    hiddenInputName,
    placeholder = 'Select archetype...',
    initialValue = '',
    initialLabel = '',
}: ArchetypeFilterSelectProps) {
    const [archetypes, setArchetypes] = useState<ArchetypeOption[]>([]);
    const [search, setSearch] = useState(initialLabel);
    const [selectedSlug, setSelectedSlug] = useState(initialValue);
    const selectedSlugRef = useRef(initialValue);

    const combobox = useCombobox({
        onDropdownClose: () => {
            combobox.resetSelectedOption();
            const selected = archetypes.find((archetype) => archetype.slug === selectedSlugRef.current);
            setSearch(selected?.name ?? '');
        },
        onDropdownOpen: () => {
            combobox.updateSelectedOptionIndex();
        },
    });

    useEffect(() => {
        const controller = new AbortController();

        fetch(catalogUrl, { signal: controller.signal })
            .then((response) => {
                if (!response.ok) {
                    return [];
                }

                return response.json();
            })
            .then((data: ArchetypeOption[]) => {
                 
                setArchetypes(data);
            })
            .catch(() => {
                // Aborted or network error
            });

        return () => controller.abort();
    }, [catalogUrl]);

    const filteredArchetypes = search
        ? archetypes.filter((archetype) =>
            archetype.name.toLowerCase().includes(search.toLowerCase()),
        )
        : archetypes;

    const options = filteredArchetypes.map((archetype) => (
        <Combobox.Option value={archetype.slug} key={archetype.slug}>
            <Group gap="xs" wrap="nowrap">
                {archetype.pokemonSlugs.length > 0 && (
                    <span style={{ display: 'inline-flex', flexShrink: 0, alignItems: 'center' }}>
                        {archetype.pokemonSlugs.map((pokemonSlug) => (
                            <img
                                key={pokemonSlug}
                                src={`/build/sprites/pokemon/${pokemonSlug}.png`}
                                alt={pokemonSlug.replace(/-/g, ' ')}
                                style={{ height: 24, imageRendering: 'pixelated' }}
                                loading="lazy"
                            />
                        ))}
                    </span>
                )}
                <Text size="sm" fw={600}>{archetype.name}</Text>
            </Group>
        </Combobox.Option>
    ));

    return (
        <>
            <input type="hidden" name={hiddenInputName} value={selectedSlug} />
            <Combobox
                store={combobox}
                onOptionSubmit={(slug) => {
                    const found = archetypes.find((archetype) => archetype.slug === slug);
                    if (found) {
                        selectedSlugRef.current = found.slug;
                        setSelectedSlug(found.slug);
                        setSearch(found.name);
                    }
                    combobox.closeDropdown();
                }}
            >
                <Combobox.Target>
                    <InputBase
                        value={search}
                        onChange={(event) => {
                            const value = event.currentTarget.value;
                            setSearch(value);
                            if (value === '') {
                                selectedSlugRef.current = '';
                                setSelectedSlug('');
                            }
                            combobox.openDropdown();
                            combobox.updateSelectedOptionIndex();
                        }}
                        onClick={() => combobox.openDropdown()}
                        onFocus={() => combobox.openDropdown()}
                        onBlur={() => combobox.closeDropdown()}
                        placeholder={placeholder}
                        rightSection={
                            selectedSlug
                                ? <CloseButton
                                    size="sm"
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => {
                                        selectedSlugRef.current = '';
                                        setSelectedSlug('');
                                        setSearch('');
                                    }}
                                    aria-label="Clear selection"
                                />
                                : <Combobox.Chevron />
                        }
                        rightSectionPointerEvents={selectedSlug ? 'all' : 'none'}
                    />
                </Combobox.Target>

                <Combobox.Dropdown>
                    <Combobox.Options mah={280} style={{ overflowY: 'auto' }}>
                        {options}
                        {options.length === 0 && (
                            <Combobox.Empty>No archetypes found</Combobox.Empty>
                        )}
                    </Combobox.Options>
                </Combobox.Dropdown>
            </Combobox>
        </>
    );
}
