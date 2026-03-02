/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')

    .addEntry('app', './assets/app.tsx')
    .addEntry('deck_show', './assets/deck-show.ts')
    .addEntry('staff_autocomplete', './assets/staff-autocomplete.ts')
    .addEntry('walk_up_autocomplete', './assets/walk-up-autocomplete.ts')

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
