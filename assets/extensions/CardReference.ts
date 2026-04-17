/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { mergeAttributes, Node, nodePasteRule } from '@tiptap/core';

/**
 * @see docs/features.md F17.2 — Custom Tiptap extension for [[card:SET-NUM]] tags
 */

const CARD_TAG_PATTERN = /\[\[card:([A-Za-z0-9-]+)\]\]/;

/**
 * markdown-it inline rule that matches `[[card:XXX]]` tokens.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function cardReferenceRule(state: any, silent: boolean): boolean {
    const source = state.src.slice(state.pos);
    const match = CARD_TAG_PATTERN.exec(source);

    if (!match || match.index !== 0) {
        return false;
    }

    if (!silent) {
        const token = state.push('card_reference', '', 0);
        token.content = match[1];
    }

    state.pos += match[0].length;

    return true;
}

const CardReference = Node.create({
    name: 'cardReference',
    group: 'inline',
    inline: true,
    atom: true,

    addAttributes() {
        return {
            reference: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('data-card-reference'),
                renderHTML: (attributes: Record<string, string>) => ({
                    'data-card-reference': attributes.reference,
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-card-reference]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'span',
            mergeAttributes(HTMLAttributes, { class: 'card-reference-badge' }),
            HTMLAttributes['data-card-reference'] ?? '',
        ];
    },

    addPasteRules() {
        return [
            nodePasteRule({
                find: /\[\[card:([A-Za-z0-9-]+)\]\]/g,
                type: this.type,
                getAttributes: (match) => ({
                    reference: match[1],
                }),
            }),
        ];
    },

    addStorage() {
        return {
            markdown: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                serialize(state: any, node: any) {
                    state.write(`[[card:${node.attrs.reference}]]`);
                },
                parse: {
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    setup(markdownit: any) {
                        markdownit.inline.ruler.push('card_reference', cardReferenceRule);

                        markdownit.renderer.rules.card_reference = (
                            // eslint-disable-next-line @typescript-eslint/no-explicit-any
                            tokens: any[],
                            index: number,
                        ) => {
                            const reference = tokens[index].content;

                            return `<span data-card-reference="${reference}">${reference}</span>`;
                        };
                    },
                },
            },
        };
    },
});

export default CardReference;
