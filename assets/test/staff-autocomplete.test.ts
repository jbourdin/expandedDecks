/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { initStaffAutocomplete } from '../staff-autocomplete';

function createDOM(): { input: HTMLInputElement; hidden: HTMLInputElement; form: HTMLFormElement } {
    const form = document.createElement('form');

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'user_query';
    form.appendChild(hidden);

    const wrapper = document.createElement('div');
    form.appendChild(wrapper);

    const input = document.createElement('input');
    input.type = 'text';
    input.dataset.staffAutocomplete = '';
    input.dataset.searchUrl = '/api/user/search';
    wrapper.appendChild(input);

    document.body.appendChild(form);

    return { input, hidden, form };
}

function mockFetch(data: unknown[]): void {
    globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(data),
    });
}

describe('staff-autocomplete', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('does not fetch for queries shorter than 2 characters', () => {
        const { input } = createDOM();
        mockFetch([]);
        initStaffAutocomplete(input);

        input.value = 'A';
        input.dispatchEvent(new Event('input'));

        vi.advanceTimersByTime(400);

        expect(globalThis.fetch).not.toHaveBeenCalled();
    });

    it('debounces input before fetching', () => {
        const { input } = createDOM();
        mockFetch([]);
        initStaffAutocomplete(input);

        input.value = 'Ad';
        input.dispatchEvent(new Event('input'));

        // Should not have fetched yet
        expect(globalThis.fetch).not.toHaveBeenCalled();

        vi.advanceTimersByTime(300);

        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
        expect(globalThis.fetch).toHaveBeenCalledWith('/api/user/search?q=Ad');
    });

    it('renders dropdown on fetch response', async () => {
        const { input } = createDOM();
        const users = [
            { screenName: 'Admin', email: 'admin@example.com', playerId: null },
        ];
        mockFetch(users);
        initStaffAutocomplete(input);

        input.value = 'Ad';
        input.dispatchEvent(new Event('input'));

        vi.advanceTimersByTime(300);
        await vi.runAllTimersAsync();

        const dropdown = document.querySelector('.staff-autocomplete-dropdown');
        expect(dropdown).not.toBeNull();

        const items = dropdown!.querySelectorAll('.staff-autocomplete-item');
        expect(items).toHaveLength(1);

        const primary = items[0].querySelector('.staff-autocomplete-primary');
        expect(primary!.textContent).toBe('Admin');
    });

    it('navigates with arrow keys', async () => {
        const { input } = createDOM();
        const users = [
            { screenName: 'Admin', email: 'admin@example.com', playerId: null },
            { screenName: 'Alice', email: 'alice@example.com', playerId: 'PKM-001' },
        ];
        mockFetch(users);
        initStaffAutocomplete(input);

        input.value = 'A';
        // Need 2+ chars
        input.value = 'Ad';
        input.dispatchEvent(new Event('input'));
        vi.advanceTimersByTime(300);
        await vi.runAllTimersAsync();

        // ArrowDown should highlight first item
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown' }));
        const items = document.querySelectorAll('.staff-autocomplete-item');
        expect(items[0].classList.contains('active')).toBe(true);

        // ArrowDown again should highlight second item
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown' }));
        expect(items[0].classList.contains('active')).toBe(false);
        expect(items[1].classList.contains('active')).toBe(true);
    });

    it('selects with Enter and fills hidden input', async () => {
        const { input, hidden } = createDOM();
        const users = [
            { screenName: 'Admin', email: 'admin@example.com', playerId: null },
        ];
        mockFetch(users);
        initStaffAutocomplete(input);

        input.value = 'Ad';
        input.dispatchEvent(new Event('input'));
        vi.advanceTimersByTime(300);
        await vi.runAllTimersAsync();

        // ArrowDown + Enter
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown' }));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));

        expect(input.value).toBe('Admin');
        expect(hidden.value).toBe('Admin');

        // Dropdown should be closed
        const dropdown = document.querySelector('.staff-autocomplete-dropdown') as HTMLElement;
        expect(dropdown.style.display).toBe('none');
    });

    it('closes dropdown on Escape', async () => {
        const { input } = createDOM();
        const users = [
            { screenName: 'Admin', email: 'admin@example.com', playerId: null },
        ];
        mockFetch(users);
        initStaffAutocomplete(input);

        input.value = 'Ad';
        input.dispatchEvent(new Event('input'));
        vi.advanceTimersByTime(300);
        await vi.runAllTimersAsync();

        const dropdown = document.querySelector('.staff-autocomplete-dropdown') as HTMLElement;
        expect(dropdown.style.display).toBe('block');

        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(dropdown.style.display).toBe('none');
    });

    it('syncs hidden input on manual typing', () => {
        const { input, hidden } = createDOM();
        mockFetch([]);
        initStaffAutocomplete(input);

        input.value = 'SomeUser';
        input.dispatchEvent(new Event('input'));

        expect(hidden.value).toBe('SomeUser');
    });
});
