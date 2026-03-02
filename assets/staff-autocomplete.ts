/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Staff autocomplete widget.
 *
 * Targets <input data-staff-autocomplete data-search-url="..."> elements.
 * Fetches matching users from the search API and renders a dropdown.
 *
 * @see docs/features.md F3.5 — Assign event staff team
 */

interface UserResult {
    screenName: string;
    email: string;
    playerId: string | null;
}

export function initStaffAutocomplete(input: HTMLInputElement): void {
    const searchUrl = input.dataset.searchUrl;
    if (!searchUrl) return;

    const form = input.closest('form');
    if (!form) return;

    const hiddenInput = form.querySelector<HTMLInputElement>('input[name="user_query"]') as HTMLInputElement;
    if (!hiddenInput) return;

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let activeIndex = -1;
    let results: UserResult[] = [];

    // Create dropdown
    const dropdown = document.createElement('div');
    dropdown.className = 'staff-autocomplete-dropdown';
    dropdown.setAttribute('role', 'listbox');
    dropdown.setAttribute('id', 'staff-autocomplete-listbox');
    dropdown.style.display = 'none';
    input.parentElement!.style.position = 'relative';
    input.parentElement!.appendChild(dropdown);

    // ARIA attributes
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', 'staff-autocomplete-listbox');

    function renderDropdown(): void {
        dropdown.innerHTML = '';
        activeIndex = -1;

        if (results.length === 0) {
            dropdown.style.display = 'none';
            input.setAttribute('aria-expanded', 'false');
            return;
        }

        results.forEach((user, index) => {
            const item = document.createElement('div');
            item.className = 'staff-autocomplete-item';
            item.setAttribute('role', 'option');
            item.setAttribute('id', `staff-autocomplete-option-${index}`);
            item.setAttribute('aria-selected', 'false');

            const primary = document.createElement('span');
            primary.className = 'staff-autocomplete-primary';
            primary.textContent = user.screenName;
            item.appendChild(primary);

            const secondary = document.createElement('span');
            secondary.className = 'staff-autocomplete-secondary';
            const parts: string[] = [];
            if (user.email) parts.push(user.email);
            if (user.playerId) parts.push(user.playerId);
            secondary.textContent = parts.join(' · ');
            item.appendChild(secondary);

            item.addEventListener('mousedown', (e) => {
                e.preventDefault();
                selectResult(index);
            });

            dropdown.appendChild(item);
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
                input.setAttribute('aria-activedescendant', item.id);
            } else {
                item.classList.remove('active');
                item.setAttribute('aria-selected', 'false');
            }
        });
        activeIndex = index;
    }

    function selectResult(index: number): void {
        const user = results[index];
        if (!user) return;

        input.value = user.screenName;
        hiddenInput.value = user.screenName;
        closeDropdown();
    }

    function closeDropdown(): void {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
        results = [];
        activeIndex = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    async function fetchResults(query: string): Promise<void> {
        try {
            const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`);
            if (!response.ok) return;
            results = await response.json();
            renderDropdown();
        } catch {
            // Silently ignore network errors
        }
    }

    input.addEventListener('input', () => {
        const value = input.value.trim();
        hiddenInput.value = value;

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
        // Delay to allow mousedown on dropdown items
        setTimeout(() => closeDropdown(), 200);
    });
}

// Auto-init on DOM ready
document.querySelectorAll<HTMLInputElement>('input[data-staff-autocomplete]').forEach((input) => {
    initStaffAutocomplete(input);
});
