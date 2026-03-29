/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { mergeAttributes, Node } from '@tiptap/core';

/**
 * @see docs/features.md F17.2 — Custom Tiptap extension for [[deck:SHORT_TAG]] tags
 */

const DECK_TAG_PATTERN = /\[\[deck:([A-HJ-NP-Z0-9]{6})\]\]/;

/**
 * markdown-it inline rule that matches `[[deck:XXXXXX]]` tokens.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function deckReferenceRule(state: any, silent: boolean): boolean {
    const source = state.src.slice(state.pos);
    const match = DECK_TAG_PATTERN.exec(source);

    if (!match || match.index !== 0) {
        return false;
    }

    if (!silent) {
        const token = state.push('deck_reference', '', 0);
        token.content = match[1];
    }

    state.pos += match[0].length;

    return true;
}

const DeckReference = Node.create({
    name: 'deckReference',
    group: 'inline',
    inline: true,
    atom: true,

    addAttributes() {
        return {
            shortTag: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('data-deck-short-tag'),
                renderHTML: (attributes: Record<string, string>) => ({
                    'data-deck-short-tag': attributes.shortTag,
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-deck-short-tag]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'span',
            mergeAttributes(HTMLAttributes, { class: 'deck-reference-badge' }),
            HTMLAttributes['data-deck-short-tag'] ?? '',
        ];
    },

    addStorage() {
        return {
            markdown: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                serialize(state: any, node: any) {
                    state.write(`[[deck:${node.attrs.shortTag}]]`);
                },
                parse: {
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    setup(markdownit: any) {
                        markdownit.inline.ruler.push('deck_reference', deckReferenceRule);

                        markdownit.renderer.rules.deck_reference = (
                            // eslint-disable-next-line @typescript-eslint/no-explicit-any
                            tokens: any[],
                            index: number,
                        ) => {
                            const shortTag = tokens[index].content;

                            return `<span data-deck-short-tag="${shortTag}">${shortTag}</span>`;
                        };
                    },
                },
            },
        };
    },
});

export default DeckReference;
