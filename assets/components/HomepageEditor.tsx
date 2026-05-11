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
import { Button, Group, Stack, Card, Text, Modal, SimpleGrid, UnstyledButton, Alert, TextInput, Textarea } from '@mantine/core';
import { IconPlus, IconDeviceFloppy, IconCheck, IconGripVertical, IconTrash, IconPencil } from '@tabler/icons-react';
import Sortable from 'sortablejs';
import { useEffect, useRef } from 'react';
import BlockEditModal from './BlockEditModal';
import ImageUrlField from './ImageUrlField';

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
type MetaMap = Record<string, { title: string; ogDescription: string }>;

interface CategoryInfo {
    id: number;
    name: string;
}

interface HomepageEditorProps {
    saveUrl: string;
    previewUrl: string;
    uploadUrl: string;
    channelCode: string;
    supportedLocales: string[];
    initialBlocks: BlockData[];
    initialTranslations: TranslationsMap;
    initialOgImage: string;
    initialMeta: MetaMap;
    blockTypes: BlockTypeInfo[];
    categories: CategoryInfo[];
    labels: Labels;
}

export default function HomepageEditor({
    saveUrl,
    uploadUrl,
    channelCode,
    supportedLocales,
    initialBlocks,
    initialTranslations,
    initialOgImage,
    initialMeta,
    blockTypes,
    categories,
    labels,
}: HomepageEditorProps) {
    const [blocks, setBlocks] = useState<BlockData[]>(initialBlocks);
    const [translations, setTranslations] = useState<TranslationsMap>(initialTranslations);
    const [ogImage, setOgImage] = useState<string>(initialOgImage);
    const [meta, setMeta] = useState<MetaMap>(initialMeta);
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
                const { oldIndex, newIndex, item, from } = event;
                if (oldIndex === undefined || newIndex === undefined || oldIndex === newIndex) {
                    return;
                }

                // Revert SortableJS DOM manipulation — let React handle the re-render
                from.removeChild(item);
                if (from.children[oldIndex]) {
                    from.insertBefore(item, from.children[oldIndex]);
                } else {
                    from.appendChild(item);
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
        const defaultColumnWidth = (typeValue === 'featuredDeck' || typeValue === 'featuredEvent') ? 6 : null;
        const newBlock: BlockData = {
            type: typeValue,
            columnWidth: defaultColumnWidth,
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

    const updateMeta = (locale: string, field: 'title' | 'ogDescription', value: string) => {
        setMeta((previous) => ({
            ...previous,
            [locale]: {
                title: previous[locale]?.title ?? '',
                ogDescription: previous[locale]?.ogDescription ?? '',
                [field]: value,
            },
        }));
    };

    const handleSave = async () => {
        setSaving(true);
        setSaved(false);
        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ blocks, translations, channelCode, ogImage, meta }),
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
            {/* Layout-level settings */}
            <Card shadow="xs" padding="sm" withBorder>
                <Text size="sm" fw={500} mb="xs">{label('layoutSettings')}</Text>
                <ImageUrlField
                    label={label('ogImage')}
                    value={ogImage}
                    onChange={setOgImage}
                    uploadUrl={uploadUrl}
                />
                {labels.ogImageHelp && (
                    <Text size="xs" c="dimmed" mt={4}>{label('ogImageHelp')}</Text>
                )}
            </Card>

            {/* Per-locale page metadata: HTML <title> and Open Graph description */}
            <Card shadow="xs" padding="sm" withBorder>
                <Text size="sm" fw={500} mb="xs">{label('metadata')}</Text>
                <Stack gap="md">
                    {supportedLocales.map((locale) => (
                        <Stack key={locale} gap="xs">
                            <Text size="xs" fw={500} tt="uppercase" c="dimmed">{locale}</Text>
                            <TextInput
                                label={label('metaTitle')}
                                description={labels.metaTitleHelp || undefined}
                                value={meta[locale]?.title ?? ''}
                                onChange={(event) => updateMeta(locale, 'title', event.currentTarget.value)}
                                maxLength={255}
                            />
                            <Textarea
                                label={label('metaOgDescription')}
                                description={labels.metaOgDescriptionHelp || undefined}
                                value={meta[locale]?.ogDescription ?? ''}
                                onChange={(event) => updateMeta(locale, 'ogDescription', event.currentTarget.value)}
                                autosize
                                minRows={2}
                                maxRows={4}
                            />
                        </Stack>
                    ))}
                </Stack>
            </Card>

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
                    categories={categories}
                    uploadUrl={uploadUrl}
                    onSave={(updatedBlock, updatedTranslations) => handleSaveBlock(editingIndex, updatedBlock, updatedTranslations)}
                    onClose={() => setEditingIndex(null)}
                />
            )}
        </Stack>
    );
}
