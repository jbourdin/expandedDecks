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
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */

const isTouchDevice = (): boolean => window.matchMedia('(max-width: 767.98px)').matches;

document.querySelectorAll<HTMLElement>('.card-hover').forEach((el) => {
    el.addEventListener('mouseenter', () => {
        if (isTouchDevice()) return;

        const img = el.querySelector('.card-hover-img');
        if (!img) return;

        const rect = el.getBoundingClientRect();
        if (rect.top < 360) {
            img.classList.add('show-below');
        } else {
            img.classList.remove('show-below');
        }
    });

    el.addEventListener('click', (event) => {
        if (!isTouchDevice()) return;

        const img = el.querySelector<HTMLImageElement>('.card-hover-img');
        if (!img) return;

        event.preventDefault();

        const modalElement = document.getElementById('cardImageModal');
        const modalImg = document.getElementById('cardImageModalImg') as HTMLImageElement | null;
        const modalLabel = document.getElementById('cardImageModalLabel');

        if (!modalElement || !modalImg) return;

        modalImg.src = img.src;
        modalImg.alt = img.alt;
        if (modalLabel) {
            modalLabel.textContent = img.alt;
        }

        const bsModal = Modal.getOrCreateInstance(modalElement);
        bsModal.show();
    });
});
