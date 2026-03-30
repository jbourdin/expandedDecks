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
import type { Editor } from '@tiptap/core';

/**
 * @see docs/features.md F17.7 — Image float and alignment
 */

interface ImageAlignButtonProps {
    editor: Editor;
    icon: React.ReactNode;
    label: string;
    cssClass: string | null;
}

export default function ImageAlignButton({ editor, icon, label, cssClass }: ImageAlignButtonProps) {
    const isActive = editor.isActive('image', { cssClass });

    const handleClick = () => {
        editor.chain().focus().updateAttributes('image', { cssClass }).run();
    };

    return (
        <Tooltip label={label}>
            <ActionIcon
                variant={isActive ? 'filled' : 'default'}
                size="sm"
                onClick={handleClick}
                aria-label={label}
            >
                {icon}
            </ActionIcon>
        </Tooltip>
    );
}
