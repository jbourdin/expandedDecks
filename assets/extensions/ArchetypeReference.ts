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
 * @see docs/features.md F17.3 — Custom Tiptap extension for [[archetype:slug]] tags
 */

const ARCHETYPE_TAG_PATTERN = /\[\[archetype:([a-z0-9-]+)\]\]/;

/**
 * markdown-it inline rule that matches `[[archetype:slug]]` tokens.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function archetypeReferenceRule(state: any, silent: boolean): boolean {
    const source = state.src.slice(state.pos);
    const match = ARCHETYPE_TAG_PATTERN.exec(source);

    if (!match || match.index !== 0) {
        return false;
    }

    if (!silent) {
        const token = state.push('archetype_reference', '', 0);
        token.content = match[1];
    }

    state.pos += match[0].length;

    return true;
}

const ArchetypeReference = Node.create({
    name: 'archetypeReference',
    group: 'inline',
    inline: true,
    atom: true,

    addAttributes() {
        return {
            slug: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('data-archetype-slug'),
                renderHTML: (attributes: Record<string, string>) => ({
                    'data-archetype-slug': attributes.slug,
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-archetype-slug]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'span',
            mergeAttributes(HTMLAttributes, { class: 'archetype-reference-badge' }),
            HTMLAttributes['data-archetype-slug'] ?? '',
        ];
    },

    addStorage() {
        return {
            markdown: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                serialize(state: any, node: any) {
                    state.write(`[[archetype:${node.attrs.slug}]]`);
                },
                parse: {
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    setup(markdownit: any) {
                        markdownit.inline.ruler.push('archetype_reference', archetypeReferenceRule);

                        markdownit.renderer.rules.archetype_reference = (
                            // eslint-disable-next-line @typescript-eslint/no-explicit-any
                            tokens: any[],
                            index: number,
                        ) => {
                            const slug = tokens[index].content;

                            return `<span data-archetype-slug="${slug}">${slug}</span>`;
                        };
                    },
                },
            },
        };
    },
});

export default ArchetypeReference;
