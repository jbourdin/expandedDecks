/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Banned cards public list — modal interactions.
 *
 * Populates the shared #bannedCardModal from the clicked trigger's data
 * attributes, and adds swipe / arrow-key / chevron navigation between
 * banned cards (mirrors the deck card modal in `shared/card-hover.ts`).
 *
 * @see docs/features.md F6.14 — Banned cards public page
 */

const MODAL_ID = 'bannedCardModal';
const SWIPE_THRESHOLD = 50;

interface PrintingInfo {
    setCode: string;
    cardNumber: string;
}

const modalElement = document.getElementById(MODAL_ID);
const triggers = Array.from(
    document.querySelectorAll<HTMLButtonElement>('.banned-card-trigger'),
);

if (modalElement && triggers.length > 0) {
    initBannedCardModal(modalElement, triggers);
}

function initBannedCardModal(modalElement: HTMLElement, triggers: HTMLButtonElement[]): void {
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

    document.getElementById('bannedCardModalPrev')?.addEventListener('click', () => navigate(-1));
    document.getElementById('bannedCardModalNext')?.addEventListener('click', () => navigate(1));

    const showNavControls = triggers.length > 1;
    const prevButton = document.getElementById('bannedCardModalPrev');
    const nextButton = document.getElementById('bannedCardModalNext');
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

        modalBody.addEventListener('touchend', (event) => {
            const touch = (event as TouchEvent).changedTouches[0];
            const deltaX = touch.clientX - touchStartX;
            const deltaY = touch.clientY - touchStartY;
            if (Math.abs(deltaX) > SWIPE_THRESHOLD && Math.abs(deltaX) > Math.abs(deltaY)) {
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
    const effectiveDate = trigger.dataset.cardEffectiveDate ?? '';
    const sourceUrl = trigger.dataset.cardSourceUrl ?? '';
    const explanationHtml = trigger.dataset.cardExplanation ?? '';
    const printingsJson = trigger.dataset.cardPrintings ?? '[]';

    const titleEl = document.getElementById('bannedCardModalLabel');
    if (titleEl) titleEl.textContent = name;

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

    const printingsList = document.getElementById('bannedCardModalPrintings');
    if (printingsList) {
        printingsList.innerHTML = '';
        printings.forEach((printing) => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-secondary-subtle text-secondary-emphasis font-monospace';
            badge.textContent = `${printing.setCode} ${printing.cardNumber}`;
            printingsList.appendChild(badge);
        });
    }

    const imageEl = document.getElementById('bannedCardModalImage') as HTMLImageElement | null;
    if (imageEl) {
        if (image) {
            imageEl.src = image;
            imageEl.alt = name;
            imageEl.hidden = false;
        } else {
            imageEl.removeAttribute('src');
            imageEl.hidden = true;
        }
    }

    const effectiveDateLabel = document.getElementById('bannedCardModalEffectiveDateLabel');
    const effectiveDateEl = document.getElementById('bannedCardModalEffectiveDate');
    if (effectiveDateLabel && effectiveDateEl) {
        if (effectiveDate) {
            effectiveDateEl.textContent = effectiveDate;
            effectiveDateLabel.hidden = false;
            effectiveDateEl.hidden = false;
        } else {
            effectiveDateLabel.hidden = true;
            effectiveDateEl.hidden = true;
        }
    }

    const sourceLabel = document.getElementById('bannedCardModalSourceLabel');
    const sourceEl = document.getElementById('bannedCardModalSource') as HTMLAnchorElement | null;
    if (sourceLabel && sourceEl?.parentElement) {
        if (sourceUrl) {
            sourceEl.href = sourceUrl;
            sourceLabel.hidden = false;
            sourceEl.parentElement.hidden = false;
        } else {
            sourceLabel.hidden = true;
            sourceEl.parentElement.hidden = true;
        }
    }

    const explanationLabel = document.getElementById('bannedCardModalExplanationLabel');
    const explanationEl = document.getElementById('bannedCardModalExplanation');
    const noExplanationEl = document.getElementById('bannedCardModalNoExplanation');
    if (explanationLabel && explanationEl && noExplanationEl) {
        if (explanationHtml) {
            explanationEl.innerHTML = explanationHtml;
            explanationLabel.hidden = false;
            explanationEl.hidden = false;
            noExplanationEl.hidden = true;
        } else {
            explanationEl.innerHTML = '';
            explanationLabel.hidden = true;
            explanationEl.hidden = true;
            noExplanationEl.hidden = false;
        }
    }
}
