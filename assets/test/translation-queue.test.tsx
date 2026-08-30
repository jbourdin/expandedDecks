/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import AppMantineProvider from '../components/AppMantineProvider';
import TranslationQueue, { editUrl, filterByLocale, localesOf, stateLabel } from '../components/TranslationQueue';

const labels = {
    tabPages: 'Pages',
    tabArchetypes: 'Archetypes',
    tabMenuCategories: 'Menu categories',
    tabBannedCards: 'Banned cards',
    tabStapleCards: 'Staples',
    localeAll: 'All languages',
    badgeUntranslated: 'To translate',
    flavorContributor: 'My work',
    flavorReviewer: 'To review',
    stateDraft: 'Draft',
    statePendingReview: 'Awaiting review',
    stateRejected: 'Rejected',
    badgeOutdated: 'Source updated',
    badgePendingVariants: 'variants pending',
    badgeOutdatedVariants: 'variants outdated',
    empty: 'Nothing to translate right now.',
    open: 'Open',
    loading: 'Loading…',
};

const pageRow = {
    contentType: 'page',
    contentId: 7,
    label: 'Welcome page',
    locale: 'fr',
    state: 'draft',
    sourceOutdated: true,
    pendingVariants: 0,
    outdatedVariants: 0,
};

/**
 * @see docs/features.md F9.12 — Translation queue
 */
describe('TranslationQueue', () => {
    it('builds edit URLs from the workspace base URL', () => {
        expect(editUrl('/admin/translations', pageRow)).toBe('/admin/translations/page/7/fr');
    });

    it('maps revision states to their labels', () => {
        expect(stateLabel(labels, 'draft')).toBe('Draft');
        expect(stateLabel(labels, 'pending_review')).toBe('Awaiting review');
        expect(stateLabel(labels, 'rejected')).toBe('Rejected');
        expect(stateLabel(labels, null)).toBeNull();
    });

    it('renders contributor rows with state and outdated badges', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            json: () => Promise.resolve({
                contributor: { pages: [pageRow], archetypes: [], menuCategories: [], bannedCards: [], stapleCards: [] },
                reviewer: null,
            }),
        }));

        render(
            <AppMantineProvider>
                <TranslationQueue queueUrl="/admin/translations/queue-data" baseUrl="/admin/translations" labels={labels} />
            </AppMantineProvider>,
        );

        await waitFor(() => {
            expect(screen.getByText('Welcome page')).toBeTruthy();
        });
        expect(screen.getByText('Draft')).toBeTruthy();
        expect(screen.getByText('Source updated')).toBeTruthy();
        expect(screen.getByText('Open').closest('a')?.getAttribute('href')).toBe('/admin/translations/page/7/fr');
        // Single flavor: no toggle rendered.
        expect(screen.queryByText('To review')).toBeNull();

        vi.unstubAllGlobals();
    });

    it('shows the reviewer flavor with archetype variant counters', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            json: () => Promise.resolve({
                contributor: null,
                reviewer: {
                    pages: [],
                    archetypes: [{
                        contentType: 'archetype',
                        contentId: 3,
                        label: 'Iron Thorns',
                        locale: 'fr',
                        state: null,
                        sourceOutdated: false,
                        pendingVariants: 2,
                        outdatedVariants: 1,
                    }],
                    menuCategories: [],
                    bannedCards: [],
                    stapleCards: [],
                },
            }),
        }));

        render(
            <AppMantineProvider>
                <TranslationQueue queueUrl="/admin/translations/queue-data" baseUrl="/admin/translations" labels={labels} />
            </AppMantineProvider>,
        );

        await waitFor(() => {
            expect(screen.getByText('Iron Thorns')).toBeTruthy();
        });
        // The archetypes tab needs activating to see its rows? Rows live in the
        // archetypes panel; Mantine keeps inactive panels unmounted, so switch tab.
        screen.getByText('Archetypes').click();
        await waitFor(() => {
            expect(screen.getByText('2 variants pending')).toBeTruthy();
        });
        expect(screen.getByText('1 variants outdated')).toBeTruthy();

        vi.unstubAllGlobals();
    });

    /**
     * @see docs/features.md F9.19 — Queue card tabs & locale filter
     */
    it('collects the distinct locales across every tab, sorted', () => {
        const data = {
            pages: [pageRow],
            archetypes: [{ ...pageRow, contentType: 'archetype', locale: 'de' }],
            menuCategories: [],
            bannedCards: [{ ...pageRow, contentType: 'banned_card', locale: 'fr' }],
            stapleCards: [],
        };
        expect(localesOf(data)).toEqual(['de', 'fr']);
    });

    it('filters rows by locale, null meaning no filter', () => {
        const rows = [pageRow, { ...pageRow, contentId: 8, locale: 'de' }];
        expect(filterByLocale(rows, null)).toHaveLength(2);
        expect(filterByLocale(rows, 'de').map((row) => row.contentId)).toEqual([8]);
    });

    it('renders the locale filter only when several locales have work', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            json: () => Promise.resolve({
                contributor: {
                    pages: [pageRow, { ...pageRow, contentId: 9, label: 'Willkommen', locale: 'de' }],
                    archetypes: [],
                    menuCategories: [],
                    bannedCards: [],
                    stapleCards: [],
                },
                reviewer: null,
            }),
        }));

        render(
            <AppMantineProvider>
                <TranslationQueue queueUrl="/admin/translations/queue-data" baseUrl="/admin/translations" labels={labels} />
            </AppMantineProvider>,
        );

        await waitFor(() => {
            expect(screen.getByText('All languages')).toBeTruthy();
        });
        expect(screen.getByText('Welcome page')).toBeTruthy();
        expect(screen.getByText('Willkommen')).toBeTruthy();

        vi.unstubAllGlobals();
    });

    it('lists banned and staple cards in their own tabs', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            json: () => Promise.resolve({
                contributor: {
                    pages: [],
                    archetypes: [],
                    menuCategories: [],
                    bannedCards: [{ ...pageRow, contentType: 'banned_card', contentId: 4, label: 'Lysandre Prime', state: null, sourceOutdated: false }],
                    stapleCards: [{ ...pageRow, contentType: 'staple_card', contentId: 5, label: 'Quick Ball', state: 'draft', sourceOutdated: false }],
                },
                reviewer: null,
            }),
        }));

        render(
            <AppMantineProvider>
                <TranslationQueue queueUrl="/admin/translations/queue-data" baseUrl="/admin/translations" labels={labels} />
            </AppMantineProvider>,
        );

        await waitFor(() => {
            expect(screen.getByText('Banned cards')).toBeTruthy();
        });
        screen.getByText('Banned cards').click();
        await waitFor(() => {
            expect(screen.getByText('Lysandre Prime')).toBeTruthy();
        });
        expect(screen.getByText('To translate')).toBeTruthy();

        screen.getByText('Staples').click();
        await waitFor(() => {
            expect(screen.getByText('Quick Ball')).toBeTruthy();
        });

        vi.unstubAllGlobals();
    });
});
