/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F18.32 — Card-fan OG image builder
 */

import { useState, useCallback } from 'react';
import { Badge, Button, CopyButton, Group, Image, Stack, Text, Textarea } from '@mantine/core';
import { IconCheck, IconCopy, IconPhoto } from '@tabler/icons-react';

interface ResolvedCard {
    code: string;
    status: 'resolved' | 'not_found';
    name: string | null;
}

interface OgImageBuilderProps {
    generateUrl: string;
    labels: {
        codesLabel: string;
        codesHelp: string;
        generate: string;
        copyUrl: string;
        copied: string;
        notFound: string;
        errorCardCount: string;
        errorNoneResolved: string;
        errorGeneric: string;
    };
}

export default function OgImageBuilder({ generateUrl, labels }: OgImageBuilderProps) {
    const [codesText, setCodesText] = useState('');
    const [generating, setGenerating] = useState(false);
    const [imageUrl, setImageUrl] = useState<string | null>(null);
    const [cards, setCards] = useState<ResolvedCard[]>([]);
    const [error, setError] = useState<string | null>(null);

    const generate = useCallback(async () => {
        const codes = codesText
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== '');

        setError(null);
        setImageUrl(null);
        setCards([]);
        setGenerating(true);

        try {
            const response = await fetch(generateUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ codes }),
            });

            const data = await response.json();

            if (Array.isArray(data.cards)) {
                setCards(data.cards as ResolvedCard[]);
            }

            if (!response.ok) {
                if (data.error === 'invalid_card_count') {
                    setError(labels.errorCardCount);
                } else if (data.error === 'no_card_resolved') {
                    setError(labels.errorNoneResolved);
                } else {
                    setError(labels.errorGeneric);
                }
                return;
            }

            setImageUrl(data.url);
        } catch {
            setError(labels.errorGeneric);
        } finally {
            setGenerating(false);
        }
    }, [codesText, generateUrl, labels]);

    return (
        <Stack maw={640}>
            <Textarea
                label={labels.codesLabel}
                description={labels.codesHelp}
                value={codesText}
                onChange={(event) => setCodesText(event.currentTarget.value)}
                autosize
                minRows={3}
                maxRows={8}
            />
            <Group>
                <Button
                    onClick={generate}
                    loading={generating}
                    leftSection={<IconPhoto size={16} />}
                >
                    {labels.generate}
                </Button>
            </Group>
            {error && (
                <Text c="red" size="sm">
                    {error}
                </Text>
            )}
            {cards.length > 0 && (
                <Group gap="xs">
                    {cards.map((card) => (
                        <Badge
                            key={card.code}
                            color={card.status === 'resolved' ? 'green' : 'red'}
                            variant="light"
                        >
                            {card.code}
                            {card.status === 'resolved' ? ` — ${card.name}` : ` — ${labels.notFound}`}
                        </Badge>
                    ))}
                </Group>
            )}
            {imageUrl && (
                <Stack gap="xs">
                    <Image
                        src={imageUrl}
                        alt=""
                        radius="sm"
                        style={{
                            // Checkerboard backdrop so the transparent areas are visible
                            backgroundImage:
                                'linear-gradient(45deg, #ced4da 25%, transparent 25%, transparent 75%, #ced4da 75%), '
                                + 'linear-gradient(45deg, #ced4da 25%, transparent 25%, transparent 75%, #ced4da 75%)',
                            backgroundSize: '24px 24px',
                            backgroundPosition: '0 0, 12px 12px',
                        }}
                    />
                    <Group>
                        <CopyButton value={new URL(imageUrl, window.location.origin).toString()}>
                            {({ copied, copy }) => (
                                <Button
                                    variant="light"
                                    color={copied ? 'teal' : undefined}
                                    onClick={copy}
                                    leftSection={copied ? <IconCheck size={16} /> : <IconCopy size={16} />}
                                >
                                    {copied ? labels.copied : labels.copyUrl}
                                </Button>
                            )}
                        </CopyButton>
                        <Text size="sm" c="dimmed" style={{ wordBreak: 'break-all' }}>
                            {imageUrl}
                        </Text>
                    </Group>
                </Stack>
            )}
        </Stack>
    );
}
