/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useCallback, useRef, useState } from 'react';
import { SegmentedControl, Textarea } from '@mantine/core';
import { RichTextEditor, Link } from '@mantine/tiptap';
import { useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { IconCards, IconStack2, IconSword } from '@tabler/icons-react';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore — tiptap-markdown types conflict with tiptap v3 private class properties
import { Markdown } from 'tiptap-markdown';
import ArchetypeReference from '../extensions/ArchetypeReference';
import CardReference from '../extensions/CardReference';
import DeckReference from '../extensions/DeckReference';
import InsertReferenceButton from './InsertReferenceButton';

/**
 * @see docs/features.md F17.1 — Rich text editor with Markdown
 */

interface MarkdownEditorProps {
    textareaSelector: string;
    initialContent: string;
    placeholder?: string;
}

type EditorMode = 'rte' | 'markdown';

const CARD_PATTERN = /^[A-Za-z0-9-]+$/;
const ARCHETYPE_PATTERN = /^[a-z0-9-]+$/;
const DECK_PATTERN = /^[A-HJ-NP-Z0-9]{6}$/;

const validateCardReference = (value: string) => CARD_PATTERN.test(value);
const validateArchetypeSlug = (value: string) => ARCHETYPE_PATTERN.test(value);
const validateDeckShortTag = (value: string) => DECK_PATTERN.test(value);

export default function MarkdownEditor({ textareaSelector, initialContent, placeholder }: MarkdownEditorProps) {
    const [mode, setMode] = useState<EditorMode>('rte');
    const [rawMarkdown, setRawMarkdown] = useState(initialContent);
    const suppressSyncRef = useRef(false);

    const syncToTextarea = useCallback((value: string) => {
        const textarea = document.querySelector<HTMLTextAreaElement>(textareaSelector);
        if (textarea) {
            textarea.value = value;
        }
    }, [textareaSelector]);

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const markdownExtension = (Markdown as any).configure({
        html: false,
        transformPastedText: true,
        transformCopiedText: true,
    });

    const editor = useEditor({
        extensions: [
            StarterKit,
            Link.configure({ openOnClick: false }),
            ArchetypeReference,
            CardReference,
            DeckReference,
            markdownExtension,
        ],
        content: initialContent,
        onUpdate: ({ editor: currentEditor }) => {
            if (suppressSyncRef.current) {
                return;
            }
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const markdown = (currentEditor.storage as any).markdown.getMarkdown() as string;
            setRawMarkdown(markdown);
            syncToTextarea(markdown);
        },
    });

    const handleModeChange = (newMode: string) => {
        const targetMode = newMode as EditorMode;

        if (targetMode === 'markdown' && editor) {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const markdown = (editor.storage as any).markdown.getMarkdown() as string;
            setRawMarkdown(markdown);
        } else if (targetMode === 'rte' && editor) {
            suppressSyncRef.current = true;
            editor.commands.setContent(rawMarkdown);
            suppressSyncRef.current = false;
        }

        setMode(targetMode);
    };

    const handleRawChange = (event: React.ChangeEvent<HTMLTextAreaElement>) => {
        const value = event.currentTarget.value;
        setRawMarkdown(value);
        syncToTextarea(value);
    };

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const editorForMantine = editor as any;

    return (
        <div>
            <SegmentedControl
                value={mode}
                onChange={handleModeChange}
                data={[
                    { label: 'Rich Text', value: 'rte' },
                    { label: 'Markdown', value: 'markdown' },
                ]}
                size="xs"
                mb="xs"
            />

            {mode === 'rte' && editor ? (
                <RichTextEditor editor={editorForMantine}>
                    <RichTextEditor.Toolbar sticky stickyOffset={0}>
                        <RichTextEditor.ControlsGroup>
                            <RichTextEditor.H2 />
                            <RichTextEditor.H3 />
                            <RichTextEditor.H4 />
                        </RichTextEditor.ControlsGroup>

                        <RichTextEditor.ControlsGroup>
                            <RichTextEditor.Bold />
                            <RichTextEditor.Italic />
                            <RichTextEditor.Strikethrough />
                            <RichTextEditor.Code />
                        </RichTextEditor.ControlsGroup>

                        <RichTextEditor.ControlsGroup>
                            <RichTextEditor.BulletList />
                            <RichTextEditor.OrderedList />
                            <RichTextEditor.Blockquote />
                            <RichTextEditor.Hr />
                        </RichTextEditor.ControlsGroup>

                        <RichTextEditor.ControlsGroup>
                            <RichTextEditor.Link />
                            <RichTextEditor.Unlink />
                        </RichTextEditor.ControlsGroup>

                        <RichTextEditor.ControlsGroup>
                            <RichTextEditor.CodeBlock />
                        </RichTextEditor.ControlsGroup>

                        <RichTextEditor.ControlsGroup>
                            <InsertReferenceButton
                                editor={editor}
                                icon={<IconCards size={16} />}
                                label="Insert card reference"
                                placeholder="SET-NUM"
                                validate={validateCardReference}
                                nodeType="cardReference"
                                attrName="reference"
                            />
                            <InsertReferenceButton
                                editor={editor}
                                icon={<IconSword size={16} />}
                                label="Insert archetype reference"
                                placeholder="slug"
                                validate={validateArchetypeSlug}
                                nodeType="archetypeReference"
                                attrName="slug"
                            />
                            <InsertReferenceButton
                                editor={editor}
                                icon={<IconStack2 size={16} />}
                                label="Insert deck reference"
                                placeholder="SHORT_TAG"
                                validate={validateDeckShortTag}
                                nodeType="deckReference"
                                attrName="shortTag"
                            />
                        </RichTextEditor.ControlsGroup>
                    </RichTextEditor.Toolbar>

                    <RichTextEditor.Content />
                </RichTextEditor>
            ) : (
                <Textarea
                    value={rawMarkdown}
                    onChange={handleRawChange}
                    placeholder={placeholder}
                    autosize
                    minRows={10}
                    styles={{ input: { fontFamily: 'monospace' } }}
                />
            )}
        </div>
    );
}
