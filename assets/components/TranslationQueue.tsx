/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Translation queue: tabs per content type, contributor and reviewer flavors,
 * locale filter when work spans several target languages.
 *
 * @see docs/features.md F9.12 — Translation queue
 * @see docs/features.md F9.19 — Queue card tabs & locale filter
 */

import React, { useEffect, useMemo, useState } from 'react';
import { Alert, Badge, Button, Group, Loader, SegmentedControl, Table, Tabs, Text } from '@mantine/core';

export interface TranslationQueueRow {
    contentType: string;
    contentId: number;
    label: string;
    locale: string;
    state: string | null;
    sourceOutdated: boolean;
    pendingVariants: number;
    outdatedVariants: number;
}

export interface TranslationQueueData {
    pages: TranslationQueueRow[];
    archetypes: TranslationQueueRow[];
    menuCategories: TranslationQueueRow[];
    bannedCards: TranslationQueueRow[];
    stapleCards: TranslationQueueRow[];
}

export const QUEUE_TABS: (keyof TranslationQueueData)[] = ['pages', 'archetypes', 'menuCategories', 'bannedCards', 'stapleCards'];

/**
 * Distinct locales across every tab, sorted — drives the locale filter,
 * which only renders when more than one target language has work.
 */
export const localesOf = (data: TranslationQueueData): string[] => {
    const locales = new Set<string>();
    QUEUE_TABS.forEach((tab) => (data[tab] ?? []).forEach((row) => locales.add(row.locale)));
    return [...locales].sort();
};

export const filterByLocale = (rows: TranslationQueueRow[], locale: string | null): TranslationQueueRow[] =>
    locale === null ? rows : rows.filter((row) => row.locale === locale);

export interface TranslationQueueLabels {
    [key: string]: string;
}

interface TranslationQueueProps {
    queueUrl: string;
    baseUrl: string;
    labels: TranslationQueueLabels;
}

export const editUrl = (baseUrl: string, row: TranslationQueueRow): string =>
    `${baseUrl}/${row.contentType}/${row.contentId}/${row.locale}`;

export const stateLabel = (labels: TranslationQueueLabels, state: string | null): string | null => {
    switch (state) {
        case 'draft':
            return labels.stateDraft;
        case 'pending_review':
            return labels.statePendingReview;
        case 'rejected':
            return labels.stateRejected;
        default:
            return null;
    }
};

function QueueRows({ rows, baseUrl, labels }: { rows: TranslationQueueRow[]; baseUrl: string; labels: TranslationQueueLabels }) {
    if (rows.length === 0) {
        return <Text c="dimmed" py="md">{labels.empty}</Text>;
    }

    return (
        <Table striped highlightOnHover>
            <Table.Tbody>
                {rows.map((row) => (
                    <Table.Tr key={`${row.contentType}-${row.contentId}-${row.locale}`}>
                        <Table.Td>
                            <Text fw={500} component="span">{row.label}</Text>
                        </Table.Td>
                        <Table.Td>
                            <Badge variant="outline" tt="uppercase">{row.locale}</Badge>
                        </Table.Td>
                        <Table.Td>
                            <Group gap="xs">
                                {stateLabel(labels, row.state) !== null && (
                                    <Badge color={row.state === 'rejected' ? 'red' : row.state === 'pending_review' ? 'yellow' : 'gray'}>
                                        {stateLabel(labels, row.state)}
                                    </Badge>
                                )}
                                {row.sourceOutdated && <Badge color="orange">{labels.badgeOutdated}</Badge>}
                                {row.state === null && !row.sourceOutdated && row.pendingVariants === 0 && row.outdatedVariants === 0 && (
                                    <Badge color="gray" variant="light">{labels.badgeUntranslated}</Badge>
                                )}
                                {row.pendingVariants > 0 && (
                                    <Badge color="yellow" variant="light">{`${row.pendingVariants} ${labels.badgePendingVariants}`}</Badge>
                                )}
                                {row.outdatedVariants > 0 && (
                                    <Badge color="orange" variant="light">{`${row.outdatedVariants} ${labels.badgeOutdatedVariants}`}</Badge>
                                )}
                            </Group>
                        </Table.Td>
                        <Table.Td style={{ textAlign: 'right' }}>
                            <Button component="a" href={editUrl(baseUrl, row)} size="xs" variant="light">
                                {labels.open}
                            </Button>
                        </Table.Td>
                    </Table.Tr>
                ))}
            </Table.Tbody>
        </Table>
    );
}

export default function TranslationQueue({ queueUrl, baseUrl, labels }: TranslationQueueProps) {
    const [contributor, setContributor] = useState<TranslationQueueData | null>(null);
    const [reviewer, setReviewer] = useState<TranslationQueueData | null>(null);
    const [loaded, setLoaded] = useState(false);
    const [flavor, setFlavor] = useState<'contributor' | 'reviewer'>('contributor');
    const [localeFilter, setLocaleFilter] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        fetch(queueUrl, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((data: { contributor: TranslationQueueData | null; reviewer: TranslationQueueData | null }) => {
                if (cancelled) {
                    return;
                }
                setContributor(data.contributor);
                setReviewer(data.reviewer);
                if (data.contributor === null && data.reviewer !== null) {
                    setFlavor('reviewer');
                }
                setLoaded(true);
            })
            .catch(() => setLoaded(true));

        return () => {
            cancelled = true;
        };
    }, [queueUrl]);

    const active = useMemo(
        () => (flavor === 'contributor' ? contributor : reviewer),
        [flavor, contributor, reviewer],
    );

    if (!loaded) {
        return (
            <Group justify="center" py="xl">
                <Loader size="sm" />
                <Text c="dimmed">{labels.loading}</Text>
            </Group>
        );
    }

    if (active === null) {
        return <Alert color="gray">{labels.empty}</Alert>;
    }

    const locales = localesOf(active);
    const effectiveLocaleFilter = localeFilter !== null && locales.includes(localeFilter) ? localeFilter : null;

    return (
        <div>
            <Group mb="md">
                {contributor !== null && reviewer !== null && (
                    <SegmentedControl
                        value={flavor}
                        onChange={(value) => setFlavor(value === 'reviewer' ? 'reviewer' : 'contributor')}
                        data={[
                            { label: labels.flavorContributor, value: 'contributor' },
                            { label: labels.flavorReviewer, value: 'reviewer' },
                        ]}
                    />
                )}
                {locales.length > 1 && (
                    <SegmentedControl
                        value={effectiveLocaleFilter ?? 'all'}
                        onChange={(value) => setLocaleFilter(value === 'all' ? null : value)}
                        data={[
                            { label: labels.localeAll, value: 'all' },
                            ...locales.map((locale) => ({ label: locale.toUpperCase(), value: locale })),
                        ]}
                    />
                )}
            </Group>
            <Tabs defaultValue="pages">
                <Tabs.List>
                    <Tabs.Tab value="pages">{labels.tabPages}</Tabs.Tab>
                    <Tabs.Tab value="archetypes">{labels.tabArchetypes}</Tabs.Tab>
                    <Tabs.Tab value="menuCategories">{labels.tabMenuCategories}</Tabs.Tab>
                    <Tabs.Tab value="bannedCards">{labels.tabBannedCards}</Tabs.Tab>
                    <Tabs.Tab value="stapleCards">{labels.tabStapleCards}</Tabs.Tab>
                </Tabs.List>
                {QUEUE_TABS.map((tab) => (
                    <Tabs.Panel key={tab} value={tab} pt="md">
                        <QueueRows rows={filterByLocale(active[tab] ?? [], effectiveLocaleFilter)} baseUrl={baseUrl} labels={labels} />
                    </Tabs.Panel>
                ))}
            </Tabs>
        </div>
    );
}
