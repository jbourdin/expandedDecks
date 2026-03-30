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
 * Uses max-width/max-height instead of width/height so images scale down
 * responsively on smaller viewports.
 *
 * @see docs/features.md F17.5 — Image drag-and-drop in the editor
 * @see docs/features.md F17.7 — Image float and alignment
 */

const ATTRIBUTES_PATTERN = /\{([^}]+)\}$/;

/**
 * Parse Pandoc-style attributes string into a key-value map.
 * Supports: `{max-width=400px max-height=300px #id .class .another-class}`
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
            const [key, ...rest] = part.split('=');
            attributes[key] = rest.join('=');
        }
    }

    return attributes;
}

/**
 * Build an inline style string from max-width and max-height values.
 */
function buildStyle(maxWidth: string | null, maxHeight: string | null): string {
    const parts: string[] = [];

    if (maxWidth) {
        parts.push(`max-width: ${maxWidth}px`);
    }
    if (maxHeight) {
        parts.push(`max-height: ${maxHeight}px`);
    }

    return parts.join('; ');
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

        // Check if text following the image contains {attributes}
        if (nextToken && nextToken.type === 'text' && nextToken.content) {
            const match = ATTRIBUTES_PATTERN.exec(nextToken.content);
            if (match) {
                const attributes = parseAttributes(match[1]);
                const styleParts: string[] = [];

                if (attributes['max-width']) {
                    styleParts.push(`max-width: ${attributes['max-width']}`);
                }
                if (attributes['max-height']) {
                    styleParts.push(`max-height: ${attributes['max-height']}`);
                }
                if (styleParts.length > 0) {
                    token.attrSet('style', styleParts.join('; '));
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
                renderHTML: () => ({}),
            },
            height: {
                default: null,
                renderHTML: () => ({}),
            },
            maxWidth: {
                default: null,
                parseHTML: (element: HTMLElement) => {
                    const style = element.getAttribute('style') ?? '';
                    const match = /max-width:\s*(\d+)px/.exec(style);

                    return match ? match[1] : null;
                },
                renderHTML: () => ({}),
            },
            maxHeight: {
                default: null,
                parseHTML: (element: HTMLElement) => {
                    const style = element.getAttribute('style') ?? '';
                    const match = /max-height:\s*(\d+)px/.exec(style);

                    return match ? match[1] : null;
                },
                renderHTML: () => ({}),
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
            style: {
                default: null,
                renderHTML: (attributes: Record<string, string | null>) => {
                    const style = buildStyle(attributes.maxWidth, attributes.maxHeight);

                    return style ? { style } : {};
                },
            },
        };
    },

    /**
     * Override the resize commit to store as maxWidth/maxHeight instead of width/height.
     */
    addNodeView() {
        const parentNodeView = this.parent?.();

        if (!parentNodeView) {
            return null;
        }

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        return (props: any) => {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const nodeView = (parentNodeView as any)(props);

            if (!nodeView || !nodeView.resizableNodeView) {
                return nodeView;
            }

            nodeView.resizableNodeView.options.onCommit = (committedWidth: number, committedHeight: number) => {
                const position = props.getPos();
                if (position === undefined) {
                    return;
                }

                props.editor
                    .chain()
                    .setNodeSelection(position)
                    .updateAttributes('image', {
                        width: null,
                        height: null,
                        maxWidth: String(Math.round(committedWidth)),
                        maxHeight: String(Math.round(committedHeight)),
                    })
                    .run();
            };

            // If maxWidth/maxHeight exist, apply as initial inline style
            const { maxWidth, maxHeight } = props.node.attrs;
            if (maxWidth || maxHeight) {
                const element = nodeView.dom?.querySelector?.('img') ?? nodeView.dom;
                if (element instanceof HTMLElement) {
                    if (maxWidth) {
                        element.style.maxWidth = `${maxWidth}px`;
                    }
                    if (maxHeight) {
                        element.style.maxHeight = `${maxHeight}px`;
                    }
                }
            }

            return nodeView;
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

                    if (node.attrs.maxWidth) {
                        attributes.push(`max-width=${node.attrs.maxWidth}px`);
                    }
                    if (node.attrs.maxHeight) {
                        attributes.push(`max-height=${node.attrs.maxHeight}px`);
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
