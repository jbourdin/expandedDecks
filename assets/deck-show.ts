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
 */

import { initCardHover } from './shared/card-hover';

initCardHover();

/**
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

    const storageKey = 'deckViewMode';
    const savedMode = localStorage.getItem(storageKey);

    const showTable = (): void => {
        tableView.style.display = '';
        mosaicView.style.display = 'none';
        tableButton.classList.add('active');
        tableButton.setAttribute('aria-pressed', 'true');
        mosaicButton.classList.remove('active');
        mosaicButton.setAttribute('aria-pressed', 'false');
        localStorage.setItem(storageKey, 'table');
    };

    const showMosaic = (): void => {
        tableView.style.display = 'none';
        mosaicView.style.display = '';
        mosaicButton.classList.add('active');
        mosaicButton.setAttribute('aria-pressed', 'true');
        tableButton.classList.remove('active');
        tableButton.setAttribute('aria-pressed', 'false');
        localStorage.setItem(storageKey, 'mosaic');
    };

    tableButton.addEventListener('click', showTable);
    mosaicButton.addEventListener('click', showMosaic);

    if (savedMode === 'mosaic') {
        showMosaic();
    }
}

initViewToggle();

/**
 * @see docs/features.md F6.7 — Export deck list as PTCGL text
 * @see docs/features.md F6.8 — Minified deck list export
 */
function initCopyList(): void {
    const feedback = document.getElementById('deckCopyFeedback');

    const copyToClipboard = (elementId: string): void => {
        const element = document.getElementById(elementId);

        if (!element || !feedback) {
            return;
        }

        const text = element.textContent?.trim() ?? '';

        navigator.clipboard.writeText(text).then(() => {
            feedback.style.display = '';
            setTimeout(() => {
                feedback.style.display = 'none';
            }, 2000);
        });
    };

    document.getElementById('deckCopyList')?.addEventListener('click', () => {
        copyToClipboard('deckRawList');
    });

    document.getElementById('deckCopyMinifiedList')?.addEventListener('click', () => {
        copyToClipboard('deckMinifiedList');
    });
}

initCopyList();

/**
 * @see docs/features.md F6.8 — Minified deck list export
 */
function initTableVariantToggle(): void {
    const originalButton = document.getElementById('tableOriginal');
    const minifiedButton = document.getElementById('tableMinified');
    const originalTable = document.getElementById('deckTableOriginal');
    const minifiedTable = document.getElementById('deckTableMinified');

    if (!originalButton || !minifiedButton || !originalTable || !minifiedTable) {
        return;
    }

    originalButton.addEventListener('click', () => {
        originalTable.style.display = '';
        minifiedTable.style.display = 'none';
        originalButton.classList.add('active');
        originalButton.setAttribute('aria-pressed', 'true');
        minifiedButton.classList.remove('active');
        minifiedButton.setAttribute('aria-pressed', 'false');
    });

    minifiedButton.addEventListener('click', () => {
        originalTable.style.display = 'none';
        minifiedTable.style.display = '';
        minifiedButton.classList.add('active');
        minifiedButton.setAttribute('aria-pressed', 'true');
        originalButton.classList.remove('active');
        originalButton.setAttribute('aria-pressed', 'false');
    });
}

initTableVariantToggle();

/**
 * @see docs/features.md F6.6b — Minified mosaic
 */
function initMosaicVariantToggle(): void {
    const originalButton = document.getElementById('mosaicOriginal');
    const minifiedButton = document.getElementById('mosaicMinified');
    const mosaicImg = document.getElementById('deckMosaicImg') as HTMLImageElement | null;

    if (!originalButton || !minifiedButton || !mosaicImg) {
        return;
    }

    const originalSrc = mosaicImg.dataset.originalSrc ?? '';
    const minifiedSrc = mosaicImg.dataset.minifiedSrc ?? '';

    originalButton.addEventListener('click', () => {
        mosaicImg.src = originalSrc;
        originalButton.classList.add('active');
        originalButton.setAttribute('aria-pressed', 'true');
        minifiedButton.classList.remove('active');
        minifiedButton.setAttribute('aria-pressed', 'false');
    });

    minifiedButton.addEventListener('click', () => {
        mosaicImg.src = minifiedSrc;
        minifiedButton.classList.add('active');
        minifiedButton.setAttribute('aria-pressed', 'true');
        originalButton.classList.remove('active');
        originalButton.setAttribute('aria-pressed', 'false');
    });
}

initMosaicVariantToggle();
