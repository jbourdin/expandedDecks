/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import Image from '@tiptap/extension-image';

/**
 * Extends the Tiptap Image extension with resize handles, float/alignment,
 * and Pandoc-style `{.class width=X height=Y}` Markdown serialization.
 *
 * @see docs/features.md F17.5 — Image drag-and-drop in the editor
 * @see docs/features.md F17.7 — Image float and alignment
 */

const ATTRIBUTES_PATTERN = /\{([^}]+)\}$/;

/**
 * Parse Pandoc-style attributes string into a key-value map.
 * Supports: `{width=400 height=300 #id .class .another-class}`
 */
function parseAttributes(attributeString: string): Record<string, string> {
    const attributes: Record<string, string> = {};

    for (const part of attributeString.split(/\s+/)) {
        if (part.startsWith('#')) {
            attributes.id = part.slice(1);
        } else if (part.startsWith('.')) {
            const existing = attributes.class ?? '';
            attributes.class = (existing ? `${existing} ` : '') + part.slice(1);
        } else if (part.includes('=')) {
            const [key, value] = part.split('=', 2);
            attributes[key] = value;
        }
    }

    return attributes;
}

/**
 * markdown-it plugin that parses Pandoc-style `{key=value .class}` attributes
 * on image tokens and attaches width/height/class to the token attrs.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function imageAttributesPlugin(markdownit: any): void {
    const defaultImageRenderer = markdownit.renderer.rules.image
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        ?? ((tokens: any[], index: number, options: unknown, _env: unknown, self: any) =>
            self.renderToken(tokens, index, options));

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    markdownit.renderer.rules.image = (tokens: any[], index: number, options: unknown, environment: unknown, self: any) => {
        const token = tokens[index];
        const nextToken = tokens[index + 1];

        // Check if text following the image contains {attributes}
        if (nextToken && nextToken.type === 'text' && nextToken.content) {
            const match = ATTRIBUTES_PATTERN.exec(nextToken.content);
            if (match) {
                const attributes = parseAttributes(match[1]);

                if (attributes.width) {
                    token.attrSet('width', attributes.width);
                }
                if (attributes.height) {
                    token.attrSet('height', attributes.height);
                }
                if (attributes.class) {
                    token.attrSet('class', attributes.class);
                }

                // Remove the {attributes} from the text token
                nextToken.content = nextToken.content.slice(0, match.index);
            }
        }

        return defaultImageRenderer(tokens, index, options, environment, self);
    };
}

const ResizableImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('width'),
                renderHTML: (attributes: Record<string, string | null>) => {
                    if (!attributes.width) {
                        return {};
                    }

                    return { width: attributes.width };
                },
            },
            height: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('height'),
                renderHTML: (attributes: Record<string, string | null>) => {
                    if (!attributes.height) {
                        return {};
                    }

                    return { height: attributes.height };
                },
            },
            cssClass: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('class'),
                renderHTML: (attributes: Record<string, string | null>) => {
                    if (!attributes.cssClass) {
                        return {};
                    }

                    return { class: attributes.cssClass };
                },
            },
        };
    },

    addStorage() {
        return {
            markdown: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                serialize(state: any, node: any) {
                    const source = node.attrs.src ?? '';
                    const alt = (node.attrs.alt ?? '').replace(/"/g, '\\"');
                    const title = node.attrs.title ? ` "${node.attrs.title.replace(/"/g, '\\"')}"` : '';

                    state.write(`![${alt}](${source}${title})`);

                    const attributes: string[] = [];

                    // Class attributes as .class-name
                    if (node.attrs.cssClass) {
                        const classes = (node.attrs.cssClass as string).split(/\s+/);
                        for (const className of classes) {
                            attributes.push(`.${className}`);
                        }
                    }

                    if (node.attrs.width) {
                        attributes.push(`width=${node.attrs.width}`);
                    }
                    if (node.attrs.height) {
                        attributes.push(`height=${node.attrs.height}`);
                    }

                    if (attributes.length > 0) {
                        state.write(`{${attributes.join(' ')}}`);
                    }

                    state.closeBlock(node);
                },
                parse: {
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    setup(markdownit: any) {
                        imageAttributesPlugin(markdownit);
                    },
                },
            },
        };
    },
});

export default ResizableImage;
