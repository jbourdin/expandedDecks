/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useCallback, useEffect, useState } from 'react';
import { Badge, Loader, NativeSelect, Table, Text, UnstyledButton } from '@mantine/core';

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

interface DiffResult {
    added: CardDiff[];
    removed: CardDiff[];
    changed: CardDiff[];
    unchanged: CardDiff[];
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

const DeckVersionCompare: React.FC<DeckVersionCompareProps> = ({ shortTag, versions, labels }) => {
    const [fromVersion, setFromVersion] = useState<number>(versions.length > 1 ? versions[1].versionNumber : versions[0].versionNumber);
    const [toVersion, setToVersion] = useState<number>(versions[0].versionNumber);
    const [diff, setDiff] = useState<DiffResult | null>(null);
    const [loading, setLoading] = useState(false);
    const [showUnchanged, setShowUnchanged] = useState(false);

    const fetchDiff = useCallback(async () => {
        if (fromVersion === toVersion) {
            setDiff(null);

            return;
        }

        setLoading(true);
        try {
            const response = await fetch(`/api/deck/${shortTag}/versions/compare?from=${fromVersion}&to=${toVersion}`);
            if (response.ok) {
                const data = (await response.json()) as DiffResult;
                setDiff(data);
            }
        } finally {
            setLoading(false);
        }
    }, [shortTag, fromVersion, toVersion]);

    useEffect(() => {
        void fetchDiff();
    }, [fetchDiff]);

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

            {!loading && diff !== null && hasChanges && (
                <Table striped highlightOnHover>
                    <Table.Thead>
                        <Table.Tr>
                            <Table.Th>{labels.card}</Table.Th>
                            <Table.Th style={{ width: 56 }}>{labels.set}</Table.Th>
                            <Table.Th style={{ width: 40 }}>#</Table.Th>
                            <Table.Th style={{ width: 100 }}>{labels.qty}</Table.Th>
                            <Table.Th style={{ width: 100 }}>{labels.change}</Table.Th>
                        </Table.Tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {diff.added.map((card) => (
                            <Table.Tr key={`added-${card.setCode}-${card.cardNumber}`} bg="green.0">
                                <Table.Td><CardName card={card} /></Table.Td>
                                <Table.Td><code>{card.setCode}</code></Table.Td>
                                <Table.Td>{card.cardNumber}</Table.Td>
                                <Table.Td>+{card.quantity}</Table.Td>
                                <Table.Td><Badge color="green" variant="light">{labels.added}</Badge></Table.Td>
                            </Table.Tr>
                        ))}
                        {diff.removed.map((card) => (
                            <Table.Tr key={`removed-${card.setCode}-${card.cardNumber}`} bg="red.0">
                                <Table.Td><CardName card={card} /></Table.Td>
                                <Table.Td><code>{card.setCode}</code></Table.Td>
                                <Table.Td>{card.cardNumber}</Table.Td>
                                <Table.Td>-{card.quantity}</Table.Td>
                                <Table.Td><Badge color="red" variant="light">{labels.removed}</Badge></Table.Td>
                            </Table.Tr>
                        ))}
                        {diff.changed.map((card) => (
                            <Table.Tr key={`changed-${card.setCode}-${card.cardNumber}`} bg="yellow.0">
                                <Table.Td><CardName card={card} detail={`${card.oldQuantity} → ${card.newQuantity}`} /></Table.Td>
                                <Table.Td><code>{card.setCode}</code></Table.Td>
                                <Table.Td>{card.cardNumber}</Table.Td>
                                <Table.Td>{card.oldQuantity} → {card.newQuantity}</Table.Td>
                                <Table.Td><Badge color="yellow" variant="light">{labels.changed}</Badge></Table.Td>
                            </Table.Tr>
                        ))}
                    </Table.Tbody>
                </Table>
            )}

            {!loading && diff !== null && !hasChanges && (
                <Text c="dimmed" ta="center" py="md">{labels.noChanges}</Text>
            )}

            {!loading && diff !== null && diff.unchanged.length > 0 && (
                <div className="mt-3">
                    <UnstyledButton
                        onClick={() => setShowUnchanged(!showUnchanged)}
                        className="text-muted small"
                    >
                        {labels.showUnchanged} ({diff.unchanged.length})
                    </UnstyledButton>

                    {showUnchanged && (
                        <Table striped mt="sm">
                            <Table.Thead>
                                <Table.Tr>
                                    <Table.Th>{labels.card}</Table.Th>
                                    <Table.Th style={{ width: 56 }}>{labels.set}</Table.Th>
                                    <Table.Th style={{ width: 40 }}>#</Table.Th>
                                    <Table.Th style={{ width: 100 }}>{labels.qty}</Table.Th>
                                    <Table.Th style={{ width: 100 }}>{labels.change}</Table.Th>
                                </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {diff.unchanged.map((card) => (
                                    <Table.Tr key={`unchanged-${card.setCode}-${card.cardNumber}`}>
                                        <Table.Td><CardName card={card} /></Table.Td>
                                        <Table.Td><code>{card.setCode}</code></Table.Td>
                                        <Table.Td>{card.cardNumber}</Table.Td>
                                        <Table.Td>{card.quantity}</Table.Td>
                                        <Table.Td><Badge color="gray" variant="light">{labels.unchanged}</Badge></Table.Td>
                                    </Table.Tr>
                                ))}
                            </Table.Tbody>
                        </Table>
                    )}
                </div>
            )}
        </div>
    );
};

export default DeckVersionCompare;
