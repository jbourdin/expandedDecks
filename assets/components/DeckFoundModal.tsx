/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F4.16 — Lost & found deck alert
 */

import { useState, useRef, useCallback } from 'react';
import { Alert, Button, Checkbox, Group, Modal, Textarea } from '@mantine/core';
import { FriendlyCaptchaSDK, type WidgetHandle } from '@friendlycaptcha/sdk';

interface DeckFoundModalProps {
    apiUrl: string;
    csrfToken: string;
    isLoggedIn: boolean;
    ownerDiscord: string;
    sitekey: string;
    labels: {
        button: string;
        title: string;
        anonymousLabel: string;
        messageLabel: string;
        messagePlaceholder: string;
        discordCopy: string;
        discordCopied: string;
        submit: string;
        success: string;
        error: string;
    };
}

/**
 * @see docs/features.md F4.16 — Lost & found deck alert
 */
export default function DeckFoundModal({ apiUrl, csrfToken, isLoggedIn, ownerDiscord, sitekey, labels }: DeckFoundModalProps) {
    const [opened, setOpened] = useState(false);
    const [anonymous, setAnonymous] = useState(false);
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');
    const [discordCopied, setDiscordCopied] = useState(false);
    const captchaWidgetReference = useRef<WidgetHandle | null>(null);
    const captchaResponseReference = useRef<string>('');

    /**
     * Callback ref: when Mantine mounts the captcha container in the portal,
     * create the Friendly Captcha widget. When the element unmounts (modal closes),
     * destroy the widget.
     */
    const captchaRefCallback = useCallback((element: HTMLDivElement | null) => {
        if (captchaWidgetReference.current) {
            captchaWidgetReference.current.destroy();
            captchaWidgetReference.current = null;
            captchaResponseReference.current = '';
        }

        if (element && sitekey) {
            const sdk = new FriendlyCaptchaSDK();
            const widget = sdk.createWidget({
                element,
                sitekey,
                formFieldName: 'frc-captcha-response',
            });

            widget.addEventListener('frc:widget.complete', (event) => {
                captchaResponseReference.current = event.detail.response;
            });

            captchaWidgetReference.current = widget;
        }
    }, [sitekey]);

    const handleSubmit = useCallback(async () => {
        setSubmitting(true);
        setErrorMessage('');

        const captchaResponse = captchaResponseReference.current;

        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message: message.trim() || null,
                anonymous,
                captchaResponse,
                csrfToken,
            }),
        });

        setSubmitting(false);

        if (response.ok) {
            setSubmitted(true);
        } else {
            setErrorMessage(labels.error);
        }
    }, [apiUrl, message, anonymous, csrfToken, labels.error]);

    const handleDiscordCopy = useCallback(async () => {
        await navigator.clipboard.writeText(ownerDiscord);
        setDiscordCopied(true);
        setTimeout(() => setDiscordCopied(false), 2000);
    }, [ownerDiscord]);

    const handleClose = useCallback(() => {
        setOpened(false);
        if (submitted) {
            setSubmitted(false);
            setMessage('');
            setAnonymous(false);
            setErrorMessage('');
        }
    }, [submitted]);

    return (
        <>
            <div className="mb-3">
                <Button onClick={() => setOpened(true)} variant="outline" color="blue" fullWidth>
                    {labels.button}
                </Button>
            </div>

            <Modal opened={opened} onClose={handleClose} title={labels.title} centered>
                {submitted ? (
                    <Alert color="green" mb="md">
                        {labels.success}
                    </Alert>
                ) : (
                    <>
                        {isLoggedIn && (
                            <Checkbox
                                label={labels.anonymousLabel}
                                checked={anonymous}
                                onChange={(event) => setAnonymous(event.currentTarget.checked)}
                                mb="md"
                            />
                        )}

                        <Textarea
                            label={labels.messageLabel}
                            placeholder={labels.messagePlaceholder}
                            value={message}
                            onChange={(event) => setMessage(event.currentTarget.value)}
                            maxLength={500}
                            minRows={5}
                            autosize
                            required
                            mb="md"
                        />

                        {ownerDiscord && (
                            <Group mb="md">
                                <Button
                                    variant="light"
                                    color={discordCopied ? 'green' : 'violet'}
                                    onClick={handleDiscordCopy}
                                    size="sm"
                                >
                                    {discordCopied ? labels.discordCopied : `${labels.discordCopy} (${ownerDiscord})`}
                                </Button>
                            </Group>
                        )}

                        {sitekey && (
                            <div ref={captchaRefCallback} className="frc-captcha-container mb-3" />
                        )}

                        {errorMessage && (
                            <Alert color="red" mb="md">
                                {errorMessage}
                            </Alert>
                        )}

                        <Group justify="flex-end">
                            <Button variant="default" onClick={handleClose}>
                                Cancel
                            </Button>
                            <Button onClick={handleSubmit} loading={submitting} disabled={message.trim().length === 0}>
                                {labels.submit}
                            </Button>
                        </Group>
                    </>
                )}
            </Modal>
        </>
    );
}
