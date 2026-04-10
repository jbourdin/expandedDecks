/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useEffect, useRef, useState } from 'react';
import { Button, Group, Select, SegmentedControl, Stack } from '@mantine/core';
import { initCardHover } from '../shared/card-hover';

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
    description: string | null;
    mosaicUrl: string | null;
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

function CardSection({ title, cards, labels }: { title: string; cards: CardData[]; labels: Labels }) {
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
                                {card.imageUrl ? (
                                    <span className="card-hover" data-quantity={card.quantity}>
                                        {card.cardName}
                                        <img
                                            className="card-hover-img"
                                            src={card.imageUrl}
                                            alt={card.cardName}
                                            loading="lazy"
                                        />
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

function CardTable({ groupedCards, labels }: { groupedCards: Record<string, CardData[]>; labels: Labels }) {
    const sections = Object.entries(groupedCards);

    if (sections.length === 0) {
        return null;
    }

    // Split into two columns for wider layouts
    const midpoint = Math.ceil(sections.length / 2);
    const leftSections = sections.slice(0, midpoint);
    const rightSections = sections.slice(midpoint);

    return (
        <div className="row">
            <div className="col-md-6">
                {leftSections.map(([type, cards]) => (
                    <CardSection key={type} title={labels[SECTION_LABELS[type]] ?? type} cards={cards} labels={labels} />
                ))}
            </div>
            <div className="col-md-6">
                {rightSections.map(([type, cards]) => (
                    <CardSection key={type} title={labels[SECTION_LABELS[type]] ?? type} cards={cards} labels={labels} />
                ))}
            </div>
        </div>
    );
}

export default function ArchetypeVariantSelector({ variants, labels }: ArchetypeVariantSelectorProps) {
    const canonicalIndex = variants.findIndex((variant) => variant.canonical);
    const [selectedIndex, setSelectedIndex] = useState(canonicalIndex >= 0 ? canonicalIndex : 0);
    const [viewMode, setViewMode] = useState<ViewMode>('mosaic');
    const containerRef = useRef<HTMLDivElement>(null);

    // Re-initialize card hover after variant switch or view mode change
    useEffect(() => {
        if (viewMode === 'table') {
            initCardHover();
        }
    }, [selectedIndex, viewMode]);

    if (variants.length === 0) {
        return null;
    }

    const selectedVariant = variants[selectedIndex];
    const hasCards = Object.keys(selectedVariant.groupedCards).length > 0;

    // Split variants: first MAX_BUTTONS as buttons, rest in dropdown
    const buttonVariants = variants.slice(0, MAX_BUTTONS);
    const dropdownVariants = variants.slice(MAX_BUTTONS);

    return (
        <div ref={containerRef}>
            {/* Variant selector */}
            {variants.length > 1 && (
                <div className="mb-3">
                    <Group gap={4} wrap="wrap">
                        {buttonVariants.map((variant, index) => (
                            <Button
                                key={variant.id}
                                variant={index === selectedIndex ? 'filled' : 'outline'}
                                size="compact-sm"
                                radius="xl"
                                onClick={() => setSelectedIndex(index)}
                            >
                                {variant.name}
                                {variant.canonical && ' \u2605'}
                            </Button>
                        ))}
                        {dropdownVariants.length > 0 && (
                            <Select
                                placeholder={labels.moreVariants}
                                data={dropdownVariants.map((variant, index) => ({
                                    value: String(MAX_BUTTONS + index),
                                    label: variant.name,
                                }))}
                                value={selectedIndex >= MAX_BUTTONS ? String(selectedIndex) : null}
                                onChange={(value) => {
                                    if (value) {
                                        setSelectedIndex(Number(value));
                                    }
                                }}
                                size="sm"
                                clearable
                                style={{ minWidth: 200 }}
                            />
                        )}
                    </Group>
                </div>
            )}

            {/* Description */}
            {selectedVariant.description && (
                <div className="cms-content mb-3" dangerouslySetInnerHTML={{ __html: selectedVariant.description }} />
            )}

            {/* View mode toggle + card display */}
            {hasCards && (
                <Stack gap="sm">
                    <SegmentedControl
                        value={viewMode}
                        onChange={(value) => setViewMode(value as ViewMode)}
                        data={[
                            { label: labels.viewTable, value: 'table' },
                            { label: labels.viewMosaic, value: 'mosaic' },
                        ]}
                        size="xs"
                    />

                    {viewMode === 'table' && (
                        <CardTable groupedCards={selectedVariant.groupedCards} labels={labels} />
                    )}

                    {viewMode === 'mosaic' && selectedVariant.mosaicUrl && (
                        <div className="text-center">
                            <img
                                src={selectedVariant.mosaicUrl}
                                alt={`${labels.mosaicAlt} \u2014 ${selectedVariant.name}`}
                                className="img-fluid rounded shadow-sm"
                            />
                        </div>
                    )}

                    {viewMode === 'mosaic' && !selectedVariant.mosaicUrl && (
                        <p className="text-muted text-center">Mosaic not yet generated.</p>
                    )}
                </Stack>
            )}
        </div>
    );
}
