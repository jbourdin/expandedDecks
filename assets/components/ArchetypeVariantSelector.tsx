/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { ActionIcon, Button, CopyButton, Group, Loader, Select, SegmentedControl, Stack, Text, Tooltip } from '@mantine/core';
import { useMediaQuery } from '@mantine/hooks';
import { IconArrowsExchange, IconCheck, IconCopy, IconShare } from '@tabler/icons-react';
import { initCardHover } from '../shared/card-hover';
import { displayCardNumber } from '../utils/cardNumber';
import CardImageModal, { type FlatCard } from './CardImageModal';
import CardMosaicGrid from './CardMosaicGrid';

/**
 * @see docs/features.md F18.16 — Archetype detail: variant selector
 */

interface CardData {
    cardName: string;
    quantity: number;
    setCode: string;
    cardNumber: string;
    cardType: string;
    trainerSubtype: string | null;
    imageUrl: string | null;
}

interface VariantData {
    id: number;
    shortTag: string;
    name: string;
    canonical: boolean;
    outdated: boolean;
    latestSetCode: string | null;
    latestSetName: string | null;
    enrichmentPending: boolean;
    sprites: string[];
    description: string | null;
    mosaicUrl: string | null;
    rawList: string | null;
    groupedCards: Record<string, CardData[]>;
}

interface Labels {
    viewTable: string;
    viewMosaic: string;
    mosaicAlt: string;
    sectionPokemon: string;
    sectionTrainer: string;
    sectionEnergy: string;
    tableQty: string;
    tableCard: string;
    tableSet: string;
    moreVariants: string;
    copyList: string;
    copied: string;
    outdatedBadge: string;
    groupCurrent: string;
    groupOutdated: string;
    shareMosaic: string;
    enrichmentPending: string;
    copyTag: string;
    copyTagCopied: string;
    copyCardTag: string;
    compareVariants: string;
}

interface ArchetypeVariantSelectorProps {
    variants: VariantData[];
    labels: Labels;
    archetypeSlug: string;
    canCopyTag: boolean;
}

type ViewMode = 'table' | 'mosaic';

const SECTION_LABELS: Record<string, keyof Labels> = {
    pokemon: 'sectionPokemon',
    trainer: 'sectionTrainer',
    energy: 'sectionEnergy',
};

const SELECT_MIN_WIDTH = 220;
const ROW_GAP = 8;

function SpriteList({ slugs, height = 20 }: { slugs: string[]; height?: number }) {
    return (
        <>
            {slugs.slice(0, 3).map((slug) => (
                <img
                    key={slug}
                    src={`/sprites/pokemon/${slug}.png`}
                    alt={slug}
                    style={{ height, width: 'auto', marginRight: 2, verticalAlign: 'middle' }}
                />
            ))}
        </>
    );
}

function CardSection({ title, cards, labels, onCardClick, canCopyTag }: {
    title: string;
    cards: CardData[];
    labels: Labels;
    onCardClick?: (card: CardData) => void;
    canCopyTag: boolean;
}) {
    return (
        <div className="mb-3">
            <h6 className="text-muted text-uppercase small fw-bold">{title}</h6>
            <table className="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th style={{ width: '40px' }}>{labels.tableQty}</th>
                        <th>{labels.tableCard}</th>
                        <th style={{ width: '60px' }}>{labels.tableSet}</th>
                        <th style={{ width: '40px' }}>#</th>
                        {canCopyTag && <th style={{ width: '32px' }} />}
                    </tr>
                </thead>
                <tbody>
                    {cards.map((card, index) => (
                        <tr key={index}>
                            <td>{card.quantity}</td>
                            <td>
                                {card.imageUrl && onCardClick ? (
                                    <span className="card-name-link" role="button" tabIndex={0} onClick={() => onCardClick(card)} onKeyDown={(event) => {
                                        if (event.key === 'Enter' || event.key === ' ') {
                                            event.preventDefault();
                                            onCardClick(card);
                                        }
                                    }}>
                                        {card.cardName}
                                    </span>
                                ) : (
                                    card.cardName
                                )}
                            </td>
                            <td><span className="badge bg-secondary">{card.setCode}</span></td>
                            <td>{displayCardNumber(card.cardNumber)}</td>
                            {canCopyTag && (
                                <td>
                                    <CopyButton value={`[[card:${card.setCode}-${card.cardNumber}]]`} timeout={2000}>
                                        {({ copied, copy }) => (
                                            <Tooltip label={copied ? labels.copyTagCopied : labels.copyCardTag} withArrow>
                                                <ActionIcon variant="subtle" color={copied ? 'teal' : 'gray'} size="xs" onClick={copy}>
                                                    <IconCopy size={14} />
                                                </ActionIcon>
                                            </Tooltip>
                                        )}
                                    </CopyButton>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function CardTable({ groupedCards, labels, onCardClick, canCopyTag }: {
    groupedCards: Record<string, CardData[]>;
    labels: Labels;
    onCardClick?: (card: CardData) => void;
    canCopyTag: boolean;
}) {
    const sections = Object.entries(groupedCards);

    if (sections.length === 0) {
        return null;
    }

    const midpoint = Math.ceil(sections.length / 2);
    const leftSections = sections.slice(0, midpoint);
    const rightSections = sections.slice(midpoint);

    return (
        <div className="row">
            <div className="col-md-6">
                {leftSections.map(([type, cards]) => (
                    <CardSection key={type} title={labels[SECTION_LABELS[type]] ?? type} cards={cards} labels={labels} onCardClick={onCardClick} canCopyTag={canCopyTag} />
                ))}
            </div>
            <div className="col-md-6">
                {rightSections.map(([type, cards]) => (
                    <CardSection key={type} title={labels[SECTION_LABELS[type]] ?? type} cards={cards} labels={labels} onCardClick={onCardClick} canCopyTag={canCopyTag} />
                ))}
            </div>
        </div>
    );
}

/**
 * Renders a variant button (shared between measurement and visible rows).
 */
function VariantButton({ variant, index, outdated, selected, onSelect }: {
    variant: VariantData;
    index: number;
    outdated: boolean;
    selected: boolean;
    onSelect: (index: number) => void;
}) {
    return (
        <Button
            variant={outdated
                ? (selected ? 'filled' : 'light')
                : (selected ? 'filled' : 'outline')}
            color={outdated ? 'gray' : undefined}
            size="sm"
            radius="xl"
            onClick={() => onSelect(index)}
            leftSection={variant.sprites.length > 0
                ? <span style={outdated ? { filter: 'grayscale(70%)' } : undefined}><SpriteList slugs={variant.sprites} height={22} /></span>
                : undefined}
        >
            {variant.outdated && variant.latestSetCode && (
                <span className="badge bg-secondary" style={{ marginRight: 6, fontStyle: 'normal', fontSize: '0.7em' }}>{variant.latestSetCode}</span>
            )}
            <span style={outdated ? { fontStyle: 'italic' } : undefined}>{variant.name}</span>
        </Button>
    );
}

/**
 * A single row of variant buttons that dynamically overflows excess items
 * into a Select dropdown based on available container width.
 */
function OverflowRow({ items, selectedIndex, onSelect, outdated, placeholder }: {
    items: { variant: VariantData; index: number }[];
    selectedIndex: number;
    onSelect: (index: number) => void;
    outdated: boolean;
    placeholder: string;
}) {
    const wrapperRef = useRef<HTMLDivElement>(null);
    const measureRef = useRef<HTMLDivElement>(null);
    const [visibleCount, setVisibleCount] = useState(items.length);

    const measureVisibleCount = useCallback((): number => {
        const measure = measureRef.current;
        const wrapper = wrapperRef.current;
        if (!measure || !wrapper) return items.length;

        const children = Array.from(measure.children) as HTMLElement[];
        if (children.length === 0) return items.length;

        const available = wrapper.getBoundingClientRect().width;
        let total = 0;
        let count = 0;

        for (let i = 0; i < children.length; i++) {
            const childWidth = children[i].getBoundingClientRect().width;
            const accumulated = total + childWidth + (i > 0 ? ROW_GAP : 0);

            if (i === children.length - 1 && accumulated <= available) {
                count = children.length;
                break;
            }

            if (accumulated + ROW_GAP + SELECT_MIN_WIDTH > available) break;
            total = accumulated;
            count++;
        }

        return Math.max(1, count);
    }, [items.length]);

    useLayoutEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect -- DOM measurement requires setState after layout
        setVisibleCount(measureVisibleCount());
    }, [measureVisibleCount]);

    useEffect(() => {
        const wrapper = wrapperRef.current;
        if (!wrapper) return;

        const observer = new ResizeObserver(() => {
             
            setVisibleCount(measureVisibleCount());
        });
        observer.observe(wrapper);

        return () => observer.disconnect();
    }, [measureVisibleCount]);

    const buttonItems = items.slice(0, visibleCount);
    const dropdownItems = items.slice(visibleCount);
    const selectedDropdownItem = dropdownItems.find(({ index }) => index === selectedIndex);
    const selectedDropdownVariant = selectedDropdownItem?.variant;

    return (
        <div ref={wrapperRef} style={{ position: 'relative' }}>
            {/* Hidden measurement row — renders ALL buttons to capture natural widths */}
            <div
                ref={measureRef}
                aria-hidden
                style={{ position: 'absolute', visibility: 'hidden', pointerEvents: 'none', display: 'flex', gap: ROW_GAP, flexWrap: 'nowrap' }}
            >
                {items.map(({ variant, index }) => (
                    <VariantButton key={variant.id} variant={variant} index={index} outdated={outdated} selected={index === selectedIndex} onSelect={onSelect} />
                ))}
            </div>

            {/* Visible row */}
            <Group gap="xs" wrap="nowrap">
                {buttonItems.map(({ variant, index }) => (
                    <VariantButton key={variant.id} variant={variant} index={index} outdated={outdated} selected={index === selectedIndex} onSelect={onSelect} />
                ))}
                {dropdownItems.length > 0 && (
                    <Select
                        data={dropdownItems.map(({ variant, index }) => ({
                            value: String(index),
                            label: variant.outdated && variant.latestSetCode
                                ? `${variant.latestSetCode} ${variant.name}`
                                : variant.name,
                        }))}
                        value={selectedDropdownItem ? String(selectedIndex) : null}
                        onChange={(value) => {
                            if (value) {
                                onSelect(Number(value));
                            }
                        }}
                        size="sm"
                        clearable
                        placeholder={placeholder}
                        style={{ minWidth: SELECT_MIN_WIDTH }}
                        leftSection={
                            selectedDropdownVariant && selectedDropdownVariant.sprites.length > 0
                                ? <span style={outdated ? { filter: 'grayscale(70%)' } : undefined}><SpriteList slugs={selectedDropdownVariant.sprites} height={20} /></span>
                                : undefined
                        }
                        leftSectionWidth={
                            selectedDropdownVariant && selectedDropdownVariant.sprites.length > 0
                                ? selectedDropdownVariant.sprites.length * 22 + 8
                                : undefined
                        }
                        styles={outdated ? { input: { fontStyle: 'italic' } } : { input: { fontWeight: 600 } }}
                        renderOption={({ option }) => {
                            const item = dropdownItems.find(({ index }) => String(index) === option.value);
                            if (!item) return <span>{option.label}</span>;
                            const { variant } = item;

                            return (
                                <Group gap={6} wrap="nowrap" style={outdated ? { opacity: 0.5 } : undefined}>
                                    {variant.sprites.length > 0 && (
                                        <span style={outdated ? { filter: 'grayscale(70%)' } : undefined}>
                                            <SpriteList slugs={variant.sprites} height={20} />
                                        </span>
                                    )}
                                    {variant.outdated && variant.latestSetCode && (
                                        <span className="badge bg-secondary" style={{ fontSize: '0.65em' }}>{variant.latestSetCode}</span>
                                    )}
                                    <span style={outdated ? { fontStyle: 'italic' } : undefined}>{variant.name}</span>
                                </Group>
                            );
                        }}
                    />
                )}
            </Group>
        </div>
    );
}

/**
 * Desktop variant selector: compact pill buttons with sprites.
 * Current variants appear on one row; outdated variants on a separate row
 * with a grayed-out visual treatment. Each row dynamically overflows excess
 * buttons into a Select dropdown based on available width.
 */
function DesktopSelector({ variants, selectedIndex, onSelect, labels }: {
    variants: VariantData[];
    selectedIndex: number;
    onSelect: (index: number) => void;
    labels: Labels;
}) {
    const indexed = variants.map((variant, index) => ({ variant, index }));
    const currentVariants = indexed.filter(({ variant }) => !variant.outdated);
    const outdatedVariants = indexed.filter(({ variant }) => variant.outdated);

    return (
        <Stack gap="xs">
            {currentVariants.length > 0 && (
                <OverflowRow items={currentVariants} selectedIndex={selectedIndex} onSelect={onSelect} outdated={false} placeholder={labels.moreVariants} />
            )}
            {outdatedVariants.length > 0 && (
                <OverflowRow items={outdatedVariants} selectedIndex={selectedIndex} onSelect={onSelect} outdated={true} placeholder={labels.moreVariants} />
            )}
        </Stack>
    );
}

/**
 * Mobile variant selector: dropdown with sprites in both the input and options.
 * Groups current and outdated variants with a separator header.
 */
function MobileSelector({ variants, selectedIndex, onSelect, labels }: {
    variants: VariantData[];
    selectedIndex: number;
    onSelect: (index: number) => void;
    labels: Labels;
}) {
    const hasOutdated = variants.some((variant) => variant.outdated);

    const data = hasOutdated
        ? [
            {
                group: labels.groupCurrent,
                items: variants
                    .map((variant, index) => ({ variant, index }))
                    .filter(({ variant }) => !variant.outdated)
                    .map(({ variant, index }) => ({
                        value: String(index),
                        label: variant.name,
                    })),
            },
            {
                group: labels.groupOutdated,
                items: variants
                    .map((variant, index) => ({ variant, index }))
                    .filter(({ variant }) => variant.outdated)
                    .map(({ variant, index }) => ({
                        value: String(index),
                        label: variant.latestSetCode
                            ? `${variant.latestSetCode} ${variant.name}`
                            : variant.name,
                    })),
            },
        ]
        : variants.map((variant, index) => ({
            value: String(index),
            label: variant.name,
        }));

    return (
        <Select
            data={data}
            value={String(selectedIndex)}
            onChange={(value) => {
                if (value) {
                    onSelect(Number(value));
                }
            }}
            size="sm"
            leftSection={
                variants[selectedIndex].sprites.length > 0
                    ? <SpriteList slugs={variants[selectedIndex].sprites} height={20} />
                    : undefined
            }
            leftSectionWidth={variants[selectedIndex].sprites.length > 0 ? variants[selectedIndex].sprites.length * 22 + 8 : undefined}
            styles={{ input: { fontWeight: 600 } }}
            renderOption={({ option }) => {
                const variant = variants[Number(option.value)];

                return (
                    <Group gap={6} wrap="nowrap" style={variant.outdated ? { opacity: 0.5 } : undefined}>
                        {variant.sprites.length > 0 && <SpriteList slugs={variant.sprites} height={20} />}
                        {variant.outdated && variant.latestSetCode && (
                            <span className="badge bg-secondary" style={{ fontSize: '0.65em' }}>{variant.latestSetCode}</span>
                        )}
                        <span style={variant.outdated ? { fontStyle: 'italic' } : undefined}>{variant.name}</span>
                    </Group>
                );
            }}
        />
    );
}

/**
 * Resolve the initial variant index from the URL hash, falling back to the canonical variant.
 *
 * @see docs/features.md F2.25 — Archetype variant URL anchors & enhanced archetype tags
 */
function resolveInitialIndex(variants: VariantData[]): number {
    const hash = window.location.hash.slice(1);
    if (hash) {
        const hashIndex = variants.findIndex((variant) => variant.shortTag === hash);
        if (hashIndex >= 0) {
            return hashIndex;
        }
    }

    const canonicalIndex = variants.findIndex((variant) => variant.canonical);

    return canonicalIndex >= 0 ? canonicalIndex : 0;
}

export default function ArchetypeVariantSelector({ variants, labels, archetypeSlug, canCopyTag }: ArchetypeVariantSelectorProps) {
    const [selectedIndex, setSelectedIndex] = useState(() => resolveInitialIndex(variants));
    const containerRef = useRef<HTMLDivElement>(null);
    const isMobile = useMediaQuery('(max-width: 767.98px)');
    const [viewMode, setViewMode] = useState<ViewMode>('mosaic');
    const [cardModalOpen, setCardModalOpen] = useState(false);
    const [cardModalIndex, setCardModalIndex] = useState(0);
    const [mosaicCopied, setMosaicCopied] = useState(false);
    const mosaicCopyTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    /**
     * @see docs/features.md F2.25 — Archetype variant URL anchors & enhanced archetype tags
     */
    const handleSelectVariant = useCallback((index: number) => {
        setSelectedIndex(index);
        const shortTag = variants[index]?.shortTag;
        if (shortTag) {
            history.replaceState(null, '', `#${shortTag}`);
        }
    }, [variants]);

    // Sync variant selection when the URL hash changes (browser back/forward).
    useEffect(() => {
        const handleHashChange = () => {
            const hash = window.location.hash.slice(1);
            if (hash) {
                const hashIndex = variants.findIndex((variant) => variant.shortTag === hash);
                if (hashIndex >= 0) {
                    setSelectedIndex(hashIndex);
                }
            }
        };

        window.addEventListener('hashchange', handleHashChange);

        return () => window.removeEventListener('hashchange', handleHashChange);
    }, [variants]);

    const selectedVariant = variants[selectedIndex];
    const groupedCards = selectedVariant?.groupedCards;

    const flatCards: FlatCard[] = useMemo(() => {
        const entries: FlatCard[] = [];
        const order = ['pokemon', 'trainer', 'energy'];

        for (const section of order) {
            for (const card of groupedCards?.[section] ?? []) {
                if (card.imageUrl) {
                    entries.push({ imageUrl: card.imageUrl, cardName: card.cardName, quantity: card.quantity });
                }
            }
        }

        return entries;
    }, [groupedCards]);

    const handleCardClick = (card: CardData): void => {
        const index = flatCards.findIndex((entry) => entry.imageUrl === card.imageUrl && entry.cardName === card.cardName);
        if (index >= 0) {
            setCardModalIndex(index);
            setCardModalOpen(true);
        }
    };

    const handleShareMosaic = useCallback(async () => {
        const mosaicUrl = selectedVariant?.mosaicUrl;
        if (!mosaicUrl) return;

        await navigator.clipboard.writeText(window.location.origin + mosaicUrl);
        setMosaicCopied(true);

        if (mosaicCopyTimeout.current) {
            clearTimeout(mosaicCopyTimeout.current);
        }

        mosaicCopyTimeout.current = setTimeout(() => setMosaicCopied(false), 2000);
    }, [selectedVariant?.mosaicUrl]);

    // Re-initialize card hover after every render — description HTML contains
    // .card-hover elements from [[card:...]] tags, and they get recreated on
    // variant switch via dangerouslySetInnerHTML. Use requestAnimationFrame
    // to ensure the DOM is fully committed before scanning for elements.
    useEffect(() => {
        requestAnimationFrame(() => {
            initCardHover();
        });
    }, [selectedIndex, viewMode]);

    if (variants.length === 0) {
        return null;
    }

    const hasCards = Object.keys(selectedVariant?.groupedCards ?? {}).length > 0;

    return (
        <div ref={containerRef}>
            {/* Variant selector */}
            {variants.length > 1 && (
                <div className="mb-3">
                    {isMobile ? (
                        <MobileSelector variants={variants} selectedIndex={selectedIndex} onSelect={handleSelectVariant} labels={labels} />
                    ) : (
                        <DesktopSelector variants={variants} selectedIndex={selectedIndex} onSelect={handleSelectVariant} labels={labels} />
                    )}
                </div>
            )}

            {/* Outdated badge */}
            {selectedVariant.outdated && selectedVariant.latestSetCode && (
                <div className="alert alert-secondary py-2 px-3 mb-3 d-flex align-items-center gap-2" role="status">
                    <span className="badge bg-secondary">{selectedVariant.latestSetCode}</span>
                    <span className="text-muted small">
                        {selectedVariant.latestSetName && <>{selectedVariant.latestSetName} — </>}
                        {labels.outdatedBadge}
                    </span>
                </div>
            )}

            {/* Description */}
            {selectedVariant.description && (
                <div className="cms-content mb-3" dangerouslySetInnerHTML={{ __html: selectedVariant.description }} />
            )}

            {/* View mode toggle + copy button + card display */}
            {hasCards && (
                <Stack gap="sm">
                    <Group justify="space-between" align="center">
                        <SegmentedControl
                            value={viewMode}
                            onChange={(value) => setViewMode(value as ViewMode)}
                            data={[
                                { label: labels.viewTable, value: 'table' },
                                { label: labels.viewMosaic, value: 'mosaic' },
                            ]}
                            size="xs"
                        />
                        <Group gap="xs">
                            {selectedVariant.rawList && (
                                <CopyButton value={selectedVariant.rawList} timeout={2000}>
                                    {({ copied, copy }) => (
                                        <Button
                                            variant={copied ? 'filled' : 'outline'}
                                            color={copied ? 'teal' : 'gray'}
                                            size="sm"
                                            onClick={copy}
                                        >
                                            {copied ? labels.copied : labels.copyList}
                                        </Button>
                                    )}
                                </CopyButton>
                            )}
                            {canCopyTag && selectedVariant.shortTag && (
                                <CopyButton value={`[[archetype:${archetypeSlug}:${selectedVariant.shortTag}]]`} timeout={2000}>
                                    {({ copied, copy }) => (
                                        <Tooltip label={copied ? labels.copyTagCopied : labels.copyTag}>
                                            <ActionIcon variant="subtle" color={copied ? 'teal' : 'gray'} size="lg" onClick={copy}>
                                                <IconCopy size={18} />
                                            </ActionIcon>
                                        </Tooltip>
                                    )}
                                </CopyButton>
                            )}
                            {selectedVariant.mosaicUrl && (
                                <Tooltip label={mosaicCopied ? labels.copied : labels.shareMosaic}>
                                    <ActionIcon variant="subtle" color={mosaicCopied ? 'teal' : 'gray'} size="lg" onClick={handleShareMosaic}>
                                        {mosaicCopied ? <IconCheck size={18} /> : <IconShare size={18} />}
                                    </ActionIcon>
                                </Tooltip>
                            )}
                            {variants.length >= 2 && selectedVariant.shortTag && (() => {
                                const canonicalIndex = variants.findIndex((variant) => variant.canonical);
                                const otherIndex = canonicalIndex >= 0 && canonicalIndex !== selectedIndex ? canonicalIndex : (selectedIndex === 0 ? 1 : 0);
                                const otherVariant = variants[otherIndex];

                                return otherVariant?.shortTag ? (
                                    <Tooltip label={labels.compareVariants}>
                                        <ActionIcon
                                            variant="subtle"
                                            color="gray"
                                            size="lg"
                                            component="a"
                                            href={`/archetypes/${archetypeSlug}/compare/${selectedVariant.shortTag}/${otherVariant.shortTag}`}
                                        >
                                            <IconArrowsExchange size={18} />
                                        </ActionIcon>
                                    </Tooltip>
                                ) : null;
                            })()}
                        </Group>
                    </Group>

                    {selectedVariant.enrichmentPending && (
                        <div className="card shadow-sm">
                            <div className="card-body text-center py-5">
                                <Loader size="sm" className="mb-2" />
                                <Text size="sm" c="dimmed">{labels.enrichmentPending}</Text>
                            </div>
                        </div>
                    )}

                    {!selectedVariant.enrichmentPending && viewMode === 'table' && (
                        <CardTable groupedCards={selectedVariant.groupedCards} labels={labels} onCardClick={handleCardClick} canCopyTag={canCopyTag} />
                    )}

                    {!selectedVariant.enrichmentPending && viewMode === 'mosaic' && (
                        <CardMosaicGrid
                            groupedCards={selectedVariant.groupedCards}
                            mosaicAltLabel={`${labels.mosaicAlt} \u2014 ${selectedVariant.name}`}
                        />
                    )}
                </Stack>
            )}

            <CardImageModal
                opened={cardModalOpen}
                cards={flatCards}
                currentIndex={cardModalIndex}
                onClose={() => setCardModalOpen(false)}
                onNavigate={setCardModalIndex}
            />
        </div>
    );
}
