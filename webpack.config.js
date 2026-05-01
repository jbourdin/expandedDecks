/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

const Encore = require('@symfony/webpack-encore');
const path = require('path');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')

    .addEntry('app', './assets/app.tsx')
    .addStyleEntry('theme_expandedtalks', './assets/styles/themes/expandedtalks/theme.scss')
    .addEntry('deck_form', './assets/deck-form.tsx')
    .addEntry('deck_card_list', './assets/deck-card-list.tsx')
    .addEntry('admin_archetype_list', './assets/admin-archetype-list.ts')
    .addEntry('admin_archetype_edit', './assets/admin-archetype-edit.ts')
    .addEntry('archetype_show', './assets/archetype-show.ts')
    .addEntry('archetype_variants', './assets/archetype-variants.tsx')
    .addEntry('page_show', './assets/page-show.ts')
    .addEntry('staff_autocomplete', './assets/staff-autocomplete.tsx')
    .addEntry('walk_up_autocomplete', './assets/walk-up-autocomplete.tsx')
    .addEntry('catalog_filters', './assets/catalog-filters.tsx')
    .addEntry('event_sync', './assets/event-sync.ts')
    .addEntry('notification_bell', './assets/notification-bell.tsx')
    .addEntry('navbar_search', './assets/navbar-search.tsx')
    .addEntry('deck_version_compare', './assets/deck-version-compare.tsx')
    .addEntry('variant_compare', './assets/variant-compare.tsx')
    .addEntry('variant_compare_modal', './assets/variant-compare-modal.tsx')
    .addEntry('archetype_form', './assets/archetype-form.tsx')
    .addEntry('page_form', './assets/page-form.tsx')
    .addEntry('admin_page_list', './assets/admin-page-list.ts')
    .addEntry('admin_menu_category_list', './assets/admin-menu-category-list.ts')
    .addEntry('homepage_editor', './assets/homepage-editor.tsx')
    .addEntry('toggle_private_decks', './assets/toggle-private-decks.ts')
    .addEntry('friendly_captcha', './assets/friendly-captcha.ts')
    .addEntry('deck_found', './assets/deck-found.tsx')
    .addEntry('event_form', './assets/event-form.tsx')
    .addEntry('event_calendar_copy', './assets/event-calendar-copy.ts')

    .splitEntryChunks()
    .enableSingleRuntimeChunk()

    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .enableTypeScriptLoader()
    .enableReactPreset()
    .enableSassLoader((options) => {
        options.sassOptions = {
            quietDeps: true,
            silenceDeprecations: ['import'],
        };
    })

;

module.exports = Encore.getWebpackConfig();
