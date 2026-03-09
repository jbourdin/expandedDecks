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

const isTouchDevice = (): boolean => window.matchMedia('(max-width: 767.98px)').matches;

interface CardEntry {
    src: string;
    alt: string;
}

const cards: CardEntry[] = [];
let currentIndex = 0;

document.querySelectorAll<HTMLElement>('.card-hover').forEach((el) => {
    const img = el.querySelector<HTMLImageElement>('.card-hover-img');
    if (!img) return;

    const index = cards.length;
    cards.push({ src: img.src, alt: img.alt });

    el.addEventListener('mouseenter', () => {
        if (isTouchDevice()) return;

        const rect = el.getBoundingClientRect();
        if (rect.top < 360) {
            img.classList.add('show-below');
        } else {
            img.classList.remove('show-below');
        }
    });

    el.addEventListener('click', (event) => {
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

function showCard(index: number): void {
    const modalImg = document.getElementById('cardImageModalImg') as HTMLImageElement | null;
    const modalLabel = document.getElementById('cardImageModalLabel');
    const counter = document.getElementById('cardImageModalCounter');

    if (!modalImg || index < 0 || index >= cards.length) return;

    modalImg.src = cards[index].src;
    modalImg.alt = cards[index].alt;
    if (modalLabel) {
        modalLabel.textContent = cards[index].alt;
    }
    if (counter) {
        counter.textContent = `${index + 1} / ${cards.length}`;
    }
}

function navigate(direction: number): void {
    currentIndex = (currentIndex + direction + cards.length) % cards.length;
    showCard(currentIndex);
}

// Prev/next button handlers
document.getElementById('cardImageModalPrev')?.addEventListener('click', () => navigate(-1));
document.getElementById('cardImageModalNext')?.addEventListener('click', () => navigate(1));

// Touch swipe support
const modalBody = document.querySelector('#cardImageModal .modal-body');
if (modalBody) {
    let touchStartX = 0;

    modalBody.addEventListener('touchstart', (event) => {
        touchStartX = (event as TouchEvent).touches[0].clientX;
    }, { passive: true });

    modalBody.addEventListener('touchend', (event) => {
        const touchEndX = (event as TouchEvent).changedTouches[0].clientX;
        const deltaX = touchEndX - touchStartX;

        if (Math.abs(deltaX) > 50) {
            navigate(deltaX < 0 ? 1 : -1);
        }
    }, { passive: true });
}

// Keyboard navigation when modal is open
document.getElementById('cardImageModal')?.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft') navigate(-1);
    if (event.key === 'ArrowRight') navigate(1);
});
