/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Walk-up autocomplete widgets for deck and borrower search.
 *
 * @see docs/features.md F4.12 — Walk-up lending (direct lend)
 */

interface AutocompleteResult {
    id: number;
    label: string;
    secondary: string;
}

function initAutocomplete(
    input: HTMLInputElement,
    hiddenInputName: string,
    mapResult: (item: Record<string, unknown>) => AutocompleteResult
): void {
    const searchUrl = input.dataset.searchUrl;
    if (!searchUrl) return;

    const form = input.closest('form');
    if (!form) return;

    const hiddenInput: HTMLInputElement | null = form.querySelector<HTMLInputElement>(`input[name="${hiddenInputName}"]`);
    if (!hiddenInput) return;
    const resolvedHiddenInput: HTMLInputElement = hiddenInput;

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let activeIndex = -1;
    let results: AutocompleteResult[] = [];

    const dropdown = document.createElement('div');
    dropdown.className = 'staff-autocomplete-dropdown';
    dropdown.setAttribute('role', 'listbox');
    dropdown.style.display = 'none';
    input.parentElement!.style.position = 'relative';
    input.parentElement!.appendChild(dropdown);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    function renderDropdown(): void {
        dropdown.innerHTML = '';
        activeIndex = -1;

        if (results.length === 0) {
            dropdown.style.display = 'none';
            input.setAttribute('aria-expanded', 'false');
            return;
        }

        results.forEach((item, index) => {
            const el = document.createElement('div');
            el.className = 'staff-autocomplete-item';
            el.setAttribute('role', 'option');
            el.setAttribute('aria-selected', 'false');

            const primary = document.createElement('span');
            primary.className = 'staff-autocomplete-primary';
            primary.textContent = item.label;
            el.appendChild(primary);

            if (item.secondary) {
                const secondary = document.createElement('span');
                secondary.className = 'staff-autocomplete-secondary';
                secondary.textContent = item.secondary;
                el.appendChild(secondary);
            }

            el.addEventListener('mousedown', (e) => {
                e.preventDefault();
                selectResult(index);
            });

            dropdown.appendChild(el);
        });

        dropdown.style.display = 'block';
        input.setAttribute('aria-expanded', 'true');
    }

    function setActiveItem(index: number): void {
        const items = dropdown.querySelectorAll<HTMLElement>('.staff-autocomplete-item');
        items.forEach((item, i) => {
            if (i === index) {
                item.classList.add('active');
                item.setAttribute('aria-selected', 'true');
            } else {
                item.classList.remove('active');
                item.setAttribute('aria-selected', 'false');
            }
        });
        activeIndex = index;
    }

    function selectResult(index: number): void {
        const item = results[index];
        if (!item) return;

        input.value = item.label;
        resolvedHiddenInput.value = String(item.id);
        closeDropdown();
    }

    function closeDropdown(): void {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
        results = [];
        activeIndex = -1;
        input.setAttribute('aria-expanded', 'false');
    }

    async function fetchResults(query: string): Promise<void> {
        try {
            const response = await fetch(`${searchUrl}&q=${encodeURIComponent(query)}`);
            if (!response.ok) return;
            const data = await response.json();
            results = data.map(mapResult);
            renderDropdown();
        } catch {
            // Silently ignore network errors
        }
    }

    input.addEventListener('input', () => {
        const value = input.value.trim();
        hiddenInput.value = '';

        if (debounceTimer) clearTimeout(debounceTimer);

        if (value.length < 2) {
            closeDropdown();
            return;
        }

        debounceTimer = setTimeout(() => {
            fetchResults(value);
        }, 300);
    });

    input.addEventListener('keydown', (e: KeyboardEvent) => {
        if (dropdown.style.display === 'none') return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const next = activeIndex < results.length - 1 ? activeIndex + 1 : 0;
            setActiveItem(next);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prev = activeIndex > 0 ? activeIndex - 1 : results.length - 1;
            setActiveItem(prev);
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            selectResult(activeIndex);
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    input.addEventListener('blur', () => {
        setTimeout(() => closeDropdown(), 200);
    });
}

// Deck autocomplete
document.querySelectorAll<HTMLInputElement>('input[data-walk-up-deck-autocomplete]').forEach((input) => {
    initAutocomplete(input, 'deck_id', (item) => ({
        id: item.id as number,
        label: item.name as string,
        secondary: `${item.ownerName as string} · ${item.shortTag as string}`,
    }));
});

// User autocomplete
document.querySelectorAll<HTMLInputElement>('input[data-walk-up-user-autocomplete]').forEach((input) => {
    initAutocomplete(input, 'borrower_id', (item) => ({
        id: item.id as number,
        label: item.screenName as string,
        secondary: [item.email, item.playerId].filter(Boolean).join(' · '),
    }));
});
