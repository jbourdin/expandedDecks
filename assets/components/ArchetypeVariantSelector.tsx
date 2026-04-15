/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ActionIcon, Button, CopyButton, Group, Loader, Select, SegmentedControl, Stack, Text, Tooltip } from '@mantine/core';
import { useMediaQuery } from '@mantine/hooks';
import { IconShare } from '@tabler/icons-react';
import { initCardHover } from '../shared/card-hover';
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
    shareMosaic: string;
    enrichmentPending: string;
}

interface ArchetypeVariantSelectorProps {
    variants: VariantData[];
    labels: Labels;
}

type ViewMode = 'table' | 'mosaic';

const SECTION_LABELS: Record<string, keyof Labels> = {
    pokemon: 'sectionPokemon',
    trainer: 'sectionTrainer',
    energy: 'sectionEnergy',
};

const MAX_BUTTONS = 5;

function SpriteList({ slugs, height = 20 }: { slugs: string[]; height?: number }) {
    return (
        <>
            {slugs.slice(0, 3).map((slug) => (
                <img
                    key={slug}
                    src={`/build/sprites/pokemon/${slug}.png`}
                    alt={slug}
                    style={{ height, width: 'auto', marginRight: 2, verticalAlign: 'middle', imageRendering: 'pixelated' }}
                />
            ))}
        </>
    );
}

function CardSection({ title, cards, labels, onCardClick }: {
    title: string;
    cards: CardData[];
    labels: Labels;
    onCardClick?: (card: CardData) => void;
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
                            <td>{card.cardNumber}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function CardTable({ groupedCards, labels, onCardClick }: {
    groupedCards: Record<string, CardData[]>;
    labels: Labels;
    onCardClick?: (card: CardData) => void;
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
                    <CardSection key={type} title={labels[SECTION_LABELS[type]] ?? type} cards={cards} labels={labels} onCardClick={onCardClick} />
                ))}
            </div>
            <div className="col-md-6">
                {rightSections.map(([type, cards]) => (
                    <CardSection key={type} title={labels[SECTION_LABELS[type]] ?? type} cards={cards} labels={labels} onCardClick={onCardClick} />
                ))}
            </div>
        </div>
    );
}

/**
 * Desktop variant selector: compact pill buttons with sprites.
 */
function DesktopSelector({ variants, selectedIndex, onSelect }: {
    variants: VariantData[];
    selectedIndex: number;
    onSelect: (index: number) => void;
}) {
    const buttonVariants = variants.slice(0, MAX_BUTTONS);
    const dropdownVariants = variants.slice(MAX_BUTTONS);

    return (
        <Group gap="xs" wrap="wrap">
            {buttonVariants.map((variant, index) => (
                <Button
                    key={variant.id}
                    variant={index === selectedIndex ? 'filled' : 'outline'}
                    size="sm"
                    radius="xl"
                    onClick={() => onSelect(index)}
                    leftSection={variant.sprites.length > 0 ? <SpriteList slugs={variant.sprites} height={22} /> : undefined}
                    opacity={variant.outdated && index !== selectedIndex ? 0.5 : 1}
                >
                    {variant.outdated && variant.latestSetCode && (
                        <span className="badge bg-secondary" style={{ marginRight: 6, fontStyle: 'normal', fontSize: '0.7em' }}>{variant.latestSetCode}</span>
                    )}
                    <span style={variant.outdated ? { fontStyle: 'italic' } : undefined}>{variant.name}</span>
                </Button>
            ))}
            {dropdownVariants.length > 0 && (
                <Select
                    data={dropdownVariants.map((variant, index) => ({
                        value: String(MAX_BUTTONS + index),
                        label: variant.outdated && variant.latestSetCode
                            ? `${variant.latestSetCode} ${variant.name}`
                            : variant.name,
                    }))}
                    value={selectedIndex >= MAX_BUTTONS ? String(selectedIndex) : null}
                    onChange={(value) => {
                        if (value) {
                            onSelect(Number(value));
                        }
                    }}
                    size="xs"
                    clearable
                    style={{ minWidth: 200 }}
                />
            )}
        </Group>
    );
}

/**
 * Mobile variant selector: dropdown with sprites in both the input and options.
 */
function MobileSelector({ variants, selectedIndex, onSelect }: {
    variants: VariantData[];
    selectedIndex: number;
    onSelect: (index: number) => void;
}) {
    return (
        <Select
            data={variants.map((variant, index) => ({
                value: String(index),
                label: variant.outdated && variant.latestSetCode
                    ? `${variant.latestSetCode} ${variant.name}`
                    : variant.name,
            }))}
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

export default function ArchetypeVariantSelector({ variants, labels }: ArchetypeVariantSelectorProps) {
    const canonicalIndex = variants.findIndex((variant) => variant.canonical);
    const [selectedIndex, setSelectedIndex] = useState(canonicalIndex >= 0 ? canonicalIndex : 0);
    const containerRef = useRef<HTMLDivElement>(null);
    const isMobile = useMediaQuery('(max-width: 767.98px)');
    const [viewMode, setViewMode] = useState<ViewMode>('mosaic');
    const [cardModalOpen, setCardModalOpen] = useState(false);
    const [cardModalIndex, setCardModalIndex] = useState(0);

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

        if (navigator.share) {
            try {
                const response = await fetch(mosaicUrl);
                const blob = await response.blob();
                const file = new File([blob], 'deck-mosaic.png', { type: 'image/png' });
                await navigator.share({ files: [file] });

                return;
            } catch {
                // Share cancelled or not supported with files
            }

            try {
                await navigator.share({ title: labels.mosaicAlt, url: mosaicUrl });

                return;
            } catch {
                // Share cancelled — fall back to clipboard
            }
        }

        await navigator.clipboard.writeText(window.location.origin + mosaicUrl);
    }, [selectedVariant?.mosaicUrl, labels.mosaicAlt]);

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
                        <MobileSelector variants={variants} selectedIndex={selectedIndex} onSelect={setSelectedIndex} />
                    ) : (
                        <DesktopSelector variants={variants} selectedIndex={selectedIndex} onSelect={setSelectedIndex} />
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
                            {selectedVariant.mosaicUrl && (
                                <Tooltip label={labels.shareMosaic}>
                                    <ActionIcon variant="subtle" color="gray" size="lg" onClick={handleShareMosaic}>
                                        <IconShare size={18} />
                                    </ActionIcon>
                                </Tooltip>
                            )}
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
                        <CardTable groupedCards={selectedVariant.groupedCards} labels={labels} onCardClick={handleCardClick} />
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
