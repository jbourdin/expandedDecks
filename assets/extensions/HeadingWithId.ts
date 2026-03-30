/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import Heading from '@tiptap/extension-heading';

/**
 * Extends the Tiptap Heading extension with Pandoc-style `{#anchor-id}`
 * support for Markdown round-tripping. The PHP `AttributesExtension`
 * (league/commonmark) handles the same syntax server-side.
 *
 * @see docs/features.md F17.1 — Rich text editor with Markdown
 */

const HEADING_ID_PATTERN = /\s*\{#([a-zA-Z0-9_-]+)\}\s*$/;

/**
 * markdown-it plugin that extracts `{#id}` from heading content
 * and sets it as an `id` attribute on the rendered HTML element.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function headingIdPlugin(markdownit: any): void {
    markdownit.core.ruler.push('heading_id', (state: { tokens: { type: string; children: { type: string; content: string }[] | null; attrSet: (key: string, value: string) => void }[] }) => {
        for (const token of state.tokens) {
            if (token.type !== 'heading_open') {
                continue;
            }

            const inline = state.tokens[state.tokens.indexOf(token) + 1];
            if (!inline || !inline.children) {
                continue;
            }

            const lastChild = inline.children[inline.children.length - 1];
            if (!lastChild || lastChild.type !== 'text') {
                continue;
            }

            const match = HEADING_ID_PATTERN.exec(lastChild.content);
            if (!match) {
                continue;
            }

            token.attrSet('id', match[1]);
            lastChild.content = lastChild.content.slice(0, match.index);
        }
    });
}

const HeadingWithId = Heading.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            id: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('id'),
                renderHTML: (attributes: Record<string, string | null>) => {
                    if (!attributes.id) {
                        return {};
                    }

                    return { id: attributes.id };
                },
            },
        };
    },

    addStorage() {
        return {
            markdown: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                serialize(state: any, node: any) {
                    const level = node.attrs.level as number;
                    state.write(`${'#'.repeat(level)} `);
                    state.renderInline(node);

                    if (node.attrs.id) {
                        state.write(` {#${node.attrs.id}}`);
                    }

                    state.closeBlock(node);
                },
                parse: {
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    setup(markdownit: any) {
                        headingIdPlugin(markdownit);
                    },
                },
            },
        };
    },
});

export default HeadingWithId;
