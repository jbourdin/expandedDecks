/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F10.6 — ImageUrlField component with drag-and-drop upload
 */

import { useState, useRef, useCallback } from 'react';
import { TextInput, Image, Loader, Text, Group, ActionIcon } from '@mantine/core';
import { IconUpload, IconX } from '@tabler/icons-react';

interface ImageUrlFieldProps {
    value: string;
    onChange: (url: string) => void;
    label?: string;
    placeholder?: string;
    uploadUrl: string;
    serverError?: string | null;
    labels?: {
        dropHint?: string;
        uploading?: string;
        uploadError?: string;
        invalidType?: string;
    };
}

const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

export default function ImageUrlField({
    value,
    onChange,
    label,
    placeholder = 'https://...',
    uploadUrl,
    serverError = null,
    labels = {},
}: ImageUrlFieldProps) {
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [dragOver, setDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const uploadFile = useCallback(async (file: File) => {
        if (!ALLOWED_TYPES.includes(file.type)) {
            setError(labels.invalidType ?? 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP.');
            return;
        }

        setError(null);
        setUploading(true);

        try {
            const formData = new FormData();
            formData.append('file', file);

            const response = await fetch(uploadUrl, {
                method: 'POST',
                body: formData,
            });

            if (!response.ok) {
                const data = await response.json();
                setError(data.error ?? labels.uploadError ?? 'Upload failed.');
                return;
            }

            const data = await response.json();
            onChange(data.url);
        } catch {
            setError(labels.uploadError ?? 'Upload failed.');
        } finally {
            setUploading(false);
        }
    }, [uploadUrl, onChange, labels]);

    const handleDrop = useCallback((event: React.DragEvent) => {
        event.preventDefault();
        setDragOver(false);

        const file = event.dataTransfer.files[0];
        if (file) {
            uploadFile(file);
        }
    }, [uploadFile]);

    const handleFileSelect = useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (file) {
            uploadFile(file);
        }
        // Reset so the same file can be selected again
        event.target.value = '';
    }, [uploadFile]);

    return (
        <div
            onDragOver={(event) => { event.preventDefault(); setDragOver(true); }}
            onDragLeave={() => setDragOver(false)}
            onDrop={handleDrop}
            style={{
                border: dragOver ? '2px dashed #228be6' : undefined,
                borderRadius: dragOver ? 4 : undefined,
                padding: dragOver ? 4 : undefined,
                transition: 'border 0.15s',
            }}
        >
            <TextInput
                label={label}
                value={value}
                onChange={(event) => { setError(null); onChange(event.currentTarget.value); }}
                placeholder={placeholder}
                error={error ?? serverError ?? undefined}
                rightSection={
                    uploading ? (
                        <Loader size={16} />
                    ) : (
                        <ActionIcon variant="subtle" size="sm" onClick={() => fileInputRef.current?.click()}>
                            <IconUpload size={14} />
                        </ActionIcon>
                    )
                }
            />
            <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/gif,image/webp"
                style={{ display: 'none' }}
                onChange={handleFileSelect}
            />
            {dragOver && (
                <Text size="xs" c="blue" ta="center" mt={4}>
                    {labels.dropHint ?? 'Drop image to upload'}
                </Text>
            )}
            {value && !uploading && (
                <Group mt="xs" gap="xs" align="center">
                    <Image
                        src={value}
                        alt=""
                        w={60}
                        h={40}
                        fit="cover"
                        radius="sm"
                        fallbackSrc="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='40'%3E%3Crect fill='%23dee2e6' width='60' height='40'/%3E%3C/svg%3E"
                    />
                    <ActionIcon variant="subtle" color="red" size="sm" onClick={() => onChange('')}>
                        <IconX size={14} />
                    </ActionIcon>
                </Group>
            )}
        </div>
    );
}
