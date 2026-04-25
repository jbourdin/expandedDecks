/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useEffect, useRef, useState } from 'react';
import { Combobox, TextInput, useCombobox } from '@mantine/core';
import { useDebouncedValue } from '@mantine/hooks';
import { IconSearch } from '@tabler/icons-react';

/**
 * @see docs/features.md F18.3 — Quick-search autocomplete (navbar)
 */

interface SearchGroup {
    type: string;
    items: SearchItem[];
}

interface SearchItem {
    title: string;
    type: string;
    url: string;
    secondary: string | null;
}

interface NavbarSearchProps {
    searchUrl: string;
    searchPageUrl: string;
    labels: {
        placeholder: string;
        seeAll: string;
        noResults: string;
    };
}

const TYPE_ICONS: Record<string, string> = {
    archetype: '\u2694\uFE0F',
    variant: '\uD83C\uDCCF',
    page: '\uD83D\uDCC4',
    event: '\uD83D\uDCC5',
    deck: '\uD83C\uDCCF',
};

const NavbarSearch: React.FC<NavbarSearchProps> = ({ searchUrl, searchPageUrl, labels }) => {
    const [query, setQuery] = useState('');
    const [debouncedQuery] = useDebouncedValue(query, 300);
    const [groups, setGroups] = useState<SearchGroup[]>([]);
    const [loading, setLoading] = useState(false);
    const abortRef = useRef<AbortController | null>(null);
    const combobox = useCombobox();

    useEffect(() => {
        if (debouncedQuery.length < 2) {
            setGroups([]);
            return;
        }

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setLoading(true);
        const separator = searchUrl.includes('?') ? '&' : '?';
        const url = `${searchUrl}${separator}q=${encodeURIComponent(debouncedQuery)}`;

        fetch(url, { signal: controller.signal })
            .then((response) => {
                if (!response.ok) return;
                return response.json();
            })
            .then((data: SearchGroup[] | undefined) => {
                if (data) {
                    setGroups(data);
                    combobox.openDropdown();
                }
            })
            .catch(() => {
                // Aborted or network error
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- combobox is stable but not referentially equal
    }, [debouncedQuery, searchUrl]);

    const hasResults = groups.some((group) => group.items.length > 0);

    const handleOptionSubmit = (value: string) => {
        if (value === '__see_all__') {
            window.location.href = `${searchPageUrl}?q=${encodeURIComponent(query)}`;
        } else {
            window.location.href = value;
        }
        combobox.closeDropdown();
    };

    const handleKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'Enter' && !combobox.dropdownOpened) {
            window.location.href = `${searchPageUrl}?q=${encodeURIComponent(query)}`;
        }
    };

    return (
        <Combobox
            store={combobox}
            onOptionSubmit={handleOptionSubmit}
            withinPortal={false}
        >
            <Combobox.Target>
                <TextInput
                    placeholder={labels.placeholder}
                    value={query}
                    onChange={(event) => {
                        setQuery(event.currentTarget.value);
                        if (event.currentTarget.value.length >= 2) {
                            combobox.openDropdown();
                        } else {
                            combobox.closeDropdown();
                        }
                    }}
                    onFocus={() => {
                        if (query.length >= 2 && groups.length > 0) {
                            combobox.openDropdown();
                        }
                    }}
                    onBlur={() => combobox.closeDropdown()}
                    onKeyDown={handleKeyDown}
                    leftSection={<IconSearch size={16} />}
                    size="xs"
                    styles={{
                        input: {
                            width: '200px',
                            backgroundColor: 'rgba(255,255,255,0.1)',
                            border: '1px solid rgba(255,255,255,0.2)',
                            color: 'white',
                        },
                    }}
                />
            </Combobox.Target>

            <Combobox.Dropdown>
                <Combobox.Options>
                    {query.length >= 2 && !loading && !hasResults && (
                        <Combobox.Empty>{labels.noResults}</Combobox.Empty>
                    )}

                    {groups.map((group) => (
                        <Combobox.Group key={group.type} label={group.type}>
                            {group.items.map((item) => (
                                <Combobox.Option key={item.url} value={item.url}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                        <span>
                                            {TYPE_ICONS[item.type] ?? ''} {item.title}
                                        </span>
                                        {item.secondary && (
                                            <span style={{ fontSize: '0.75rem', color: '#868e96' }}>{item.secondary}</span>
                                        )}
                                    </div>
                                </Combobox.Option>
                            ))}
                        </Combobox.Group>
                    ))}

                    {hasResults && (
                        <Combobox.Option
                            value="__see_all__"
                            styles={{ option: { textAlign: 'center', fontWeight: 500, borderTop: '1px solid #dee2e6' } }}
                        >
                            {labels.seeAll} →
                        </Combobox.Option>
                    )}
                </Combobox.Options>
            </Combobox.Dropdown>
        </Combobox>
    );
};

export default NavbarSearch;
