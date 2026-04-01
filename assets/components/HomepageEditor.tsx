/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F10.5 — Homepage block editor (admin UI)
 */

import { useState, useCallback } from 'react';
import { Button, Group, Stack, Card, Text, Modal, SimpleGrid, UnstyledButton, Alert } from '@mantine/core';
import { IconPlus, IconDeviceFloppy, IconCheck, IconGripVertical, IconTrash, IconPencil } from '@tabler/icons-react';
import Sortable from 'sortablejs';
import { useEffect, useRef } from 'react';
import BlockEditModal from './BlockEditModal';

interface BlockTypeInfo {
    value: string;
    label: string;
    icon: string;
}

interface Labels {
    [key: string]: string;
}

type BlockData = Record<string, unknown>;
type TranslationsMap = Record<string, Record<string, Record<string, unknown>>>;

interface HomepageEditorProps {
    saveUrl: string;
    previewUrl: string;
    supportedLocales: string[];
    initialBlocks: BlockData[];
    initialTranslations: TranslationsMap;
    blockTypes: BlockTypeInfo[];
    labels: Labels;
}

export default function HomepageEditor({
    saveUrl,
    supportedLocales,
    initialBlocks,
    initialTranslations,
    blockTypes,
    labels,
}: HomepageEditorProps) {
    const [blocks, setBlocks] = useState<BlockData[]>(initialBlocks);
    const [translations, setTranslations] = useState<TranslationsMap>(initialTranslations);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [addModalOpen, setAddModalOpen] = useState(false);
    const [editingIndex, setEditingIndex] = useState<number | null>(null);
    const sortableRef = useRef<HTMLDivElement>(null);
    const sortableInstance = useRef<Sortable | null>(null);

    const label = useCallback((key: string) => labels[key] ?? key, [labels]);

    // Initialize SortableJS
    useEffect(() => {
        if (!sortableRef.current) {
            return;
        }

        sortableInstance.current = Sortable.create(sortableRef.current, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'opacity-50',
            onEnd: (event) => {
                const { oldIndex, newIndex } = event;
                if (oldIndex === undefined || newIndex === undefined || oldIndex === newIndex) {
                    return;
                }

                setBlocks((previous) => {
                    const updated = [...previous];
                    const [moved] = updated.splice(oldIndex, 1);
                    updated.splice(newIndex, 0, moved);
                    return updated;
                });

                // Reindex translations
                setTranslations((previous) => {
                    const result: TranslationsMap = {};
                    for (const locale of supportedLocales) {
                        const localeTranslations = previous[locale] ?? {};
                        const reindexed: Record<string, Record<string, unknown>> = {};

                        // Build old-to-new index mapping
                        const order = Array.from({ length: blocks.length }, (_, index) => index);
                        const [movedItem] = order.splice(oldIndex!, 1);
                        order.splice(newIndex!, 0, movedItem);

                        for (let newIdx = 0; newIdx < order.length; newIdx++) {
                            const oldIdx = order[newIdx];
                            const entry = localeTranslations[String(oldIdx)];
                            if (entry) {
                                reindexed[String(newIdx)] = entry;
                            }
                        }

                        result[locale] = reindexed;
                    }
                    return result;
                });
            },
        });

        return () => {
            sortableInstance.current?.destroy();
        };
    }, [blocks.length, supportedLocales]);

    const handleAddBlock = (typeValue: string) => {
        const newBlock: BlockData = {
            type: typeValue,
            columnWidth: null,
            cssClasses: null,
            startAt: null,
            endAt: null,
        };
        setBlocks((previous) => [...previous, newBlock]);
        setAddModalOpen(false);
        setEditingIndex(blocks.length);
    };

    const handleDeleteBlock = (index: number) => {
        setBlocks((previous) => previous.filter((_, filterIndex) => filterIndex !== index));
        setTranslations((previous) => {
            const result: TranslationsMap = {};
            for (const locale of supportedLocales) {
                const localeTranslations = previous[locale] ?? {};
                const reindexed: Record<string, Record<string, unknown>> = {};
                let newIdx = 0;
                for (let oldIdx = 0; oldIdx < blocks.length; oldIdx++) {
                    if (oldIdx === index) {
                        continue;
                    }
                    const entry = localeTranslations[String(oldIdx)];
                    if (entry) {
                        reindexed[String(newIdx)] = entry;
                    }
                    newIdx++;
                }
                result[locale] = reindexed;
            }
            return result;
        });
    };

    const handleSaveBlock = (index: number, updatedBlock: BlockData, updatedTranslations: Record<string, Record<string, unknown>>) => {
        setBlocks((previous) => {
            const updated = [...previous];
            updated[index] = updatedBlock;
            return updated;
        });
        setTranslations((previous) => {
            const result = { ...previous };
            for (const locale of supportedLocales) {
                result[locale] = {
                    ...(result[locale] ?? {}),
                    [String(index)]: updatedTranslations[locale] ?? {},
                };
            }
            return result;
        });
        setEditingIndex(null);
    };

    const handleSave = async () => {
        setSaving(true);
        setSaved(false);
        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ blocks, translations }),
            });
            if (response.ok) {
                setSaved(true);
                setTimeout(() => setSaved(false), 3000);
            }
        } finally {
            setSaving(false);
        }
    };

    const getBlockTypeInfo = (typeValue: string): BlockTypeInfo | undefined =>
        blockTypes.find((blockType) => blockType.value === typeValue);

    return (
        <Stack gap="md">
            {/* Block list */}
            <div ref={sortableRef}>
                {blocks.map((block, index) => {
                    const typeInfo = getBlockTypeInfo(block.type as string);
                    const width = block.columnWidth as number | null;

                    return (
                        <Card key={`block-${index}`} shadow="xs" padding="sm" mb="sm" withBorder data-block-index={index}>
                            <Group justify="space-between" wrap="nowrap">
                                <Group gap="sm" wrap="nowrap">
                                    <span className="drag-handle" style={{ cursor: 'grab' }}>
                                        <IconGripVertical size={18} color="gray" />
                                    </span>
                                    <i className={`bi ${typeInfo?.icon ?? 'bi-square'}`} />
                                    <Text fw={500} size="sm">
                                        {typeInfo?.label ?? (block.type as string)}
                                    </Text>
                                    {width && (
                                        <Text size="xs" c="dimmed">
                                            col-{width}/12
                                        </Text>
                                    )}
                                </Group>
                                <Group gap="xs">
                                    <Button
                                        variant="subtle"
                                        size="compact-sm"
                                        onClick={() => setEditingIndex(index)}
                                        leftSection={<IconPencil size={14} />}
                                    >
                                        {label('edit')}
                                    </Button>
                                    <Button
                                        variant="subtle"
                                        color="red"
                                        size="compact-sm"
                                        onClick={() => handleDeleteBlock(index)}
                                        leftSection={<IconTrash size={14} />}
                                    >
                                        {label('delete')}
                                    </Button>
                                </Group>
                            </Group>
                        </Card>
                    );
                })}
            </div>

            {blocks.length === 0 && (
                <Alert color="blue" variant="light">
                    {label('noBlocks')}
                </Alert>
            )}

            {/* Grid preview */}
            {blocks.length > 0 && (
                <Card shadow="xs" padding="sm" withBorder>
                    <Text size="sm" fw={500} mb="xs">{label('gridPreview')}</Text>
                    <div className="row g-2">
                        {blocks.map((block, index) => {
                            const typeInfo = getBlockTypeInfo(block.type as string);
                            const width = block.columnWidth as number | null;
                            const colClass = width ? `col-md-${width}` : 'col-12';

                            return (
                                <div key={`preview-${index}`} className={colClass}>
                                    <div
                                        style={{
                                            background: '#e9ecef',
                                            border: '1px dashed #adb5bd',
                                            borderRadius: 4,
                                            padding: '8px 12px',
                                            textAlign: 'center',
                                            fontSize: 12,
                                        }}
                                    >
                                        <i className={`bi ${typeInfo?.icon ?? 'bi-square'} me-1`} />
                                        {typeInfo?.label ?? (block.type as string)}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </Card>
            )}

            {/* Actions */}
            <Group>
                <Button leftSection={<IconPlus size={16} />} variant="outline" onClick={() => setAddModalOpen(true)}>
                    {label('addBlock')}
                </Button>
                <Button
                    leftSection={saved ? <IconCheck size={16} /> : <IconDeviceFloppy size={16} />}
                    color={saved ? 'green' : 'blue'}
                    loading={saving}
                    onClick={handleSave}
                >
                    {saved ? label('saved') : label('save')}
                </Button>
            </Group>

            {/* Add block type picker modal */}
            <Modal opened={addModalOpen} onClose={() => setAddModalOpen(false)} title={label('selectType')} centered>
                <SimpleGrid cols={2}>
                    {blockTypes.map((blockType) => (
                        <UnstyledButton
                            key={blockType.value}
                            onClick={() => handleAddBlock(blockType.value)}
                            style={{
                                border: '1px solid #dee2e6',
                                borderRadius: 8,
                                padding: 16,
                                textAlign: 'center',
                            }}
                        >
                            <i className={`bi ${blockType.icon}`} style={{ fontSize: 24 }} />
                            <Text size="sm" mt={4}>{blockType.label}</Text>
                        </UnstyledButton>
                    ))}
                </SimpleGrid>
            </Modal>

            {/* Edit block modal */}
            {editingIndex !== null && blocks[editingIndex] && (
                <BlockEditModal
                    block={blocks[editingIndex]}
                    blockIndex={editingIndex}
                    translations={translations}
                    supportedLocales={supportedLocales}
                    labels={labels}
                    blockTypes={blockTypes}
                    onSave={(updatedBlock, updatedTranslations) => handleSaveBlock(editingIndex, updatedBlock, updatedTranslations)}
                    onClose={() => setEditingIndex(null)}
                />
            )}
        </Stack>
    );
}
