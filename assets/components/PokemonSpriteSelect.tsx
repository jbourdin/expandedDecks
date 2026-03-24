/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useState } from 'react';
import { Combobox, Group, Pill, PillsInput, ScrollArea, useCombobox } from '@mantine/core';
import spriteManifest from '../generated/pokemon-sprites.json';

/**
 * @see docs/features.md F2.22 — Custom Pokemon sprites on decks
 */

interface PokemonSpriteSelectProps {
    initialValues?: string[];
    hiddenInputName: string;
    placeholder?: string;
}

/**
 * Convert a slug like "flutter-mane" to a readable name like "Flutter Mane".
 */
function slugToName(slug: string): string {
    return slug.replace(/-/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
}

/**
 * Render a small sprite image for a given slug.
 */
function SpritePreview({ slug }: { slug: string }) {
    return (
        <img
            src={`/build/sprites/pokemon/${slug}.png`}
            alt={slugToName(slug)}
            style={{ width: 20, height: 20, imageRendering: 'pixelated' }}
        />
    );
}

export default function PokemonSpriteSelect({ initialValues = [], hiddenInputName, placeholder }: PokemonSpriteSelectProps) {
    const allSlugs: string[] = spriteManifest;
    const [value, setValue] = useState<string[]>(initialValues);
    const [search, setSearch] = useState('');
    const combobox = useCombobox({
        onDropdownClose: () => combobox.resetSelectedOption(),
        onDropdownOpen: () => combobox.updateSelectedOptionIndex('active'),
    });

    const syncHidden = (newValue: string[]) => {
        const hiddenInput = document.querySelector<HTMLInputElement>(
            `input[name="${hiddenInputName}"]`,
        );
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(newValue);
        }
    };

    const handleValueSelect = (selectedSlug: string) => {
        if (!value.includes(selectedSlug)) {
            const updated = [...value, selectedSlug];
            setValue(updated);
            syncHidden(updated);
        }
        setSearch('');
    };

    const handleValueRemove = (removedSlug: string) => {
        const updated = value.filter((slug) => slug !== removedSlug);
        setValue(updated);
        syncHidden(updated);
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

    const filteredOptions = search.trim().length > 0
        ? allSlugs
            .filter((slug) => !value.includes(slug))
            .filter((slug) => slug.includes(search.trim().toLowerCase().replace(/ /g, '-')))
            .slice(0, 50)
        : [];

    const pills = value.map((slug) => (
        <Pill
            key={slug}
            withRemoveButton
            onRemove={() => handleValueRemove(slug)}
        >
            <Group gap={4} wrap="nowrap" style={{ display: 'inline-flex', alignItems: 'center' }}>
                <SpritePreview slug={slug} />
                {slugToName(slug)}
            </Group>
        </Pill>
    ));

    const options = filteredOptions.map((slug) => (
        <Combobox.Option value={slug} key={slug} active={value.includes(slug)}>
            <Group gap="sm">
                <SpritePreview slug={slug} />
                <span>{slugToName(slug)}</span>
            </Group>
        </Combobox.Option>
    ));

    return (
        <Combobox store={combobox} onOptionSubmit={handleValueSelect}>
            <Combobox.DropdownTarget>
                <PillsInput onClick={() => combobox.openDropdown()}>
                    <Pill.Group>
                        {pills}
                        <Combobox.EventsTarget>
                            <PillsInput.Field
                                value={search}
                                placeholder={value.length === 0 ? (placeholder ?? 'Search Pokemon sprites...') : undefined}
                                onChange={(event) => {
                                    combobox.updateSelectedOptionIndex();
                                    setSearch(event.currentTarget.value);
                                    if (event.currentTarget.value.trim().length > 0) {
                                        combobox.openDropdown();
                                    }
                                }}
                                onFocus={() => {
                                    if (search.trim().length > 0) {
                                        combobox.openDropdown();
                                    }
                                }}
                                onBlur={() => combobox.closeDropdown()}
                                onKeyDown={(event) => {
                                    if (event.key === 'Backspace' && search.length === 0) {
                                        event.preventDefault();
                                        const lastSlug = value[value.length - 1];
                                        if (lastSlug) {
                                            handleValueRemove(lastSlug);
                                        }
                                    }
                                }}
                            />
                        </Combobox.EventsTarget>
                    </Pill.Group>
                </PillsInput>
            </Combobox.DropdownTarget>

            {options.length > 0 && (
                <Combobox.Dropdown>
                    <Combobox.Options>
                        <ScrollArea.Autosize mah={200} type="scroll">
                            {options}
                        </ScrollArea.Autosize>
                    </Combobox.Options>
                </Combobox.Dropdown>
            )}
        </Combobox>
    );
}
