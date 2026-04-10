/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { Modal } from 'bootstrap';

/**
 * Card image hover: toggles `show-below` class so the preview stays
 * within the viewport (above by default, below when near the top).
 *
 * On touch devices (< md), hover is disabled via CSS. Instead, tapping
 * a card name opens a centered Bootstrap modal with the card image.
 * The modal is swipeable: prev/next buttons and touch swipe navigate
 * through all card images in the same sweep group.
 *
 * Sweep groups: elements with `data-card-hover-group="name"` are grouped
 * together for prev/next navigation. Elements without a group open the
 * modal but do not participate in sweep navigation (standalone).
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */

interface CardEntry {
    src: string;
    name: string;
    quantity: number;
    group: string | null;
    globalIndex: number;
}

const isTouchDevice = (): boolean => window.matchMedia('(max-width: 767.98px)').matches;

// Module-level state shared across multiple initCardHover() calls
const cards: CardEntry[] = [];
let currentIndex = 0;
let modalInitialized = false;

function showCard(index: number): void {
    const modalImg = document.getElementById('cardImageModalImg') as HTMLImageElement | null;
    const modalLabel = document.getElementById('cardImageModalLabel');

    if (!modalImg || index < 0 || index >= cards.length) return;

    const card = cards[index];
    modalImg.src = card.src;
    modalImg.alt = card.name;
    if (modalLabel) {
        modalLabel.textContent = card.quantity > 1 ? `${card.quantity} \u00d7 ${card.name}` : card.name;
    }
}

function getGroupIndices(index: number): number[] {
    const card = cards[index];
    if (!card.group) {
        return [index];
    }

    return cards
        .filter((entry) => entry.group === card.group)
        .map((entry) => entry.globalIndex);
}

function navigate(direction: number): void {
    const groupIndices = getGroupIndices(currentIndex);
    if (groupIndices.length <= 1) return;

    const positionInGroup = groupIndices.indexOf(currentIndex);
    const nextPosition = (positionInGroup + direction + groupIndices.length) % groupIndices.length;
    currentIndex = groupIndices[nextPosition];
    showCard(currentIndex);
}

/**
 * Initialize card hover for all `.card-hover` elements not yet initialized.
 * Safe to call multiple times (e.g. after React re-renders).
 */
export function initCardHover(): void {
    document.querySelectorAll<HTMLElement>('.card-hover').forEach((element) => {
        if (element.dataset.cardHoverInit) return;
        element.dataset.cardHoverInit = '1';

        const img = element.querySelector<HTMLImageElement>('.card-hover-img');
        if (!img) return;

        const globalIndex = cards.length;
        const quantity = parseInt(element.dataset.quantity || '1', 10);
        const group = element.dataset.cardHoverGroup ?? null;
        cards.push({ src: img.src, name: img.alt, quantity, group, globalIndex });

        element.addEventListener('mouseenter', () => {
            if (isTouchDevice()) return;

            const rect = element.getBoundingClientRect();

            // Mirror CSS clamp(200px, 20vw, 350px) × card aspect ratio (88/63 ≈ 1.4)
            const imgHeight = Math.min(Math.max(280, window.innerHeight / 3), 672);
            const imgWidth = imgHeight / 1.4;

            // Position above the card name by default, below if not enough room
            let top: number;
            if (rect.top >= imgHeight + 8) {
                top = rect.top - imgHeight;
            } else {
                top = rect.bottom + 4;
            }

            // Clamp to viewport bounds
            top = Math.max(4, Math.min(top, window.innerHeight - imgHeight - 4));

            let left = rect.left;
            if (left + imgWidth > window.innerWidth - 4) {
                left = window.innerWidth - imgWidth - 4;
            }
            left = Math.max(4, left);

            img.style.top = `${top}px`;
            img.style.left = `${left}px`;
        });

        element.addEventListener('click', (event) => {
            if (!isTouchDevice()) return;

            event.preventDefault();
            currentIndex = globalIndex;
            showCard(currentIndex);

            const modalElement = document.getElementById('cardImageModal');
            if (!modalElement) return;

            // Hide prev/next for standalone cards (no sweep group)
            const prevButton = document.getElementById('cardImageModalPrev');
            const nextButton = document.getElementById('cardImageModalNext');
            const groupSize = getGroupIndices(globalIndex).length;
            if (prevButton) prevButton.style.display = groupSize > 1 ? '' : 'none';
            if (nextButton) nextButton.style.display = groupSize > 1 ? '' : 'none';

            const bsModal = Modal.getOrCreateInstance(modalElement);
            bsModal.show();
        });
    });

    // Only set up modal navigation handlers once
    if (modalInitialized) return;
    modalInitialized = true;

    // Prev/next button handlers
    document.getElementById('cardImageModalPrev')?.addEventListener('click', () => navigate(-1));
    document.getElementById('cardImageModalNext')?.addEventListener('click', () => navigate(1));

    // Touch swipe support + block background scroll on iOS
    const modalElement = document.getElementById('cardImageModal');
    const modalBody = document.querySelector('#cardImageModal .modal-body');
    if (modalBody && modalElement) {
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

            // Block vertical scroll; allow horizontal swipe
            if (deltaY > deltaX) {
                event.preventDefault();
            }
        }, { passive: false });

        modalBody.addEventListener('touchend', (event) => {
            const touchEndX = (event as TouchEvent).changedTouches[0].clientX;
            const deltaX = touchEndX - touchStartX;

            if (Math.abs(deltaX) > 50) {
                navigate(deltaX < 0 ? 1 : -1);
            }
        }, { passive: true });

        // Block background scroll on the modal backdrop (iOS)
        modalElement.addEventListener('touchmove', (event) => {
            if (event.target === modalElement || (event.target as Element).classList.contains('modal-dialog')) {
                event.preventDefault();
            }
        }, { passive: false });
    }

    // Keyboard navigation when modal is open
    document.getElementById('cardImageModal')?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') navigate(-1);
        if (event.key === 'ArrowRight') navigate(1);
    });
}
