/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Staple cards public list — modal interactions.
 *
 * Populates the shared #stapleCardModal from the clicked trigger's data attributes.
 *
 * @see docs/features.md F6.15 — Staple cards
 */

import './styles/app.scss';

const MODAL_ID = 'stapleCardModal';

interface PrintingInfo {
    setCode: string;
    cardNumber: string;
}

document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById(MODAL_ID);
    if (!modalElement) {
        return;
    }

    modalElement.addEventListener('show.bs.modal', (event) => {
        const trigger = (event as Event & { relatedTarget?: HTMLElement | null }).relatedTarget;
        if (!(trigger instanceof HTMLButtonElement)) {
            return;
        }
        populateModal(trigger);
    });
});

function populateModal(trigger: HTMLButtonElement): void {
    const name = trigger.dataset.cardName ?? '';
    const image = trigger.dataset.cardImage ?? '';
    const noteHtml = trigger.dataset.cardNote ?? '';
    const printingsJson = trigger.dataset.cardPrintings ?? '[]';

    const titleElement = document.getElementById('stapleCardModalLabel');
    if (titleElement) {
        titleElement.textContent = name;
    }

    let printings: PrintingInfo[] = [];
    try {
        const parsed = JSON.parse(printingsJson) as unknown;
        if (Array.isArray(parsed)) {
            printings = parsed.filter((entry): entry is PrintingInfo =>
                typeof entry === 'object'
                && entry !== null
                && 'setCode' in entry
                && 'cardNumber' in entry,
            );
        }
    } catch {
        printings = [];
    }

    const printingsList = document.getElementById('stapleCardModalPrintings');
    if (printingsList) {
        printingsList.innerHTML = '';
        printings.forEach((printing) => {
            const li = document.createElement('li');
            li.className = 'mb-1';
            const code = document.createElement('code');
            code.textContent = `${printing.setCode} ${printing.cardNumber}`;
            li.appendChild(code);
            printingsList.appendChild(li);
        });
    }

    const imageElement = document.getElementById('stapleCardModalImage') as HTMLImageElement | null;
    if (imageElement) {
        if (image) {
            imageElement.src = image;
            imageElement.alt = name;
            imageElement.hidden = false;
        } else {
            imageElement.removeAttribute('src');
            imageElement.hidden = true;
        }
    }

    const noteLabel = document.getElementById('stapleCardModalNoteLabel');
    const noteElement = document.getElementById('stapleCardModalNote');
    if (noteLabel && noteElement) {
        if (noteHtml) {
            noteElement.innerHTML = noteHtml;
            noteLabel.hidden = false;
            noteElement.hidden = false;
        } else {
            noteElement.innerHTML = '';
            noteLabel.hidden = true;
            noteElement.hidden = true;
        }
    }
}
