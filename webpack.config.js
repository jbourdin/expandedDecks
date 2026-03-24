/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

const Encore = require('@symfony/webpack-encore');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const path = require('path');
const fs = require('fs');

/*
 * Generate a static manifest of available Pokemon sprite slugs at build time.
 * The React PokemonSpriteSelect component imports this list for autocomplete.
 *
 * @see docs/features.md F2.22 — Custom Pokemon sprites on decks
 */
const spritesDir = path.resolve(__dirname, 'assets/vendor/sprites/pokemon');
const manifestPath = path.resolve(__dirname, 'assets/generated/pokemon-sprites.json');
if (fs.existsSync(spritesDir)) {
    const slugs = fs.readdirSync(spritesDir)
        .filter((file) => file.endsWith('.png'))
        .map((file) => file.replace(/\.png$/, ''))
        .sort();
    fs.writeFileSync(manifestPath, JSON.stringify(slugs));
} else {
    fs.writeFileSync(manifestPath, '[]');
}

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')

    .addEntry('app', './assets/app.tsx')
    .addEntry('deck_form', './assets/deck-form.tsx')
    .addEntry('deck_card_list', './assets/deck-card-list.tsx')
    .addEntry('archetype_show', './assets/archetype-show.ts')
    .addEntry('staff_autocomplete', './assets/staff-autocomplete.tsx')
    .addEntry('walk_up_autocomplete', './assets/walk-up-autocomplete.tsx')
    .addEntry('catalog_filters', './assets/catalog-filters.tsx')
    .addEntry('event_sync', './assets/event-sync.ts')
    .addEntry('notification_bell', './assets/notification-bell.tsx')
    .addEntry('deck_version_compare', './assets/deck-version-compare.tsx')
    .addEntry('archetype_form', './assets/archetype-form.tsx')

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

    .addPlugin(new CopyWebpackPlugin({
        patterns: [{
            from: path.resolve(__dirname, 'assets/vendor/sprites/pokemon'),
            to: 'sprites/pokemon',
            noErrorOnMissing: true,
        }],
    }))
;

module.exports = Encore.getWebpackConfig();
