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
 * Populates the shared #stapleCardModal from the clicked trigger's data
 * attributes and adds swipe / arrow-key / chevron navigation between
 * staples (mirrors the banned-card modal in `banned-card-list.ts`).
 *
 * @see docs/features.md F6.15 — Staple cards
 */

import './styles/app.scss';

const MODAL_ID = 'stapleCardModal';
const SWIPE_THRESHOLD = 50;

interface PrintingInfo {
    setCode: string;
    cardNumber: string;
}

const modalElement = document.getElementById(MODAL_ID);
const triggers = Array.from(
    document.querySelectorAll<HTMLButtonElement>('.staple-card-trigger'),
);

if (modalElement && triggers.length > 0) {
    initStapleCardModal(modalElement, triggers);
}

function initStapleCardModal(modalElement: HTMLElement, triggers: HTMLButtonElement[]): void {
    let currentIndex = 0;

    const navigate = (direction: number): void => {
        if (triggers.length <= 1) return;
        currentIndex = (currentIndex + direction + triggers.length) % triggers.length;
        populateModal(triggers[currentIndex]);
    };

    modalElement.addEventListener('show.bs.modal', (event) => {
        const trigger = (event as Event & { relatedTarget?: HTMLElement | null }).relatedTarget;
        if (!(trigger instanceof HTMLButtonElement)) return;

        const index = triggers.indexOf(trigger);
        if (index === -1) return;

        currentIndex = index;
        populateModal(trigger);
    });

    document.getElementById('stapleCardModalPrev')?.addEventListener('click', () => navigate(-1));
    document.getElementById('stapleCardModalNext')?.addEventListener('click', () => navigate(1));

    const showNavControls = triggers.length > 1;
    const prevButton = document.getElementById('stapleCardModalPrev');
    const nextButton = document.getElementById('stapleCardModalNext');
    if (prevButton) prevButton.style.display = showNavControls ? '' : 'none';
    if (nextButton) nextButton.style.display = showNavControls ? '' : 'none';

    const modalBody = modalElement.querySelector('.modal-body');
    if (modalBody) {
        let touchStartX = 0;
        let touchStartY = 0;

        modalBody.addEventListener('touchstart', (event) => {
            const touch = (event as TouchEvent).touches[0];
            touchStartX = touch.clientX;
            touchStartY = touch.clientY;
        }, { passive: true });

        modalBody.addEventListener('touchmove', (event) => {
            const touch = (event as TouchEvent).touches[0];
            const deltaX = Math.abs(touch.clientX - touchStartX);
            const deltaY = Math.abs(touch.clientY - touchStartY);
            // Block vertical scroll; allow horizontal swipe to drive navigation
            if (deltaY > deltaX) {
                event.preventDefault();
            }
        }, { passive: false });

        modalBody.addEventListener('touchend', (event) => {
            const touchEndX = (event as TouchEvent).changedTouches[0].clientX;
            const deltaX = touchEndX - touchStartX;
            if (Math.abs(deltaX) > SWIPE_THRESHOLD) {
                navigate(deltaX < 0 ? 1 : -1);
            }
        }, { passive: true });
    }

    modalElement.addEventListener('keydown', (event) => {
        const key = (event as KeyboardEvent).key;
        if (key === 'ArrowLeft') navigate(-1);
        if (key === 'ArrowRight') navigate(1);
    });
}

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
