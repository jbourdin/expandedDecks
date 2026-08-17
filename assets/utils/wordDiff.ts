/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Word-level diff between two texts, for the staleness banner of the
 * contextual translation view: shows exactly what changed in the English
 * source since the translation was pinned (US-T5).
 *
 * Plain longest-common-subsequence over whitespace-separated tokens — small
 * inputs (content fields), no need for an external diff dependency.
 *
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 */

export interface WordDiffSegment {
    kind: 'equal' | 'removed' | 'added';
    text: string;
}

const tokenize = (text: string): string[] => text.split(/(\s+)/).filter((token) => token.length > 0);

const isWhitespace = (token: string): boolean => /^\s+$/.test(token);

/**
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 */
export function wordDiff(oldText: string, newText: string): WordDiffSegment[] {
    const oldTokens = tokenize(oldText);
    const newTokens = tokenize(newText);

    // LCS table over tokens (whitespace tokens compare like any other, which
    // keeps reconstruction faithful to the original spacing).
    const rows = oldTokens.length;
    const columns = newTokens.length;
    const table: number[][] = Array.from({ length: rows + 1 }, () => new Array<number>(columns + 1).fill(0));
    for (let row = rows - 1; row >= 0; row -= 1) {
        for (let column = columns - 1; column >= 0; column -= 1) {
            table[row][column] = oldTokens[row] === newTokens[column]
                ? table[row + 1][column + 1] + 1
                : Math.max(table[row + 1][column], table[row][column + 1]);
        }
    }

    const segments: WordDiffSegment[] = [];
    const push = (kind: WordDiffSegment['kind'], text: string): void => {
        if (text.length === 0) {
            return;
        }
        const last = segments[segments.length - 1];
        if (last !== undefined && last.kind === kind) {
            last.text += text;
            return;
        }
        segments.push({ kind, text });
    };

    let row = 0;
    let column = 0;
    while (row < rows && column < columns) {
        if (oldTokens[row] === newTokens[column]) {
            push('equal', oldTokens[row]);
            row += 1;
            column += 1;
        } else if (table[row + 1][column] >= table[row][column + 1]) {
            push('removed', oldTokens[row]);
            row += 1;
        } else {
            push('added', newTokens[column]);
            column += 1;
        }
    }
    while (row < rows) {
        push('removed', oldTokens[row]);
        row += 1;
    }
    while (column < columns) {
        push('added', newTokens[column]);
        column += 1;
    }

    // Whitespace-only changed segments are reflow noise, not content change:
    // a removed one disappears, an added one reads as plain spacing.
    const normalized: WordDiffSegment[] = [];
    for (const segment of segments) {
        if (segment.kind === 'removed' && isWhitespace(segment.text)) {
            continue;
        }
        const kind = segment.kind === 'added' && isWhitespace(segment.text) ? 'equal' : segment.kind;
        const last = normalized[normalized.length - 1];
        if (last !== undefined && last.kind === kind) {
            last.text += segment.text;
        } else {
            normalized.push({ kind, text: segment.text });
        }
    }

    return normalized;
}
