/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Contextual translation view: field-aligned split form (English source
 * read-only left, target input right), staleness banner with word-level
 * source diff, autosaved drafts, batch submit, and moderator review mode.
 *
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 * @see docs/features.md F9.11 — Moderation review mode
 * @see docs/features.md F9.13 — Archetype + variants combined translation view
 */

import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
    Accordion,
    Alert,
    Badge,
    Button,
    Group,
    Paper,
    SegmentedControl,
    Stack,
    Text,
    Textarea,
    TextInput,
    Title,
} from '@mantine/core';
import MarkdownEditor from './MarkdownEditor';
import { csrfHeader } from '../csrf';
import { wordDiff } from '../utils/wordDiff';

export interface TranslationFieldData {
    name: string;
    kind: 'text' | 'textarea' | 'markdown';
    maxLength: number | null;
    source: string | null;
    sourceHtml: string | null;
    target: string | null;
    pinnedSource: string | null;
    latestSource: string | null;
}

export interface TranslationSectionData {
    contentType: string;
    contentId: number;
    locale: string;
    state: string | null;
    revisionId: number | null;
    reviewComment: string | null;
    sourceStale: boolean;
    fields: TranslationFieldData[];
    variantName?: string;
}

export interface TranslationEditorLabels {
    [key: string]: string;
}

interface TranslationEditorProps {
    sections: TranslationSectionData[];
    locale: string;
    baseUrl: string;
    canTranslate: boolean;
    canModerate: boolean;
    labels: TranslationEditorLabels;
}

export const fieldLabel = (labels: TranslationEditorLabels, fieldName: string): string => {
    const key = `field${fieldName.charAt(0).toUpperCase()}${fieldName.slice(1)}`;

    return labels[key] ?? fieldName;
};

function SourceDiff({ oldText, newText }: { oldText: string; newText: string }) {
    const segments = wordDiff(oldText, newText);

    return (
        <Text component="div" size="sm" style={{ whiteSpace: 'pre-wrap', fontFamily: 'var(--mantine-font-family-monospace)' }}>
            {segments.map((segment, index) => {
                if (segment.kind === 'removed') {
                    return (
                        <Text key={index} component="span" c="red" td="line-through">
                            {segment.text}
                        </Text>
                    );
                }
                if (segment.kind === 'added') {
                    return (
                        <Text key={index} component="span" c="green" fw={600}>
                            {segment.text}
                        </Text>
                    );
                }

                return <React.Fragment key={index}>{segment.text}</React.Fragment>;
            })}
        </Text>
    );
}

interface SectionProps {
    section: TranslationSectionData;
    locale: string;
    baseUrl: string;
    canTranslate: boolean;
    canModerate: boolean;
    labels: TranslationEditorLabels;
}

function TranslationSection({ section, locale, baseUrl, canTranslate, canModerate, labels }: SectionProps) {
    const [values, setValues] = useState<Record<string, string>>(() => {
        const initial: Record<string, string> = {};
        for (const field of section.fields) {
            initial[field.name] = field.target ?? '';
        }

        return initial;
    });
    const [state, setState] = useState<string | null>(section.state);
    const [revisionId, setRevisionId] = useState<number | null>(section.revisionId);
    const [dirty, setDirty] = useState(false);
    const [saveStatus, setSaveStatus] = useState<'idle' | 'saving' | 'saved'>('idle');
    const [showDiff, setShowDiff] = useState(false);
    const [sourceView, setSourceView] = useState<'rendered' | 'raw'>('rendered');
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [rejectComment, setRejectComment] = useState('');
    const saveTimer = useRef<number | null>(null);
    const valuesRef = useRef(values);
    useEffect(() => {
        valuesRef.current = values;
    }, [values]);

    const editable = canTranslate && (state === null || state === 'draft');

    const persistDraft = useCallback(async (): Promise<boolean> => {
        setSaveStatus('saving');
        try {
            const response = await fetch(`${baseUrl}/${section.contentType}/${section.contentId}/${locale}/draft`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', ...csrfHeader() },
                body: JSON.stringify({ fields: valuesRef.current }),
            });
            if (!response.ok) {
                setSaveStatus('idle');

                return false;
            }
            const data: { revisionId: number; state: string } = await response.json();
            setRevisionId(data.revisionId);
            setState(data.state);
            setSaveStatus('saved');

            return true;
        } catch {
            setSaveStatus('idle');

            return false;
        }
    }, [baseUrl, section.contentType, section.contentId, locale]);

    const scheduleSave = useCallback(() => {
        if (saveTimer.current !== null) {
            window.clearTimeout(saveTimer.current);
        }
        saveTimer.current = window.setTimeout(() => {
            void persistDraft();
        }, 1200);
    }, [persistDraft]);

    useEffect(() => () => {
        if (saveTimer.current !== null) {
            window.clearTimeout(saveTimer.current);
        }
    }, []);

    const updateField = (fieldName: string, value: string): void => {
        setValues((previous) => ({ ...previous, [fieldName]: value }));
        setDirty(true);
        setSaveStatus('idle');
        scheduleSave();
    };

    const submit = async (): Promise<void> => {
        setSubmitError(null);
        const saved = await persistDraft();
        if (!saved) {
            setSubmitError(labels.submitError);

            return;
        }
        const response = await fetch(`${baseUrl}/${section.contentType}/${section.contentId}/${locale}/submit`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrfHeader() },
            body: JSON.stringify({}),
        });
        const data: { state?: string; error?: string } = await response.json();
        if (!response.ok) {
            setSubmitError(data.error ?? labels.submitError);

            return;
        }
        if (data.state !== undefined) {
            setState(data.state);
        }
    };

    const reviewAction = async (action: 'approve' | 'reject' | 'rework'): Promise<void> => {
        if (revisionId === null) {
            return;
        }
        setSubmitError(null);
        const response = await fetch(`${baseUrl}/${section.contentType}/revisions/${revisionId}/${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrfHeader() },
            body: JSON.stringify(action === 'reject' ? { comment: rejectComment } : {}),
        });
        const data: { state?: string; error?: string } = await response.json();
        if (!response.ok) {
            setSubmitError(data.error ?? labels.submitError);

            return;
        }
        if (data.state !== undefined) {
            setState(data.state);
        }
    };

    const changedFields = section.fields.filter(
        (field) => field.pinnedSource !== null && field.latestSource !== null && field.pinnedSource !== field.latestSource,
    );

    return (
        <Stack gap="md">
            {section.sourceStale && (
                <Alert color="orange" data-testid="stale-banner">
                    <Group justify="space-between">
                        <Text size="sm">{labels.staleBanner}</Text>
                        <Button size="compact-xs" variant="subtle" onClick={() => setShowDiff((value) => !value)}>
                            {showDiff ? labels.hideChanges : labels.showChanges}
                        </Button>
                    </Group>
                    {showDiff && changedFields.map((field) => (
                        <Paper key={field.name} p="xs" mt="xs" withBorder>
                            <Text size="xs" c="dimmed" mb={4}>{fieldLabel(labels, field.name)}</Text>
                            <SourceDiff oldText={field.pinnedSource ?? ''} newText={field.latestSource ?? ''} />
                        </Paper>
                    ))}
                </Alert>
            )}

            {state === 'pending_review' && !canModerate && <Alert color="yellow">{labels.pendingNotice}</Alert>}
            {state === 'rejected' && (
                <Alert color="red">
                    <Text size="sm">{labels.rejectedNotice}</Text>
                    {section.reviewComment !== null && section.reviewComment !== '' && (
                        <Text size="sm" mt="xs" fs="italic">
                            {labels.reviewComment}: {section.reviewComment}
                        </Text>
                    )}
                    {canTranslate && (
                        <Button size="compact-sm" mt="xs" onClick={() => void reviewAction('rework')}>
                            {labels.rework}
                        </Button>
                    )}
                </Alert>
            )}

            {section.fields.map((field) => (
                <div key={field.name}>
                    <Group justify="space-between" mb={4}>
                        <Text fw={600} size="sm">{fieldLabel(labels, field.name)}</Text>
                        {editable && field.source !== null && field.source !== '' && (
                            <Button size="compact-xs" variant="subtle" onClick={() => updateField(field.name, field.source ?? '')}>
                                {labels.copySource}
                            </Button>
                        )}
                    </Group>
                    <Group align="flex-start" grow>
                        <Paper p="sm" withBorder>
                            <Group justify="space-between" mb={4}>
                                <Text size="xs" c="dimmed">{labels.source}</Text>
                                {field.kind === 'markdown' && field.sourceHtml !== null && (
                                    <SegmentedControl
                                        size="xs"
                                        value={sourceView}
                                        onChange={(value) => setSourceView(value === 'raw' ? 'raw' : 'rendered')}
                                        data={[
                                            { label: labels.rendered, value: 'rendered' },
                                            { label: labels.raw, value: 'raw' },
                                        ]}
                                    />
                                )}
                            </Group>
                            {field.kind === 'markdown' && field.sourceHtml !== null && sourceView === 'rendered' ? (
                                <div className="cms-content" dangerouslySetInnerHTML={{ __html: field.sourceHtml }} />
                            ) : (
                                <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>{field.source ?? ''}</Text>
                            )}
                        </Paper>
                        <Paper p="sm" withBorder>
                            <Group justify="space-between" mb={4}>
                                <Text size="xs" c="dimmed">{`${labels.target} (${locale.toUpperCase()})`}</Text>
                                {field.maxLength !== null && (
                                    <Text size="xs" c={values[field.name].length > field.maxLength ? 'red' : 'dimmed'}>
                                        {`${values[field.name].length}/${field.maxLength}`}
                                    </Text>
                                )}
                            </Group>
                            {field.kind === 'markdown' ? (
                                <MarkdownEditor
                                    initialContent={values[field.name]}
                                    onChange={(content) => updateField(field.name, content)}
                                    disabled={!editable}
                                />
                            ) : field.kind === 'textarea' ? (
                                <Textarea
                                    value={values[field.name]}
                                    onChange={(event) => updateField(field.name, event.currentTarget.value)}
                                    autosize
                                    minRows={2}
                                    disabled={!editable}
                                />
                            ) : (
                                <TextInput
                                    value={values[field.name]}
                                    onChange={(event) => updateField(field.name, event.currentTarget.value)}
                                    disabled={!editable}
                                />
                            )}
                        </Paper>
                    </Group>
                </div>
            ))}

            {submitError !== null && <Alert color="red">{submitError}</Alert>}

            <Group justify="flex-end">
                {saveStatus === 'saving' && <Text size="sm" c="dimmed">{labels.saving}</Text>}
                {saveStatus === 'saved' && <Text size="sm" c="green">{labels.saved}</Text>}
                {editable && (
                    // Untouched sections must not be submittable: a submit
                    // without any work would create and send an empty draft.
                    <Button onClick={() => void submit()} disabled={!dirty && revisionId === null}>
                        {labels.submit}
                    </Button>
                )}
                {canModerate && state === 'pending_review' && (
                    <>
                        <TextInput
                            placeholder={labels.rejectCommentLabel}
                            value={rejectComment}
                            onChange={(event) => setRejectComment(event.currentTarget.value)}
                        />
                        <Button color="red" variant="light" onClick={() => void reviewAction('reject')}>
                            {labels.reject}
                        </Button>
                        <Button color="green" onClick={() => void reviewAction('approve')}>
                            {labels.approve}
                        </Button>
                    </>
                )}
            </Group>
        </Stack>
    );
}

export const sectionStateLabel = (labels: TranslationEditorLabels, state: string | null): string | null => {
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

/**
 * Card wrapper giving each translation unit its own clearly bounded block:
 * header with name + state badges, body with the section's own form and
 * actions. The archetype and each variant are fully independent parts.
 */
function SectionCard({ title, section, labels, children }: { title: string | null; section: TranslationSectionData; labels: TranslationEditorLabels; children: React.ReactNode }) {
    const stateBadge = sectionStateLabel(labels, section.state);

    return (
        <Paper withBorder shadow="sm" p="md">
            {(title !== null || stateBadge !== null) && (
                <Group gap="xs" mb="md">
                    {title !== null && <Title order={2} size="h5" style={{ margin: 0 }}>{title}</Title>}
                    {stateBadge !== null && (
                        <Badge color={section.state === 'rejected' ? 'red' : 'yellow'}>{stateBadge}</Badge>
                    )}
                </Group>
            )}
            {children}
        </Paper>
    );
}

export default function TranslationEditor({ sections, locale, baseUrl, canTranslate, canModerate, labels }: TranslationEditorProps) {
    const [firstSection, ...variantSections] = sections;

    return (
        <Stack gap="lg">
            <SectionCard
                title={variantSections.length > 0 ? labels.archetypeSection : null}
                section={firstSection}
                labels={labels}
            >
                <TranslationSection
                    key={`${firstSection.contentType}-${firstSection.contentId}`}
                    section={firstSection}
                    locale={locale}
                    baseUrl={baseUrl}
                    canTranslate={canTranslate}
                    canModerate={canModerate}
                    labels={labels}
                />
            </SectionCard>

            {variantSections.length > 0 && (
                <Accordion
                    multiple
                    variant="separated"
                    defaultValue={variantSections
                        .filter((section) => section.sourceStale || section.state !== null)
                        .map((section) => `variant-${section.contentId}`)}
                >
                    {variantSections.map((section) => (
                        <Accordion.Item key={`variant-${section.contentId}`} value={`variant-${section.contentId}`}>
                            <Accordion.Control>
                                <Group gap="xs">
                                    <Text fw={600}>{section.variantName ?? `#${section.contentId}`}</Text>
                                    {sectionStateLabel(labels, section.state) !== null && (
                                        <Badge color={section.state === 'rejected' ? 'red' : 'yellow'}>
                                            {sectionStateLabel(labels, section.state)}
                                        </Badge>
                                    )}
                                    {section.sourceStale && <Badge color="orange">{labels.showChanges}</Badge>}
                                </Group>
                            </Accordion.Control>
                            <Accordion.Panel>
                                <TranslationSection
                                    section={section}
                                    locale={locale}
                                    baseUrl={baseUrl}
                                    canTranslate={canTranslate}
                                    canModerate={canModerate}
                                    labels={labels}
                                />
                            </Accordion.Panel>
                        </Accordion.Item>
                    ))}
                </Accordion>
            )}

            <Accordion variant="separated">
                <Accordion.Item value="guidelines">
                    <Accordion.Control>{labels.guidelinesTitle}</Accordion.Control>
                    <Accordion.Panel>
                        <Text size="sm">{labels.guidelinesBody}</Text>
                    </Accordion.Panel>
                </Accordion.Item>
            </Accordion>
        </Stack>
    );
}
