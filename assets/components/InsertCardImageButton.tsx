/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useCallback, useState } from 'react';
import { ActionIcon, Group, Loader, Popover, TextInput, Tooltip } from '@mantine/core';
import { IconCheck } from '@tabler/icons-react';
import type { Editor } from '@tiptap/react';

/**
 * Toolbar button that prompts for a card reference (e.g. "UPR-100"),
 * resolves it to a TCGdex image URL via the backend, and inserts the
 * image into the editor.
 *
 * @see docs/features.md F17.8 — Insert card image from reference in rich text editor
 */

const CARD_PATTERN = /^[A-Za-z0-9-]+$/;

interface InsertCardImageButtonProps {
    editor: Editor;
    icon: React.ReactNode;
    label: string;
}

export default function InsertCardImageButton({ editor, icon, label }: InsertCardImageButtonProps) {
    const [opened, setOpened] = useState(false);
    const [value, setValue] = useState('');
    const [error, setError] = useState<string | false>(false);
    const [loading, setLoading] = useState(false);

    const handleSubmit = useCallback(async () => {
        const trimmed = value.trim();
        if (!trimmed || !CARD_PATTERN.test(trimmed)) {
            setError('Invalid reference');

            return;
        }

        setLoading(true);
        setError(false);

        try {
            const response = await fetch(`/api/card/image-url?reference=${encodeURIComponent(trimmed)}`);

            if (!response.ok) {
                const data = await response.json();
                setError((data.error as string) ?? 'Card not found');
                setLoading(false);

                return;
            }

            const data = await response.json();
            const url = data.url as string;

            editor
                .chain()
                .focus()
                .setImage({ src: url, alt: trimmed, width: 180 })
                .run();

            setValue('');
            setError(false);
            setOpened(false);
        } catch {
            setError('Network error');
        } finally {
            setLoading(false);
        }
    }, [value, editor]);

    const handleKeyDown = useCallback(
        (event: React.KeyboardEvent) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                handleSubmit();
            } else if (event.key === 'Escape') {
                setOpened(false);
                setValue('');
                setError(false);
            }
        },
        [handleSubmit],
    );

    return (
        <Popover opened={opened} onChange={setOpened} position="bottom" withArrow trapFocus>
            <Popover.Target>
                <Tooltip label={label}>
                    <ActionIcon
                        variant="default"
                        size="sm"
                        onClick={() => setOpened((previous) => !previous)}
                        aria-label={label}
                    >
                        {icon}
                    </ActionIcon>
                </Tooltip>
            </Popover.Target>
            <Popover.Dropdown>
                <Group gap="xs">
                    <TextInput
                        size="xs"
                        placeholder="SET-NUM"
                        value={value}
                        onChange={(event) => {
                            setValue(event.currentTarget.value);
                            setError(false);
                        }}
                        onKeyDown={handleKeyDown}
                        error={error}
                        autoFocus
                        style={{ width: 160 }}
                        disabled={loading}
                    />
                    <ActionIcon size="sm" variant="filled" onClick={handleSubmit} aria-label="Insert" disabled={loading}>
                        {loading ? <Loader size={14} /> : <IconCheck size={14} />}
                    </ActionIcon>
                </Group>
            </Popover.Dropdown>
        </Popover>
    );
}
