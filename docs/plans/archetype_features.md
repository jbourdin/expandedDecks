# Archetype Features Implementation Plan

> **Audience:** Developer, AI Agent · **Scope:** F2.6, F2.10, F2.11, F2.12 · **Back:** [roadmap](../roadmap.md)

## Overview

Complete the archetype feature family: entity enrichment, sprite pictograms, detail page, and backlinking across the UI.

## Step 1: F2.6 completion — Entity + Migration + Admin UI

- Add 4 fields to `Archetype` entity:
  - `pokemonSlugs` (JSON, default `[]`) — array of Pokemon slugs for sprite display
  - `description` (TEXT, nullable) — Markdown content for detail page
  - `metaDescription` (VARCHAR 255, nullable) — SEO meta tag
  - `isPublished` (bool, default `false`) — controls detail page visibility
- Doctrine migration (additive only, safe for existing data)
- Admin archetype management at `/admin/archetypes`:
  - List page (table with name, published status, edit link)
  - Edit page (description textarea, metaDescription, pokemonSlugs React island, isPublished checkbox)
- Update `ArchetypeController::create` response to include `isPublished`
- Update `ArchetypeSearchController` response to include `isPublished`
- Update fixtures with sample data (descriptions, pokemonSlugs, published flags)
- Update `docs/models/deck.md` with new fields
- Translations (en + fr) for admin UI labels
- Update roadmap: F2.6 Partial → Done

## Step 2: F2.12 — Sprite pictograms

- Download PokéSprite Gen 9 fork box sprites (68x56 PNG) into `assets/sprites/pokemon/`
  - Source: [martimlobao/pokesprite](https://github.com/martimlobao/pokesprite) `pokemon-gen8/regular/`
  - Commit PNGs to repo (~3MB) — no CDN dependency
- Add `copy-webpack-plugin` (npm devDependency) to copy sprites to `public/build/sprites/` at build time
- Update `webpack.config.js` with CopyWebpackPlugin
- Create Twig extension + runtime (follows `GravatarExtension` pattern):
  - `src/Twig/Extension/ArchetypeSpriteExtension.php` — registers `archetype_sprites` function
  - `src/Twig/Runtime/ArchetypeSpriteRuntime.php` — renders `<img>` tags from `pokemonSlugs`
  - Half-size display: 34x28 px inline images
- Add `assets/styles/_archetype-sprites.scss` for `.archetype-sprite` styling
- Unit test for sprite rendering
- Update roadmap: F2.12 Not started → Done

## Step 3: F2.10 — Archetype detail page

- New controller: `ArchetypeDetailController` at `/archetype/{slug}`
  - Public route, but 404 if `isPublished === false` (unless admin)
  - Renders description via existing `MarkdownRenderer` service (league/commonmark)
  - Queries available deck count and recent tournament results
  - SEO meta from `metaDescription`
- New template: `templates/archetype/show.html.twig`
  - Hero: archetype name + sprites
  - Description card (Markdown → HTML)
  - "Browse decks" CTA linking to filtered catalog
  - Available deck count
  - Recent tournament results table
- Repository methods:
  - `ArchetypeRepository::findPublishedBySlug()`
  - `DeckRepository::countAvailableByArchetype()`
  - `EventDeckEntryRepository::findRecentByArchetype()`
- Translations (en + fr) for page labels
- Functional tests
- Update roadmap: F2.10 Not started → Done

## Step 4: F2.11 — Backlinking

- Create Twig macro: `templates/archetype/_name.html.twig`
  - If `isPublished`: hyperlink to detail page + sprites
  - If not published: plain text + sprites
- Update templates to use macro:
  - `templates/deck/show.html.twig` (deck detail)
  - `templates/deck/list.html.twig` (deck catalog)
  - `templates/event/results.html.twig` (tournament results)
  - `templates/event/available_decks.html.twig` (event available decks)
- Functional tests verifying link presence for published / absence for unpublished
- Update roadmap: F2.11 Not started → Done

## Key decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Sprite assets | Committed to repo | No CDN dependency, ~3MB, cache-busted via Encore |
| Sprite build | `copy-webpack-plugin` | Aligns with Encore pipeline, `make assets` rule |
| Markdown rendering | Reuse `MarkdownRenderer` | Already exists with league/commonmark |
| Sprite Twig helper | Extension + Runtime | Used in 5+ templates, cleaner than macro imports |
| Backlinking | Twig macro | Single source of truth for archetype name rendering |
| New archetypes | Default unpublished | Admin must publish to enable detail page |
| Admin UI | `ROLE_ADMIN` gated | Future `ROLE_ARCHETYPE_EDITOR` role deferred |
