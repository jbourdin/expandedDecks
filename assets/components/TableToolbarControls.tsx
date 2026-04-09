/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React from 'react';
import { ActionIcon, Tooltip } from '@mantine/core';
import { RichTextEditor } from '@mantine/tiptap';
import type { Editor } from '@tiptap/core';
import {
    IconColumnInsertRight,
    IconColumnRemove,
    IconRowInsertBottom,
    IconRowRemove,
    IconTable,
    IconTableOff,
    IconTablePlus,
} from '@tabler/icons-react';

/**
 * @see docs/features.md F17.8 — Table support in rich text editor
 */

interface TableToolbarControlsProps {
    editor: Editor;
}

export default function TableToolbarControls({ editor }: TableToolbarControlsProps) {
    const isInTable = editor.can().addRowAfter();

    return (
        <>
            <RichTextEditor.ControlsGroup>
                <Tooltip label="Insert table">
                    <ActionIcon
                        variant="default"
                        size="sm"
                        onClick={() => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()}
                        aria-label="Insert table"
                    >
                        <IconTablePlus size={16} />
                    </ActionIcon>
                </Tooltip>
            </RichTextEditor.ControlsGroup>

            <RichTextEditor.ControlsGroup>
                <Tooltip label="Add row after">
                    <ActionIcon
                        variant="default"
                        size="sm"
                        onClick={() => editor.chain().focus().addRowAfter().run()}
                        disabled={!isInTable}
                        aria-label="Add row after"
                    >
                        <IconRowInsertBottom size={16} />
                    </ActionIcon>
                </Tooltip>
                <Tooltip label="Remove row">
                    <ActionIcon
                        variant="default"
                        size="sm"
                        onClick={() => editor.chain().focus().deleteRow().run()}
                        disabled={!isInTable}
                        aria-label="Remove row"
                    >
                        <IconRowRemove size={16} />
                    </ActionIcon>
                </Tooltip>
                <Tooltip label="Add column after">
                    <ActionIcon
                        variant="default"
                        size="sm"
                        onClick={() => editor.chain().focus().addColumnAfter().run()}
                        disabled={!isInTable}
                        aria-label="Add column after"
                    >
                        <IconColumnInsertRight size={16} />
                    </ActionIcon>
                </Tooltip>
                <Tooltip label="Remove column">
                    <ActionIcon
                        variant="default"
                        size="sm"
                        onClick={() => editor.chain().focus().deleteColumn().run()}
                        disabled={!isInTable}
                        aria-label="Remove column"
                    >
                        <IconColumnRemove size={16} />
                    </ActionIcon>
                </Tooltip>
                <Tooltip label="Toggle header row">
                    <ActionIcon
                        variant="default"
                        size="sm"
                        onClick={() => editor.chain().focus().toggleHeaderRow().run()}
                        disabled={!isInTable}
                        aria-label="Toggle header row"
                    >
                        <IconTable size={16} />
                    </ActionIcon>
                </Tooltip>
                <Tooltip label="Delete table">
                    <ActionIcon
                        variant="default"
                        size="sm"
                        onClick={() => editor.chain().focus().deleteTable().run()}
                        disabled={!isInTable}
                        aria-label="Delete table"
                    >
                        <IconTableOff size={16} />
                    </ActionIcon>
                </Tooltip>
            </RichTextEditor.ControlsGroup>
        </>
    );
}
