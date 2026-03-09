/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React, { act } from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MantineProvider } from '@mantine/core';
import AsyncAutocomplete, { type AutocompleteItem } from '../components/AsyncAutocomplete';

function mapUser(item: Record<string, unknown>): AutocompleteItem {
    return {
        value: item.screenName as string,
        label: item.screenName as string,
        secondary: [item.email, item.playerId].filter(Boolean).join(' · '),
    };
}

function renderComponent(props: Partial<React.ComponentProps<typeof AsyncAutocomplete>> = {}) {
    return render(
        <MantineProvider>
            <AsyncAutocomplete
                searchUrl="/api/user/search"
                hiddenInputName="user_query"
                placeholder="Search users..."
                mapResult={mapUser}
                {...props}
            />
        </MantineProvider>,
    );
}

function mockFetch(data: unknown[]) {
    globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(data),
    });
}

describe('AsyncAutocomplete', () => {
    beforeEach(() => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('renders with placeholder', () => {
        mockFetch([]);
        renderComponent();

        expect(screen.getByPlaceholderText('Search users...')).toBeInTheDocument();
    });

    it('renders a hidden input with the given name', () => {
        mockFetch([]);
        const { container } = renderComponent();

        const hidden = container.querySelector('input[name="user_query"]') as HTMLInputElement;
        expect(hidden).toBeInTheDocument();
        expect(hidden.type).toBe('hidden');
        expect(hidden.value).toBe('');
    });

    it('does not fetch for queries shorter than 2 characters', async () => {
        mockFetch([]);
        renderComponent();

        const input = screen.getByPlaceholderText('Search users...');
        await userEvent.type(input, 'A');

        await vi.advanceTimersByTimeAsync(400);

        expect(globalThis.fetch).not.toHaveBeenCalled();
    });

    it('debounces before fetching', async () => {
        mockFetch([]);
        renderComponent();

        const input = screen.getByPlaceholderText('Search users...');
        await userEvent.type(input, 'Ad');

        // Should not have fetched yet (debounce is 300ms)
        expect(globalThis.fetch).not.toHaveBeenCalled();

        await act(() => vi.advanceTimersByTimeAsync(350));

        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
        expect(globalThis.fetch).toHaveBeenCalledWith(
            '/api/user/search?q=Ad',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
    });

    it('renders dropdown options from API response', async () => {
        const users = [
            { screenName: 'Admin', email: 'admin@example.com', playerId: null },
            { screenName: 'Alice', email: 'alice@example.com', playerId: 'PKM-001' },
        ];
        mockFetch(users);
        renderComponent();

        const input = screen.getByPlaceholderText('Search users...');
        await userEvent.type(input, 'Ad');
        await vi.advanceTimersByTimeAsync(350);

        await waitFor(() => {
            expect(screen.getByText('Admin')).toBeInTheDocument();
            expect(screen.getByText('Alice')).toBeInTheDocument();
        });

        expect(screen.getByText('admin@example.com')).toBeInTheDocument();
        expect(screen.getByText('alice@example.com · PKM-001')).toBeInTheDocument();
    });

    it('selects item and updates hidden input', async () => {
        const users = [
            { screenName: 'Admin', email: 'admin@example.com', playerId: null },
        ];
        mockFetch(users);
        const { container } = renderComponent();

        const input = screen.getByPlaceholderText('Search users...');
        await userEvent.type(input, 'Ad');
        await vi.advanceTimersByTimeAsync(350);

        await waitFor(() => {
            expect(screen.getByText('Admin')).toBeInTheDocument();
        });

        await userEvent.click(screen.getByText('Admin'));

        expect(input).toHaveValue('Admin');

        const hidden = container.querySelector('input[name="user_query"]') as HTMLInputElement;
        expect(hidden.value).toBe('Admin');
    });

    it('calls onSelect callback when item is selected', async () => {
        const users = [
            { screenName: 'Admin', email: 'admin@example.com', playerId: null },
        ];
        mockFetch(users);
        const onSelect = vi.fn();
        renderComponent({ onSelect });

        const input = screen.getByPlaceholderText('Search users...');
        await userEvent.type(input, 'Ad');
        await vi.advanceTimersByTimeAsync(350);

        await waitFor(() => {
            expect(screen.getByText('Admin')).toBeInTheDocument();
        });

        await userEvent.click(screen.getByText('Admin'));

        expect(onSelect).toHaveBeenCalledWith({
            value: 'Admin',
            label: 'Admin',
            secondary: 'admin@example.com',
        });
    });

    it('clears hidden input when user types after selection', async () => {
        const users = [
            { screenName: 'Admin', email: 'admin@example.com', playerId: null },
        ];
        mockFetch(users);
        const { container } = renderComponent();

        const input = screen.getByPlaceholderText('Search users...');
        await userEvent.type(input, 'Ad');
        await vi.advanceTimersByTimeAsync(350);

        await waitFor(() => {
            expect(screen.getByText('Admin')).toBeInTheDocument();
        });

        await userEvent.click(screen.getByText('Admin'));

        const hidden = container.querySelector('input[name="user_query"]') as HTMLInputElement;
        expect(hidden.value).toBe('Admin');

        // Typing again should clear the hidden value
        await userEvent.type(input, 'x');
        expect(hidden.value).toBe('');
    });

    it('appends query param with & when URL already has ?', async () => {
        mockFetch([]);
        renderComponent({ searchUrl: '/api/search?event_id=5' });

        const input = screen.getByPlaceholderText('Search users...');
        await userEvent.type(input, 'Ad');
        await act(() => vi.advanceTimersByTimeAsync(350));

        expect(globalThis.fetch).toHaveBeenCalledWith(
            '/api/search?event_id=5&q=Ad',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
    });

    it('shows "No results" when API returns empty array', async () => {
        mockFetch([]);
        renderComponent();

        const input = screen.getByPlaceholderText('Search users...');
        await userEvent.type(input, 'Xyz');
        await vi.advanceTimersByTimeAsync(350);

        await waitFor(() => {
            expect(screen.getByText('No results')).toBeInTheDocument();
        });
    });
});
