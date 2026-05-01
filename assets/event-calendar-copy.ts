/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F3.16 — Public iCal feed
 */

document.addEventListener('click', (event) => {
    const target = event.target as HTMLElement | null;

    if (!target) {
        return;
    }

    const button = target.closest<HTMLButtonElement>('[data-event-calendar-copy]');

    if (!button) {
        return;
    }

    event.preventDefault();

    const url = button.dataset.copyUrl ?? '';
    const labelSpan = button.querySelector('span');
    const defaultLabel = button.dataset.labelDefault ?? '';
    const copiedLabel = button.dataset.labelCopied ?? '';

    if (url === '') {
        return;
    }

    navigator.clipboard.writeText(url).then(() => {
        if (labelSpan && copiedLabel !== '') {
            labelSpan.textContent = copiedLabel;

            setTimeout(() => {
                labelSpan.textContent = defaultLabel;
            }, 2000);
        }
    }).catch(() => {
        // Clipboard API can fail in non-secure contexts; we silently degrade.
    });
});
