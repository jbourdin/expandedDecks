/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Drag-and-drop category reordering using SortableJS.
 * Also handles accessible up/down arrow buttons as a fallback.
 *
 * @see docs/features.md F11.2 — Menu categories
 */

import Sortable from 'sortablejs';

function getCategoryIds(tbody: HTMLTableSectionElement): number[] {
    return Array.from(tbody.querySelectorAll<HTMLTableRowElement>('tr[data-category-id]'))
        .map((row) => Number(row.dataset.categoryId));
}

function persistOrder(tbody: HTMLTableSectionElement): void {
    const url = tbody.dataset.reorderUrl;
    if (!url) {
        return;
    }

    const categoryIds = getCategoryIds(tbody);

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(categoryIds),
    }).catch((error) => {
        console.error('Reorder failed:', error);
    });
}

function initSortable(): void {
    const tbody = document.getElementById('sortable-categories') as HTMLTableSectionElement | null;
    if (!tbody) {
        return;
    }

    Sortable.create(tbody, {
        handle: '.sortable-handle',
        animation: 150,
        ghostClass: 'table-active',
        onEnd: () => {
            persistOrder(tbody);
        },
    });

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
