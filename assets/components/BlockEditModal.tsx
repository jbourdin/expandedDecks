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
import {
    Modal,
    Card,
    Tabs,
    TextInput,
    NumberInput,
    SegmentedControl,
    Stack,
    Group,
    Button,
    Text,
    Textarea,
    Divider,
    ActionIcon,
} from '@mantine/core';
import { IconPlus, IconTrash } from '@tabler/icons-react';

type BlockData = Record<string, unknown>;
type TranslationsMap = Record<string, Record<string, Record<string, unknown>>>;

interface BlockTypeInfo {
    value: string;
    label: string;
    icon: string;
}

interface Labels {
    [key: string]: string;
}

interface CtaButton {
    label: string;
    route: string;
    style: string;
}

interface CarouselItem {
    image: string;
    alt: string;
    link: string;
    startAt: string | null;
    endAt: string | null;
}

interface BlockEditModalProps {
    block: BlockData;
    blockIndex: number;
    translations: TranslationsMap;
    supportedLocales: string[];
    labels: Labels;
    blockTypes: BlockTypeInfo[];
    onSave: (updatedBlock: BlockData, updatedTranslations: Record<string, Record<string, unknown>>) => void;
    onClose: () => void;
}

export default function BlockEditModal({
    block,
    blockIndex,
    translations,
    supportedLocales,
    labels,
    blockTypes,
    onSave,
    onClose,
}: BlockEditModalProps) {
    const [editedBlock, setEditedBlock] = useState<BlockData>({ ...block });
    const [editedTranslations, setEditedTranslations] = useState<Record<string, Record<string, unknown>>>(() => {
        const initial: Record<string, Record<string, unknown>> = {};
        for (const locale of supportedLocales) {
            initial[locale] = { ...(translations[locale]?.[String(blockIndex)] ?? {}) };
        }
        return initial;
    });

    const label = useCallback((key: string) => labels[key] ?? key, [labels]);
    const blockType = block.type as string;
    const typeInfo = blockTypes.find((blockTypeInfo) => blockTypeInfo.value === blockType);

    const updateBlock = (key: string, value: unknown) => {
        setEditedBlock((previous) => ({ ...previous, [key]: value }));
    };

    const updateTranslation = (locale: string, key: string, value: unknown) => {
        setEditedTranslations((previous) => ({
            ...previous,
            [locale]: { ...(previous[locale] ?? {}), [key]: value },
        }));
    };

    const getCtaButtons = (locale: string): CtaButton[] => {
        const buttons = editedTranslations[locale]?.ctaButtons;
        if (Array.isArray(buttons)) {
            return buttons as CtaButton[];
        }
        return [];
    };

    const setCtaButtons = (locale: string, buttons: CtaButton[]) => {
        updateTranslation(locale, 'ctaButtons', buttons);
    };

    const handleSave = () => {
        onSave(editedBlock, editedTranslations);
    };

    const widthValue = editedBlock.columnWidth === null || editedBlock.columnWidth === undefined
        ? 'full'
        : String(editedBlock.columnWidth);

    return (
        <Modal
            opened
            onClose={onClose}
            title={`${label('edit')}: ${typeInfo?.label ?? blockType}`}
            size="lg"
            centered
        >
            <Stack gap="md">
                {/* Common settings */}
                <Text size="sm" fw={600}>{label('blockSettings')}</Text>

                <SegmentedControl
                    value={widthValue}
                    onChange={(value) => updateBlock('columnWidth', value === 'full' ? null : Number(value))}
                    data={[
                        { label: label('fullWidth'), value: 'full' },
                        { label: '6/12', value: '6' },
                        { label: '4/12', value: '4' },
                        { label: '3/12', value: '3' },
                        { label: '8/12', value: '8' },
                    ]}
                    fullWidth
                />

                <Group grow>
                    <TextInput
                        label={label('startAt')}
                        type="datetime-local"
                        value={(editedBlock.startAt as string) ?? ''}
                        onChange={(event) => updateBlock('startAt', event.currentTarget.value || null)}
                    />
                    <TextInput
                        label={label('endAt')}
                        type="datetime-local"
                        value={(editedBlock.endAt as string) ?? ''}
                        onChange={(event) => updateBlock('endAt', event.currentTarget.value || null)}
                    />
                </Group>

                <TextInput
                    label={label('cssClasses')}
                    value={(editedBlock.cssClasses as string) ?? ''}
                    onChange={(event) => updateBlock('cssClasses', event.currentTarget.value || null)}
                />

                {/* Type-specific non-translatable settings */}
                {blockType === 'richText' && (
                    <TextInput
                        label={label('pageSlug')}
                        value={(editedBlock.pageSlug as string) ?? ''}
                        onChange={(event) => updateBlock('pageSlug', event.currentTarget.value || null)}
                        placeholder="welcome"
                    />
                )}

                {blockType === 'latestPages' && (
                    <Group grow>
                        <TextInput
                            label={label('categorySlug')}
                            value={(editedBlock.categorySlug as string) ?? ''}
                            onChange={(event) => updateBlock('categorySlug', event.currentTarget.value || null)}
                            placeholder="news"
                        />
                        <NumberInput
                            label={label('limit')}
                            value={(editedBlock.limit as number) ?? 5}
                            onChange={(value) => updateBlock('limit', value)}
                            min={1}
                            max={20}
                        />
                    </Group>
                )}

                {/* Carousel items */}
                {blockType === 'carousel' && (
                    <>
                        <Divider />
                        <Text size="sm" fw={600}>{label('carouselItems')}</Text>
                        {(Array.isArray(editedBlock.items) ? editedBlock.items as CarouselItem[] : []).map((carouselItem, itemIndex) => (
                            <Card key={itemIndex} withBorder padding="xs">
                                <Group grow align="end">
                                    <TextInput
                                        label={label('image')}
                                        value={carouselItem.image ?? ''}
                                        onChange={(event) => {
                                            const items = [...(editedBlock.items as CarouselItem[])];
                                            items[itemIndex] = { ...items[itemIndex], image: event.currentTarget.value };
                                            updateBlock('items', items);
                                        }}
                                        placeholder="https://..."
                                    />
                                    <TextInput
                                        label={label('altText')}
                                        value={carouselItem.alt ?? ''}
                                        onChange={(event) => {
                                            const items = [...(editedBlock.items as CarouselItem[])];
                                            items[itemIndex] = { ...items[itemIndex], alt: event.currentTarget.value };
                                            updateBlock('items', items);
                                        }}
                                    />
                                </Group>
                                <Group grow align="end" mt="xs">
                                    <TextInput
                                        label={label('link')}
                                        value={carouselItem.link ?? ''}
                                        onChange={(event) => {
                                            const items = [...(editedBlock.items as CarouselItem[])];
                                            items[itemIndex] = { ...items[itemIndex], link: event.currentTarget.value };
                                            updateBlock('items', items);
                                        }}
                                        placeholder="/events or https://..."
                                    />
                                    <ActionIcon
                                        color="red"
                                        variant="subtle"
                                        mt="lg"
                                        onClick={() => {
                                            const items = (editedBlock.items as CarouselItem[]).filter((_, filterIndex) => filterIndex !== itemIndex);
                                            updateBlock('items', items);
                                        }}
                                    >
                                        <IconTrash size={14} />
                                    </ActionIcon>
                                </Group>
                            </Card>
                        ))}
                        <Button
                            variant="light"
                            size="xs"
                            leftSection={<IconPlus size={14} />}
                            onClick={() => {
                                const items = Array.isArray(editedBlock.items) ? [...(editedBlock.items as CarouselItem[])] : [];
                                items.push({ image: '', alt: '', link: '', startAt: null, endAt: null });
                                updateBlock('items', items);
                            }}
                        >
                            {label('addItem')}
                        </Button>
                    </>
                )}

                {/* Translatable content */}
                {(blockType === 'hero' || blockType === 'richText' || blockType === 'featuredDeck' || blockType === 'featuredEvent') && (
                    <>
                        <Divider />
                        <Text size="sm" fw={600}>{label('translatableContent')}</Text>

                        <Tabs defaultValue={supportedLocales[0]}>
                            <Tabs.List>
                                {supportedLocales.map((locale) => (
                                    <Tabs.Tab key={locale} value={locale}>
                                        {locale.toUpperCase()}
                                    </Tabs.Tab>
                                ))}
                            </Tabs.List>

                            {supportedLocales.map((locale) => (
                                <Tabs.Panel key={locale} value={locale} pt="sm">
                                    <Stack gap="sm">
                                        {/* Hero fields */}
                                        {blockType === 'hero' && (
                                            <>
                                                <TextInput
                                                    label={label('title')}
                                                    value={(editedTranslations[locale]?.title as string) ?? ''}
                                                    onChange={(event) => updateTranslation(locale, 'title', event.currentTarget.value)}
                                                />
                                                <TextInput
                                                    label={label('subtitle')}
                                                    value={(editedTranslations[locale]?.subtitle as string) ?? ''}
                                                    onChange={(event) => updateTranslation(locale, 'subtitle', event.currentTarget.value)}
                                                />
                                                <Text size="sm" fw={500}>CTA Buttons</Text>
                                                {getCtaButtons(locale).map((ctaButton, ctaIndex) => (
                                                    <Group key={ctaIndex} grow align="end">
                                                        <TextInput
                                                            label={label('ctaLabel')}
                                                            value={ctaButton.label}
                                                            onChange={(event) => {
                                                                const buttons = [...getCtaButtons(locale)];
                                                                buttons[ctaIndex] = { ...buttons[ctaIndex], label: event.currentTarget.value };
                                                                setCtaButtons(locale, buttons);
                                                            }}
                                                        />
                                                        <TextInput
                                                            label={label('ctaRoute')}
                                                            value={ctaButton.route}
                                                            onChange={(event) => {
                                                                const buttons = [...getCtaButtons(locale)];
                                                                buttons[ctaIndex] = { ...buttons[ctaIndex], route: event.currentTarget.value };
                                                                setCtaButtons(locale, buttons);
                                                            }}
                                                        />
                                                        <SegmentedControl
                                                            value={ctaButton.style}
                                                            onChange={(value) => {
                                                                const buttons = [...getCtaButtons(locale)];
                                                                buttons[ctaIndex] = { ...buttons[ctaIndex], style: value };
                                                                setCtaButtons(locale, buttons);
                                                            }}
                                                            data={[
                                                                { label: 'Primary', value: 'primary' },
                                                                { label: 'Outline', value: 'outline' },
                                                            ]}
                                                        />
                                                        <ActionIcon
                                                            color="red"
                                                            variant="subtle"
                                                            onClick={() => {
                                                                const buttons = getCtaButtons(locale).filter((_, filterIndex) => filterIndex !== ctaIndex);
                                                                setCtaButtons(locale, buttons);
                                                            }}
                                                        >
                                                            <IconTrash size={14} />
                                                        </ActionIcon>
                                                    </Group>
                                                ))}
                                                <Button
                                                    variant="light"
                                                    size="xs"
                                                    leftSection={<IconPlus size={14} />}
                                                    onClick={() => {
                                                        setCtaButtons(locale, [...getCtaButtons(locale), { label: '', route: '', style: 'primary' }]);
                                                    }}
                                                >
                                                    {label('addCta')}
                                                </Button>
                                            </>
                                        )}

                                        {/* Rich text fields */}
                                        {blockType === 'richText' && (
                                            <Textarea
                                                label={label('content')}
                                                value={(editedTranslations[locale]?.content as string) ?? ''}
                                                onChange={(event) => updateTranslation(locale, 'content', event.currentTarget.value)}
                                                minRows={6}
                                                autosize
                                            />
                                        )}

                                        {/* Featured deck / event fields */}
                                        {(blockType === 'featuredDeck' || blockType === 'featuredEvent') && (
                                            <>
                                                <TextInput
                                                    label={label('title')}
                                                    value={(editedTranslations[locale]?.title as string) ?? ''}
                                                    onChange={(event) => updateTranslation(locale, 'title', event.currentTarget.value)}
                                                />
                                                <Textarea
                                                    label={label('description')}
                                                    value={(editedTranslations[locale]?.description as string) ?? ''}
                                                    onChange={(event) => updateTranslation(locale, 'description', event.currentTarget.value)}
                                                    minRows={3}
                                                    autosize
                                                />
                                            </>
                                        )}
                                    </Stack>
                                </Tabs.Panel>
                            ))}
                        </Tabs>
                    </>
                )}

                {/* Modal actions */}
                <Group justify="flex-end" mt="md">
                    <Button variant="default" onClick={onClose}>
                        {label('cancel')}
                    </Button>
                    <Button onClick={handleSave}>
                        {label('confirm')}
                    </Button>
                </Group>
            </Stack>
        </Modal>
    );
}
