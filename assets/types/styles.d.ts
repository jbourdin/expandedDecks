/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Ambient declarations for style imports. Webpack handles these at build time
 * via its loaders; TypeScript 6 (TS2882) requires a module declaration for
 * side-effect imports such as `import './styles/app.scss'`.
 */

declare module '*.scss';
declare module '*.css';
