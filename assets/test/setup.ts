/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import '@testing-library/jest-dom';

// Mantine's FloatingIndicator (used by SegmentedControl) requires ResizeObserver.
globalThis.ResizeObserver = class ResizeObserver {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
};

// Mantine 9's autosize Textarea listens to the FontFaceSet API (document.fonts),
// which jsdom does not implement.
Object.defineProperty(document, 'fonts', {
    writable: true,
    value: {
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    },
});

// Mantine requires window.matchMedia which jsdom does not provide.
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
});
