/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Reusable drag-and-drop table sorting with SortableJS.
 * Expects a <tbody> with:
 *   - id matching the provided selector
 *   - data-reorder-url — POST endpoint receiving a JSON array of IDs
 *   - <tr data-{idAttribute}-id="N"> rows
 *   - .sortable-handle elements as drag handles
 *   - .move-up-btn / .move-down-btn for accessible fallback
 *
 * @see docs/features.md F7.10 — Admin pages: drag-and-drop sorting
 * @see docs/features.md F18.12 — Admin drag-and-drop archetype ordering
 * @see docs/features.md F18.19 — Archetype variant ordering
 */

import Sortable from 'sortablejs';

export function initSortableTable(tbodyId: string, idAttribute: string): void {
    const tbody = document.getElementById(tbodyId) as HTMLTableSectionElement | null;
    if (!tbody) {
        return;
    }

    const getIds = (): number[] =>
        Array.from(tbody.querySelectorAll<HTMLTableRowElement>(`tr[data-${idAttribute}-id]`))
            .map((row) => Number(row.dataset[`${toCamelCase(idAttribute)}Id`]));

    const persistOrder = (): void => {
        const url = tbody.dataset.reorderUrl;
        if (!url) {
            return;
        }

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(getIds()),
        }).catch((error) => {
            console.error('Reorder failed:', error);
        });
    };

    Sortable.create(tbody, {
        handle: '.sortable-handle',
        animation: 150,
        ghostClass: 'table-active',
        onEnd: () => {
            persistOrder();
        },
    });

    // Accessible up/down arrow buttons
    tbody.addEventListener('click', (event) => {
        const target = event.target as HTMLElement;
        const button = target.closest('.move-up-btn, .move-down-btn') as HTMLButtonElement | null;
        if (!button) {
            return;
        }

        const row = button.closest('tr') as HTMLTableRowElement;
        if (!row) {
            return;
        }

        if (button.classList.contains('move-up-btn') && row.previousElementSibling) {
            row.parentNode?.insertBefore(row, row.previousElementSibling);
            persistOrder();
        } else if (button.classList.contains('move-down-btn') && row.nextElementSibling) {
            row.parentNode?.insertBefore(row.nextElementSibling, row);
            persistOrder();
        }
    });
}

function toCamelCase(kebab: string): string {
    return kebab.replace(/-([a-z])/g, (_, letter: string) => letter.toUpperCase());
}
