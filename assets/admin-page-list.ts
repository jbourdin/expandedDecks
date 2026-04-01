/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Drag-and-drop page reordering within a category using SortableJS.
 * Also handles accessible up/down arrow buttons as a fallback.
 *
 * @see docs/features.md F7.10 — Admin pages: filter by category and drag-and-drop sorting
 */

import Sortable from 'sortablejs';

function getPageIds(tbody: HTMLTableSectionElement): number[] {
    return Array.from(tbody.querySelectorAll<HTMLTableRowElement>('tr[data-page-id]'))
        .map((row) => Number(row.dataset.pageId));
}

function persistOrder(tbody: HTMLTableSectionElement): void {
    const url = tbody.dataset.reorderUrl;
    if (!url) {
        return;
    }

    const pageIds = getPageIds(tbody);

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(pageIds),
    }).catch((error) => {
        console.error('Reorder failed:', error);
    });
}

function initSortable(): void {
    const tbody = document.getElementById('sortable-pages') as HTMLTableSectionElement | null;
    if (!tbody) {
        return;
    }

    // Drag-and-drop via SortableJS
    Sortable.create(tbody, {
        handle: '.sortable-handle',
        animation: 150,
        ghostClass: 'table-active',
        onEnd: () => {
            persistOrder(tbody);
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
            persistOrder(tbody);
        } else if (button.classList.contains('move-down-btn') && row.nextElementSibling) {
            row.parentNode?.insertBefore(row.nextElementSibling, row);
            persistOrder(tbody);
        }
    });
}

initSortable();
