/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @see docs/features.md F10.5 — Homepage block editor (admin UI)
 */

import { createRoot } from 'react-dom/client';
import AppMantineProvider from './components/AppMantineProvider';
import HomepageEditor from './components/HomepageEditor';

import '@mantine/core/styles.css';

interface BlockTypeInfo {
    value: string;
    label: string;
    icon: string;
}

interface CategoryInfo {
    id: number;
    name: string;
}

interface Labels {
    [key: string]: string;
}

const root = document.getElementById('homepage-editor-root');
if (root) {
    const saveUrl = root.dataset.saveUrl ?? '';
    const previewUrl = root.dataset.previewUrl ?? '';
    const uploadUrl = root.dataset.uploadUrl ?? '';
    const channelCode = root.dataset.channelCode ?? '';
    const initialOgImage = root.dataset.ogImage ?? '';

    let supportedLocales: string[] = ['en', 'fr'];
    let initialBlocks: Record<string, unknown>[] = [];
    let initialTranslations: Record<string, Record<string, Record<string, unknown>>> = {};
    let blockTypes: BlockTypeInfo[] = [];
    let categories: CategoryInfo[] = [];
    let labels: Labels = {};

    try {
        supportedLocales = JSON.parse(root.dataset.supportedLocales ?? '["en","fr"]');
    } catch { /* use default */ }

    try {
        initialBlocks = JSON.parse(root.dataset.blocks ?? '[]');
    } catch { /* use default */ }

    try {
        initialTranslations = JSON.parse(root.dataset.translations ?? '{}');
    } catch { /* use default */ }

    try {
        blockTypes = JSON.parse(root.dataset.blockTypes ?? '[]');
    } catch { /* use default */ }

    try {
        categories = JSON.parse(root.dataset.categories ?? '[]');
    } catch { /* use default */ }

    try {
        labels = JSON.parse(root.dataset.labels ?? '{}');
    } catch { /* use default */ }

    createRoot(root).render(
        <AppMantineProvider>
            <HomepageEditor
                saveUrl={saveUrl}
                previewUrl={previewUrl}
                uploadUrl={uploadUrl}
                channelCode={channelCode}
                supportedLocales={supportedLocales}
                initialBlocks={initialBlocks}
                initialTranslations={initialTranslations}
                initialOgImage={initialOgImage}
                blockTypes={blockTypes}
                categories={categories}
                labels={labels}
            />
        </AppMantineProvider>,
    );
}
