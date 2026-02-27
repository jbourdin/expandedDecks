/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Card image hover: toggles `show-below` class so the preview stays
 * within the viewport (above by default, below when near the top).
 *
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 */
document.querySelectorAll<HTMLElement>('.card-hover').forEach((el) => {
    el.addEventListener('mouseenter', () => {
        const img = el.querySelector('.card-hover-img');
        if (!img) return;

        const rect = el.getBoundingClientRect();
        if (rect.top < 360) {
            img.classList.add('show-below');
        } else {
            img.classList.remove('show-below');
        }
    });
});
