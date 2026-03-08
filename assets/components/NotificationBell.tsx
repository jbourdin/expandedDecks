/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { useCallback, useEffect, useState } from 'react';
import {
    ActionIcon,
    Badge,
    Button,
    Group,
    Indicator,
    Popover,
    ScrollArea,
    Stack,
    Text,
    UnstyledButton,
} from '@mantine/core';
import { IconBell, IconCheck, IconChecks } from '@tabler/icons-react';

/**
 * @see docs/features.md F8.4 — In-app notification center
 */

interface NotificationItem {
    id: number;
    type: string;
    title: string;
    message: string;
    isRead: boolean;
    createdAt: string;
    context: Record<string, unknown> | null;
    url: string | null;
}

interface NotificationBellProps {
    apiUrl: string;
    listUrl: string;
    markReadUrlTemplate: string;
    markAllReadUrl: string;
    pollIntervalMs?: number;
    labels: {
        markRead: string;
        markAllRead: string;
        viewAll: string;
        empty: string;
    };
}

function timeAgo(dateStr: string): string {
    const now = Date.now();
    const then = new Date(dateStr).getTime();
    const diffMs = now - then;
    const diffMin = Math.floor(diffMs / 60_000);

    if (diffMin < 1) return '< 1m';
    if (diffMin < 60) return `${diffMin}m`;
    const diffHours = Math.floor(diffMin / 60);
    if (diffHours < 24) return `${diffHours}h`;
    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays}d`;
}

function typeIcon(type: string): string {
    if (type.startsWith('borrow_')) return '↔';
    if (type.startsWith('event_') || type.startsWith('staff_')) return '📅';
    return '🔔';
}

export default function NotificationBell({
    apiUrl,
    listUrl,
    markReadUrlTemplate,
    markAllReadUrl,
    pollIntervalMs = 60_000,
    labels,
}: NotificationBellProps) {
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [opened, setOpened] = useState(false);

    const fetchNotifications = useCallback(async () => {
        try {
            const res = await fetch(apiUrl);
            if (!res.ok) return;
            const data = await res.json();
            setNotifications(data.notifications);
            setUnreadCount(data.unreadCount);
        } catch {
            // Network error — silently ignore
        }
    }, [apiUrl]);

    useEffect(() => {
        fetchNotifications();
        const interval = setInterval(fetchNotifications, pollIntervalMs);
        return () => clearInterval(interval);
    }, [fetchNotifications, pollIntervalMs]);

    const markRead = async (id: number, e: React.MouseEvent) => {
        e.stopPropagation();
        const url = markReadUrlTemplate.replace('__ID__', String(id));
        try {
            const res = await fetch(url, { method: 'POST' });
            if (!res.ok) return;
            const data = await res.json();
            setUnreadCount(data.unreadCount);
            setNotifications((prev) =>
                prev.map((n) => (n.id === id ? { ...n, isRead: true } : n)),
            );
        } catch {
            // Silently ignore
        }
    };

    const markAllRead = async () => {
        try {
            const res = await fetch(markAllReadUrl, { method: 'POST' });
            if (!res.ok) return;
            setUnreadCount(0);
            setNotifications((prev) => prev.map((n) => ({ ...n, isRead: true })));
        } catch {
            // Silently ignore
        }
    };

    return (
        <Popover
            opened={opened}
            onChange={setOpened}
            width={360}
            position="bottom-end"
            shadow="lg"
            withArrow
        >
            <Popover.Target>
                <Indicator
                    label={unreadCount > 0 ? (unreadCount > 99 ? '99+' : String(unreadCount)) : undefined}
                    size={18}
                    color="red"
                    disabled={unreadCount === 0}
                    offset={4}
                >
                    <ActionIcon
                        variant="subtle"
                        size="lg"
                        onClick={() => setOpened((o) => !o)}
                        aria-label="Notifications"
                        style={{ color: 'rgba(255, 255, 255, 0.85)' }}
                    >
                        <IconBell size={22} />
                    </ActionIcon>
                </Indicator>
            </Popover.Target>

            <Popover.Dropdown p={0}>
                <Group justify="space-between" px="md" py="sm" style={{ borderBottom: '1px solid #eee' }}>
                    <Text fw={600} size="sm">
                        Notifications
                        {unreadCount > 0 && (
                            <Badge size="sm" color="red" variant="filled" ml={6}>
                                {unreadCount}
                            </Badge>
                        )}
                    </Text>
                    {unreadCount > 0 && (
                        <UnstyledButton onClick={markAllRead}>
                            <Group gap={4}>
                                <IconChecks size={14} color="#868e96" />
                                <Text size="xs" c="dimmed">{labels.markAllRead}</Text>
                            </Group>
                        </UnstyledButton>
                    )}
                </Group>

                <ScrollArea.Autosize mah={400}>
                    {notifications.length === 0 ? (
                        <Text c="dimmed" ta="center" py="xl" size="sm">
                            {labels.empty}
                        </Text>
                    ) : (
                        <Stack gap={0}>
                            {notifications.map((n) => (
                                <Group
                                    key={n.id}
                                    wrap="nowrap"
                                    gap="sm"
                                    px="md"
                                    py="xs"
                                    align="flex-start"
                                    onClick={async () => {
                                        if (!n.isRead) {
                                            const url = markReadUrlTemplate.replace('__ID__', String(n.id));
                                            try {
                                                await fetch(url, { method: 'POST' });
                                            } catch {
                                                // Best-effort — navigate anyway
                                            }
                                        }
                                        if (n.url) {
                                            window.location.href = n.url;
                                        }
                                    }}
                                    style={{
                                        backgroundColor: n.isRead ? 'transparent' : 'rgba(33, 53, 104, 0.04)',
                                        borderBottom: '1px solid #f1f1f1',
                                        cursor: n.url ? 'pointer' : 'default',
                                    }}
                                >
                                    <Text size="lg" mt={2}>{typeIcon(n.type)}</Text>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <Group justify="space-between" wrap="nowrap">
                                            <Text
                                                size="sm"
                                                fw={n.isRead ? 400 : 600}
                                                lineClamp={1}
                                                td={n.url ? 'none' : undefined}
                                            >
                                                {n.title}
                                            </Text>
                                            <Text size="xs" c="dimmed" style={{ flexShrink: 0 }}>
                                                {timeAgo(n.createdAt)}
                                            </Text>
                                        </Group>
                                        <Text size="xs" c="dimmed" lineClamp={2}>
                                            {n.message}
                                        </Text>
                                        {!n.isRead && (
                                            <UnstyledButton
                                                onClick={(e: React.MouseEvent) => markRead(n.id, e)}
                                                mt={2}
                                            >
                                                <Group gap={4}>
                                                    <IconCheck size={12} color="#868e96" />
                                                    <Text size="xs" c="dimmed">{labels.markRead}</Text>
                                                </Group>
                                            </UnstyledButton>
                                        )}
                                    </div>
                                </Group>
                            ))}
                        </Stack>
                    )}
                </ScrollArea.Autosize>

                <Group justify="center" py="sm" style={{ borderTop: '1px solid #eee' }}>
                    <Button
                        component="a"
                        href={listUrl}
                        variant="subtle"
                        size="xs"
                    >
                        {labels.viewAll}
                    </Button>
                </Group>
            </Popover.Dropdown>
        </Popover>
    );
}
