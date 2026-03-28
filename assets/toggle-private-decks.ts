/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Toggle visibility of private deck rows in event deck selection tables.
 *
 * Each `.toggle-private-decks` button controls `.private-deck-row` elements
 * inside the container referenced by its `data-target` attribute.
 */
document.querySelectorAll<HTMLButtonElement>('.toggle-private-decks').forEach((button) => {
    const targetSelector = button.getAttribute('data-target');
    if (!targetSelector) {
        return;
    }

    const container = document.querySelector(targetSelector);
    if (!container) {
        return;
    }

    const labelShow = button.getAttribute('data-label-show') ?? 'Show private decks';
    const labelHide = button.getAttribute('data-label-hide') ?? 'Hide private decks';

    button.addEventListener('click', () => {
        const rows = container.querySelectorAll('.private-deck-row');
        const isHidden = rows.length > 0 && rows[0].classList.contains('d-none');

        rows.forEach((row) => {
            row.classList.toggle('d-none', !isHidden);
        });

        button.textContent = isHidden ? labelHide : labelShow;
    });
});
