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
 * @see docs/features.md F17.3 — Custom Tiptap extension for [[archetype:slug]] tags
 * @see docs/features.md F2.25 — Archetype variant URL anchors & enhanced archetype tags
 */

const ARCHETYPE_TAG_PATTERN = /\[\[archetype:([a-z0-9-]+)(?::([A-HJ-NP-Z0-9]{6}))?\]\]/;

/**
 * markdown-it inline rule that matches `[[archetype:slug]]` and `[[archetype:slug:shortTag]]` tokens.
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
        token.meta = { shortTag: match[2] ?? null };
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
            shortTag: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('data-archetype-short-tag'),
                renderHTML: (attributes: Record<string, string | null>) => {
                    if (!attributes.shortTag) {
                        return {};
                    }

                    return { 'data-archetype-short-tag': attributes.shortTag };
                },
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-archetype-slug]' }];
    },

    renderHTML({ HTMLAttributes }) {
        const slug = HTMLAttributes['data-archetype-slug'] ?? '';
        const shortTag = HTMLAttributes['data-archetype-short-tag'];
        const display = shortTag ? `${slug}:${shortTag}` : slug;

        return [
            'span',
            mergeAttributes(HTMLAttributes, { class: 'archetype-reference-badge' }),
            display,
        ];
    },

    addPasteRules() {
        return [
            nodePasteRule({
                find: /\[\[archetype:([a-z0-9-]+)(?::([A-HJ-NP-Z0-9]{6}))?\]\]/g,
                type: this.type,
                getAttributes: (match) => ({
                    slug: match[1],
                    shortTag: match[2] ?? null,
                }),
            }),
        ];
    },

    addStorage() {
        return {
            markdown: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                serialize(state: any, node: any) {
                    const { slug, shortTag } = node.attrs;
                    const tag = shortTag ? `[[archetype:${slug}:${shortTag}]]` : `[[archetype:${slug}]]`;
                    state.write(tag);
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
                            const shortTag = tokens[index].meta?.shortTag;

                            if (shortTag) {
                                return `<span data-archetype-slug="${slug}" data-archetype-short-tag="${shortTag}">${slug}:${shortTag}</span>`;
                            }

                            return `<span data-archetype-slug="${slug}">${slug}</span>`;
                        };
                    },
                },
            },
        };
    },
});

export default ArchetypeReference;
