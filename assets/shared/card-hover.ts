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
 * through all card images in list order.
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */

interface CardEntry {
    src: string;
    name: string;
    quantity: number;
}

const isTouchDevice = (): boolean => window.matchMedia('(max-width: 767.98px)').matches;

export function initCardHover(): void {
    const cards: CardEntry[] = [];
    let currentIndex = 0;

    function showCard(index: number): void {
        const modalImg = document.getElementById('cardImageModalImg') as HTMLImageElement | null;
        const modalLabel = document.getElementById('cardImageModalLabel');

        if (!modalImg || index < 0 || index >= cards.length) return;

        const card = cards[index];
        modalImg.src = card.src;
        modalImg.alt = card.name;
        if (modalLabel) {
            modalLabel.textContent = card.quantity > 1 ? `${card.quantity} x ${card.name}` : card.name;
        }
    }

    function navigate(direction: number): void {
        currentIndex = (currentIndex + direction + cards.length) % cards.length;
        showCard(currentIndex);
    }

    document.querySelectorAll<HTMLElement>('.card-hover').forEach((element) => {
        const img = element.querySelector<HTMLImageElement>('.card-hover-img');
        if (!img) return;

        const index = cards.length;
        const quantity = parseInt(element.dataset.quantity || '1', 10);
        cards.push({ src: img.src, name: img.alt, quantity });

        element.addEventListener('mouseenter', () => {
            if (isTouchDevice()) return;

            const rect = element.getBoundingClientRect();
            if (rect.top < 360) {
                img.classList.add('show-below');
            } else {
                img.classList.remove('show-below');
            }
        });

        element.addEventListener('click', (event) => {
            if (!isTouchDevice()) return;

            event.preventDefault();
            currentIndex = index;
            showCard(currentIndex);

            const modalElement = document.getElementById('cardImageModal');
            if (!modalElement) return;

            const bsModal = Modal.getOrCreateInstance(modalElement);
            bsModal.show();
        });
    });

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
