/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React from 'react';
import { ActionIcon, Select, Tooltip } from '@mantine/core';
import { IconArrowsExchange } from '@tabler/icons-react';

/**
 * Two variant selectors for the archetype variant comparison page.
 * Navigates to the new compare URL when either selector changes.
 *
 * @see docs/features.md F2.10 — Archetype detail page
 */

interface VariantOption {
    shortTag: string;
    name: string;
    outdated: boolean;
    latestSetCode: string | null;
    sprites: string[];
}

interface VariantComparePickerProps {
    variants: VariantOption[];
    selectedTagA: string;
    selectedTagB: string;
    archetypeSlug: string;
    labelFrom: string;
    labelTo: string;
    labelSwap: string;
    labelGroupCurrent: string;
    labelGroupOutdated: string;
}

function buildSelectData(variants: VariantOption[], labelGroupCurrent: string, labelGroupOutdated: string) {
    const hasOutdated = variants.some((variant) => variant.outdated);

    if (!hasOutdated) {
        return variants.map((variant) => ({
            value: variant.shortTag,
            label: variant.name,
        }));
    }

    return [
        {
            group: labelGroupCurrent,
            items: variants
                .filter((variant) => !variant.outdated)
                .map((variant) => ({
                    value: variant.shortTag,
                    label: variant.name,
                })),
        },
        {
            group: labelGroupOutdated,
            items: variants
                .filter((variant) => variant.outdated)
                .map((variant) => ({
                    value: variant.shortTag,
                    label: variant.latestSetCode
                        ? `${variant.latestSetCode} ${variant.name}`
                        : variant.name,
                })),
        },
    ];
}

function SpritePreview({ variant }: { variant: VariantOption | undefined }) {
    if (!variant || variant.sprites.length === 0) {
        return null;
    }

    return (
        <span style={{ display: 'inline-flex', gap: 2 }}>
            {variant.sprites.slice(0, 3).map((slug) => (
                <img
                    key={slug}
                    src={`/build/sprites/pokemon/${slug}.png`}
                    alt=""
                    style={{ height: 22, imageRendering: 'pixelated' }}
                />
            ))}
        </span>
    );
}

export default function VariantComparePicker({
    variants,
    selectedTagA,
    selectedTagB,
    archetypeSlug,
    labelFrom,
    labelTo,
    labelSwap,
    labelGroupCurrent,
    labelGroupOutdated,
}: VariantComparePickerProps) {
    const data = buildSelectData(variants, labelGroupCurrent, labelGroupOutdated);

    const navigate = (tagA: string, tagB: string) => {
        if (tagA && tagB) {
            window.location.href = `/archetypes/${archetypeSlug}/compare/${tagA}/${tagB}`;
        }
    };

    const handleSelectA = (value: string | null) => {
        if (!value) return;
        // If user picks the same value as side B, swap
        if (value === selectedTagB) {
            navigate(value, selectedTagA);
        } else {
            navigate(value, selectedTagB);
        }
    };

    const handleSelectB = (value: string | null) => {
        if (!value) return;
        // If user picks the same value as side A, swap
        if (value === selectedTagA) {
            navigate(selectedTagB, value);
        } else {
            navigate(selectedTagA, value);
        }
    };

    const handleSwap = () => {
        navigate(selectedTagB, selectedTagA);
    };

    const variantA = variants.find((variant) => variant.shortTag === selectedTagA);
    const variantB = variants.find((variant) => variant.shortTag === selectedTagB);

    return (
        <div className="row g-3 align-items-end">
            <div className="col">
                <label className="form-label fw-bold">{labelFrom}</label>
                <Select
                    data={data}
                    value={selectedTagA}
                    onChange={handleSelectA}
                    size="sm"
                    leftSection={<SpritePreview variant={variantA} />}
                    leftSectionWidth={variantA && variantA.sprites.length > 0 ? 22 * Math.min(variantA.sprites.length, 3) + 12 : undefined}
                />
            </div>
            <div className="col-auto pb-1">
                <Tooltip label={labelSwap}>
                    <ActionIcon variant="subtle" color="gray" size="lg" onClick={handleSwap}>
                        <IconArrowsExchange size={20} />
                    </ActionIcon>
                </Tooltip>
            </div>
            <div className="col">
                <label className="form-label fw-bold">{labelTo}</label>
                <Select
                    data={data}
                    value={selectedTagB}
                    onChange={handleSelectB}
                    size="sm"
                    leftSection={<SpritePreview variant={variantB} />}
                    leftSectionWidth={variantB && variantB.sprites.length > 0 ? 22 * Math.min(variantB.sprites.length, 3) + 12 : undefined}
                />
            </div>
        </div>
    );
}
