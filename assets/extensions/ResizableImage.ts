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
 * and Pandoc-style `{.class max-width=Xpx max-height=Ypx}` Markdown serialization.
 *
 * The ResizableNodeView stores dimensions as `width`/`height` attributes natively.
 * This extension renders them as `max-width`/`max-height` CSS (for responsive behavior)
 * and serializes to Markdown as `{max-width=Xpx max-height=Ypx}`.
 *
 * @see docs/features.md F17.5 — Image drag-and-drop in the editor
 * @see docs/features.md F17.7 — Image float and alignment
 */

const ATTRIBUTES_PATTERN = /\{([^}]+)\}$/;

/**
 * Parse Pandoc-style attributes string into a key-value map.
 * Supports: `{style="max-width: 400px" #id .class}`
 * Handles quoted values: `key="value with spaces"`.
 */
function parseAttributes(attributeString: string): Record<string, string> {
    const attributes: Record<string, string> = {};
    const tokenPattern = /([#.][a-zA-Z0-9_-]+)|([a-zA-Z-]+)="([^"]*)"|([a-zA-Z-]+)=(\S+)/g;
    let token: RegExpExecArray | null;

    while ((token = tokenPattern.exec(attributeString)) !== null) {
        if (token[1]?.startsWith('#')) {
            attributes.id = token[1].slice(1);
        } else if (token[1]?.startsWith('.')) {
            const existing = attributes.class ?? '';
            attributes.class = (existing ? `${existing} ` : '') + token[1].slice(1);
        } else if (token[2] && token[3] !== undefined) {
            // key="quoted value"
            attributes[token[2]] = token[3];
        } else if (token[4] && token[5]) {
            // key=unquoted-value
            attributes[token[4]] = token[5];
        }
    }

    return attributes;
}

/**
 * markdown-it plugin that parses Pandoc-style `{key=value .class}` attributes
 * on image tokens and attaches them to the rendered HTML.
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

        if (nextToken && nextToken.type === 'text' && nextToken.content) {
            const match = ATTRIBUTES_PATTERN.exec(nextToken.content);
            if (match) {
                const attributes = parseAttributes(match[1]);

                // style="..." is passed through directly
                if (attributes.style) {
                    token.attrSet('style', attributes.style);
                }
                if (attributes.class) {
                    token.attrSet('class', attributes.class);
                }

                nextToken.content = nextToken.content.slice(0, match.index);
            }
        }

        return defaultImageRenderer(tokens, index, options, environment, self);
    };
}

/**
 * Parse max-width/max-height from an inline style string.
 */
function parseStyleDimension(element: HTMLElement, property: string): string | null {
    const style = element.getAttribute('style') ?? '';
    const pattern = new RegExp(`${property}:\\s*(\\d+)px`);
    const match = pattern.exec(style);

    return match ? match[1] : null;
}

const ResizableImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            // The base Image extension defines width/height for ResizableNodeView.
            // We override renderHTML to output max-width/max-height CSS instead.
            width: {
                default: null,
                parseHTML: (element: HTMLElement) =>
                    element.getAttribute('width') ?? parseStyleDimension(element, 'max-width'),
                renderHTML: (attributes: Record<string, string | null>) => {
                    if (!attributes.width) {
                        return {};
                    }

                    return { style: `max-width: ${attributes.width}px` };
                },
            },
            height: {
                default: null,
                parseHTML: (element: HTMLElement) =>
                    element.getAttribute('height') ?? parseStyleDimension(element, 'max-height'),
                renderHTML: (attributes: Record<string, string | null>) => {
                    if (!attributes.height) {
                        return {};
                    }

                    return { style: `max-height: ${attributes.height}px` };
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

                    if (node.attrs.cssClass) {
                        const classes = (node.attrs.cssClass as string).split(/\s+/);
                        for (const className of classes) {
                            attributes.push(`.${className}`);
                        }
                    }

                    const styleParts: string[] = [];
                    if (node.attrs.width) {
                        styleParts.push(`max-width: ${node.attrs.width}px`);
                    }
                    if (node.attrs.height) {
                        styleParts.push(`max-height: ${node.attrs.height}px`);
                    }
                    if (styleParts.length > 0) {
                        attributes.push(`style="${styleParts.join('; ')}"`);
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
