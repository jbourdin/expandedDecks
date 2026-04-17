/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useCallback, useEffect, useState } from 'react';
import { Loader, NativeSelect, Table, Text } from '@mantine/core';
import { initCardHover } from '../shared/card-hover';

/**
 * @see docs/features.md F2.9 — Deck version history
 */

interface VersionInfo {
    versionNumber: number;
    createdAt: string;
    cardCount: number;
}

interface CardDiff {
    cardName: string;
    setCode: string;
    cardNumber: string;
    quantity?: number;
    oldQuantity?: number;
    newQuantity?: number;
    cardType: string;
    imageUrl?: string | null;
}

interface UnifiedEntry {
    cardName: string;
    setCode: string;
    cardNumber: string;
    oldQuantity: number;
    newQuantity: number;
    delta: number;
    status: 'added' | 'removed' | 'changed' | 'unchanged';
    cardType: string;
    trainerSubtype: string | null;
    imageUrl?: string | null;
}

interface DiffResult {
    added: CardDiff[];
    removed: CardDiff[];
    changed: CardDiff[];
    unchanged: CardDiff[];
    unified: UnifiedEntry[];
}

interface Labels {
    from: string;
    to: string;
    added: string;
    removed: string;
    changed: string;
    unchanged: string;
    showUnchanged: string;
    noChanges: string;
    card: string;
    set: string;
    qty: string;
    change: string;
}

interface DeckVersionCompareProps {
    shortTag: string;
    compareUrl?: string;
    versions: VersionInfo[];
    labels: Labels;
}

/**
 * Renders a card name with an image hover preview (desktop) using the
 * existing `.card-hover` / `.card-hover-img` CSS from app.scss.
 *
 * An optional `detail` string (e.g. quantity change "3 → 4") is shown
 * beneath the card name on hover.
 */
const CardName: React.FC<{ card: CardDiff; detail?: string }> = ({ card, detail }) => {
    if (!card.imageUrl) {
        return <>{card.cardName}</>;
    }

    return (
        <span className="card-hover" data-quantity={detail ?? card.quantity ?? ''}>
            {card.cardName}
            <img className="card-hover-img" src={card.imageUrl} alt={card.cardName} />
        </span>
    );
};

const DeckVersionCompare: React.FC<DeckVersionCompareProps> = ({ shortTag, compareUrl, versions, labels }) => {
    const [fromVersion, setFromVersion] = useState<number>(versions.length > 1 ? versions[1].versionNumber : versions[0].versionNumber);
    const [toVersion, setToVersion] = useState<number>(versions[0].versionNumber);
    const [diff, setDiff] = useState<DiffResult | null>(null);
    const [loading, setLoading] = useState(false);

    const fetchDiff = useCallback(async () => {
        if (fromVersion === toVersion) {
            setDiff(null);

            return;
        }

        setLoading(true);
        try {
            const baseUrl = compareUrl ?? `/api/deck/${shortTag}/versions/compare`;
            const response = await fetch(`${baseUrl}?from=${fromVersion}&to=${toVersion}`);
            if (response.ok) {
                const data = (await response.json()) as DiffResult;
                setDiff(data);
            }
        } finally {
            setLoading(false);
        }
    }, [shortTag, compareUrl, fromVersion, toVersion]);

    useEffect(() => {
        void fetchDiff();
    }, [fetchDiff]);

    // Initialize card hover positioning after React renders new card elements
    useEffect(() => {
        if (diff) {
            initCardHover();
        }
    }, [diff]);

    const versionOptions = versions.map((version) => ({
        value: String(version.versionNumber),
        label: `v${version.versionNumber} — ${version.createdAt} (${version.cardCount} cards)`,
    }));

    const hasChanges = diff !== null && (diff.added.length > 0 || diff.removed.length > 0 || diff.changed.length > 0);

    return (
        <div>
            <div className="row g-3 mb-3">
                <div className="col-md-6">
                    <NativeSelect
                        label={labels.from}
                        data={versionOptions}
                        value={String(fromVersion)}
                        onChange={(event) => setFromVersion(Number(event.currentTarget.value))}
                    />
                </div>
                <div className="col-md-6">
                    <NativeSelect
                        label={labels.to}
                        data={versionOptions}
                        value={String(toVersion)}
                        onChange={(event) => setToVersion(Number(event.currentTarget.value))}
                    />
                </div>
            </div>

            {loading && (
                <div className="text-center py-4">
                    <Loader size="sm" />
                </div>
            )}

            {fromVersion === toVersion && !loading && (
                <Text c="dimmed" ta="center" py="md">{labels.noChanges}</Text>
            )}

            {!loading && diff !== null && !hasChanges && (
                <Text c="dimmed" ta="center" py="md">{labels.noChanges}</Text>
            )}

            {!loading && diff !== null && hasChanges && diff.unified && (
                <Table striped highlightOnHover>
                    <Table.Thead>
                        <Table.Tr>
                            <Table.Th style={{ width: 50 }}>{labels.qty}</Table.Th>
                            <Table.Th>{labels.card}</Table.Th>
                            <Table.Th style={{ width: 56 }}>{labels.set}</Table.Th>
                            <Table.Th style={{ width: 40 }}>#</Table.Th>
                            <Table.Th style={{ width: 60 }}></Table.Th>
                        </Table.Tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {diff.unified.map((entry) => {
                            const rowColor = entry.status === 'added' ? 'green.0'
                                : entry.status === 'removed' ? 'red.0'
                                    : entry.delta > 0 ? 'green.0'
                                        : entry.delta < 0 ? 'red.0'
                                            : undefined;

                            const deltaText = entry.delta > 0 ? `+${entry.delta}`
                                : entry.delta < 0 ? String(entry.delta)
                                    : '';

                            const deltaColor = entry.delta > 0 ? 'green' : entry.delta < 0 ? 'red' : undefined;

                            return (
                                <Table.Tr key={`${entry.status}-${entry.setCode}-${entry.cardNumber}`} bg={rowColor}>
                                    <Table.Td fw={600}>{entry.newQuantity > 0 ? entry.newQuantity : '—'}</Table.Td>
                                    <Table.Td><CardName card={entry} /></Table.Td>
                                    <Table.Td><code>{entry.setCode}</code></Table.Td>
                                    <Table.Td>{entry.cardNumber}</Table.Td>
                                    <Table.Td ta="right">
                                        {deltaText && <Text component="span" size="sm" fw={600} c={deltaColor}>{deltaText}</Text>}
                                    </Table.Td>
                                </Table.Tr>
                            );
                        })}
                    </Table.Tbody>
                </Table>
            )}
        </div>
    );
};

export default DeckVersionCompare;
