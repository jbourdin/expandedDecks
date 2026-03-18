/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F6.2 — TCGdex card data enrichment
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 * @see docs/features.md F6.8 — Minified deck list export
 */

import { initCardHover } from './shared/card-hover';

initCardHover();

/** Bootstrap md breakpoint in pixels. */
const MD_BREAKPOINT = 768;

function isMobile(): boolean {
    return window.innerWidth < MD_BREAKPOINT;
}

/**
 * Global variant state: 'original' or 'minified'.
 * Controls table content, mosaic image, and which list gets copied.
 */
let currentVariant: 'original' | 'minified' = 'minified';

/**
 * Global variant toggle — controls table, mosaic, and copy list.
 */
function initVariantToggle(): void {
    const originalButton = document.getElementById('deckVariantOriginal');
    const minifiedButton = document.getElementById('deckVariantMinified');

    if (!originalButton || !minifiedButton) {
        return;
    }

    const setVariant = (variant: 'original' | 'minified'): void => {
        currentVariant = variant;

        const isMinified = variant === 'minified';

        originalButton.classList.toggle('active', !isMinified);
        originalButton.setAttribute('aria-pressed', String(!isMinified));
        minifiedButton.classList.toggle('active', isMinified);
        minifiedButton.setAttribute('aria-pressed', String(isMinified));

        // Toggle table
        const originalTable = document.getElementById('deckTableOriginal');
        const minifiedTable = document.getElementById('deckTableMinified');

        if (originalTable) {
            originalTable.style.display = isMinified ? 'none' : '';
        }

        if (minifiedTable) {
            minifiedTable.style.display = isMinified ? '' : 'none';
        }

        // Toggle mosaic image (inline + fullscreen)
        const mosaicImg = document.getElementById('deckMosaicImg') as HTMLImageElement | null;
        const fullscreenImg = document.getElementById('mosaicFullscreenImg') as HTMLImageElement | null;

        if (mosaicImg) {
            const src = isMinified
                ? (mosaicImg.dataset.minifiedSrc ?? mosaicImg.dataset.originalSrc ?? '')
                : (mosaicImg.dataset.originalSrc ?? '');
            mosaicImg.src = src;
        }

        if (fullscreenImg && mosaicImg) {
            fullscreenImg.src = mosaicImg.src;
        }
    };

    originalButton.addEventListener('click', () => setVariant('original'));
    minifiedButton.addEventListener('click', () => setVariant('minified'));
}

initVariantToggle();

/**
 * Table/Mosaic view toggle.
 *
 * Desktop: inline toggle between table and mosaic views.
 * Mobile: table is always shown; mosaic button opens a fullscreen modal.
 *
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
function initViewToggle(): void {
    const tableButton = document.getElementById('deckViewTable');
    const mosaicButton = document.getElementById('deckViewMosaic');
    const tableView = document.getElementById('deckTableView');
    const mosaicView = document.getElementById('deckMosaicView');
    const mosaicImg = document.getElementById('deckMosaicImg') as HTMLImageElement | null;
    const fullscreenImg = document.getElementById('mosaicFullscreenImg') as HTMLImageElement | null;
    const fullscreenModal = document.getElementById('mosaicFullscreenModal');

    if (!tableButton || !mosaicButton || !tableView) {
        return;
    }

    const setActiveButton = (active: 'table' | 'mosaic'): void => {
        tableButton.classList.toggle('active', active === 'table');
        tableButton.setAttribute('aria-pressed', String(active === 'table'));
        mosaicButton.classList.toggle('active', active === 'mosaic');
        mosaicButton.setAttribute('aria-pressed', String(active === 'mosaic'));
    };

    const showTable = (): void => {
        tableView.style.display = '';

        if (mosaicView) {
            mosaicView.style.display = 'none';
        }

        setActiveButton('table');
    };

    const showMosaicDesktop = (): void => {
        if (!mosaicView) {
            return;
        }

        tableView.style.display = 'none';
        mosaicView.style.display = '';
        setActiveButton('mosaic');
    };

    const showMosaicMobile = (): void => {
        if (!fullscreenModal || !fullscreenImg || !mosaicImg) {
            return;
        }

        // Copy current mosaic src to fullscreen modal
        fullscreenImg.src = mosaicImg.src;

        // Open Bootstrap modal
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const bootstrap = (window as any).bootstrap;

        if (bootstrap?.Modal) {
            const modal = new bootstrap.Modal(fullscreenModal);
            modal.show();
        }
    };

    tableButton.addEventListener('click', showTable);

    mosaicButton.addEventListener('click', () => {
        if (isMobile()) {
            showMosaicMobile();
        } else {
            showMosaicDesktop();
        }
    });

    // Set initial state based on viewport
    if (isMobile()) {
        // Mobile: always show table, mosaic via modal
        showTable();
    } else if (mosaicView) {
        // Desktop: default to mosaic
        showMosaicDesktop();
    }
}

initViewToggle();

/**
 * Single copy button that copies either original or minified list
 * based on the current variant toggle state.
 *
 * @see docs/features.md F6.7 — Export deck list as PTCGL text
 */
function initCopyList(): void {
    const copyButton = document.getElementById('deckCopyList');
    const feedback = document.getElementById('deckCopyFeedback');

    if (!copyButton || !feedback) {
        return;
    }

    copyButton.addEventListener('click', () => {
        const elementId = currentVariant === 'minified' ? 'deckMinifiedList' : 'deckRawList';
        const element = document.getElementById(elementId) ?? document.getElementById('deckRawList');

        if (!element) {
            return;
        }

        const text = element.textContent?.trim() ?? '';

        navigator.clipboard.writeText(text).then(() => {
            feedback.style.display = '';
            setTimeout(() => {
                feedback.style.display = 'none';
            }, 2000);
        });
    });
}

initCopyList();
