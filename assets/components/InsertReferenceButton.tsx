/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useCallback, useState } from 'react';
import { ActionIcon, Group, Popover, TextInput, Tooltip } from '@mantine/core';
import { IconCheck } from '@tabler/icons-react';
import type { Editor } from '@tiptap/react';

/**
 * @see docs/features.md F17.6 — Toolbar buttons to insert reference tags
 */

interface InsertReferenceButtonProps {
    editor: Editor;
    icon: React.ReactNode;
    label: string;
    placeholder: string;
    validate: (value: string) => boolean;
    nodeType: string;
    attrName: string;
}

export default function InsertReferenceButton({
    editor,
    icon,
    label,
    placeholder,
    validate,
    nodeType,
    attrName,
}: InsertReferenceButtonProps) {
    const [opened, setOpened] = useState(false);
    const [value, setValue] = useState('');
    const [error, setError] = useState(false);

    const handleSubmit = useCallback(() => {
        const trimmed = value.trim();
        if (!trimmed || !validate(trimmed)) {
            setError(true);

            return;
        }

        editor
            .chain()
            .focus()
            .insertContent({ type: nodeType, attrs: { [attrName]: trimmed } })
            .run();

        setValue('');
        setError(false);
        setOpened(false);
    }, [value, validate, editor, nodeType, attrName]);

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
                        placeholder={placeholder}
                        value={value}
                        onChange={(event) => {
                            setValue(event.currentTarget.value);
                            setError(false);
                        }}
                        onKeyDown={handleKeyDown}
                        error={error}
                        autoFocus
                        style={{ width: 160 }}
                    />
                    <ActionIcon size="sm" variant="filled" onClick={handleSubmit} aria-label="Insert">
                        <IconCheck size={14} />
                    </ActionIcon>
                </Group>
            </Popover.Dropdown>
        </Popover>
    );
}
