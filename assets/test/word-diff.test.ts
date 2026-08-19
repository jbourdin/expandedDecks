/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { describe, expect, it } from 'vitest';
import { wordDiff } from '../utils/wordDiff';

/**
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 */
describe('wordDiff', () => {
    it('returns a single equal segment for identical texts', () => {
        expect(wordDiff('same text here', 'same text here')).toEqual([
            { kind: 'equal', text: 'same text here' },
        ]);
    });

    it('detects an inserted word', () => {
        const segments = wordDiff('lock the board', 'lock the whole board');
        const added = segments.filter((segment) => segment.kind === 'added');
        expect(added).toHaveLength(1);
        expect(added[0].text.trim()).toBe('whole');
        expect(segments.some((segment) => segment.kind === 'removed')).toBe(false);
        expect(segments.map((segment) => segment.text).join('')).toBe('lock the whole board');
    });

    it('detects a removed word', () => {
        const segments = wordDiff('a very strong deck', 'a strong deck');
        const removed = segments.filter((segment) => segment.kind === 'removed');
        expect(removed).toHaveLength(1);
        expect(removed[0].text.trim()).toBe('very');
        expect(segments.some((segment) => segment.kind === 'added')).toBe(false);
        expect(
            segments.filter((segment) => segment.kind !== 'removed').map((segment) => segment.text).join(''),
        ).toBe('a strong deck');
    });

    it('detects a replacement as removal plus addition', () => {
        const segments = wordDiff('Iron Thorns locks abilities', 'Iron Thorns disables abilities');
        expect(segments.filter((segment) => segment.kind === 'removed').map((segment) => segment.text.trim())).toEqual(['locks']);
        expect(segments.filter((segment) => segment.kind === 'added').map((segment) => segment.text.trim())).toEqual(['disables']);
    });

    it('handles empty old text as a pure addition', () => {
        expect(wordDiff('', 'brand new content')).toEqual([
            { kind: 'added', text: 'brand new content' },
        ]);
    });

    it('handles empty new text as a pure removal', () => {
        expect(wordDiff('gone', '')).toEqual([{ kind: 'removed', text: 'gone' }]);
    });

    it('keeps multiline structure and marks only the changed line', () => {
        const segments = wordDiff('First line\nSecond line', 'First line\nSecond edited line');
        const added = segments.filter((segment) => segment.kind === 'added');
        expect(added).toHaveLength(1);
        expect(added[0].text.trim()).toBe('edited');
        expect(segments.map((segment) => segment.text).join('')).toContain('First line\nSecond');
    });

    it('does not flag whitespace-only reflow as a change', () => {
        const segments = wordDiff('one two', 'one  two');
        expect(segments.every((segment) => segment.kind === 'equal')).toBe(true);
    });
});
