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

        // Toggle buttons
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

        // Toggle mosaic image
        const mosaicImg = document.getElementById('deckMosaicImg') as HTMLImageElement | null;

        if (mosaicImg) {
            const src = isMinified
                ? (mosaicImg.dataset.minifiedSrc ?? mosaicImg.dataset.originalSrc ?? '')
                : (mosaicImg.dataset.originalSrc ?? '');
            mosaicImg.src = src;
        }
    };

    originalButton.addEventListener('click', () => setVariant('original'));
    minifiedButton.addEventListener('click', () => setVariant('minified'));
}

initVariantToggle();

/**
 * Table/Mosaic view toggle.
 *
 * @see docs/features.md F6.6 — Visual deck list (card mosaic)
 */
function initViewToggle(): void {
    const tableButton = document.getElementById('deckViewTable');
    const mosaicButton = document.getElementById('deckViewMosaic');
    const tableView = document.getElementById('deckTableView');
    const mosaicView = document.getElementById('deckMosaicView');

    if (!tableButton || !mosaicButton || !tableView || !mosaicView) {
        return;
    }

    const showTable = (): void => {
        tableView.style.display = '';
        mosaicView.style.display = 'none';
        tableButton.classList.add('active');
        tableButton.setAttribute('aria-pressed', 'true');
        mosaicButton.classList.remove('active');
        mosaicButton.setAttribute('aria-pressed', 'false');
    };

    const showMosaic = (): void => {
        tableView.style.display = 'none';
        mosaicView.style.display = '';
        mosaicButton.classList.add('active');
        mosaicButton.setAttribute('aria-pressed', 'true');
        tableButton.classList.remove('active');
        tableButton.setAttribute('aria-pressed', 'false');
    };

    tableButton.addEventListener('click', showTable);
    mosaicButton.addEventListener('click', showMosaic);
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
