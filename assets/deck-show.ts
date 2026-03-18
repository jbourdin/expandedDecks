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
 */
function initCopyList(): void {
    const copyButton = document.getElementById('deckCopyList');
    const rawList = document.getElementById('deckRawList');
    const feedback = document.getElementById('deckCopyFeedback');

    if (!copyButton || !rawList || !feedback) {
        return;
    }

    copyButton.addEventListener('click', () => {
        const text = rawList.textContent?.trim() ?? '';

        navigator.clipboard.writeText(text).then(() => {
            feedback.style.display = '';
            setTimeout(() => {
                feedback.style.display = 'none';
            }, 2000);
        });
    });
}

initCopyList();
