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

## Step 2: F2.12 — Sprite pictograms ✅

- Download PokéSprite Gen 9 fork box sprites (1478 PNGs, varying sizes 23×22 to 73×62) via `make sprites`
  - Source: [martimlobao/pokesprite](https://github.com/martimlobao/pokesprite) `pokemon/regular/`
  - Downloaded at build time via tarball, cached in gitignored `assets/vendor/sprites/pokemon/`
- `copy-webpack-plugin` copies sprites to `public/build/sprites/pokemon/` at build time
- Twig extension + lazy runtime:
  - `src/Twig/Extension/ArchetypeSpriteExtension.php` — registers `archetype_sprites` function
  - `src/Twig/Runtime/ArchetypeSpriteRuntime.php` — renders `<img>` tags with slug-to-name conversion for `alt`/`title`
- CSS: fixed 40px height, `image-rendering: pixelated`, `.archetype-sprites` wrapper with table `min-width`
- Sprites displayed between short tag badge and deck name across all deck views:
  - Catalog, deck detail, dashboard, event pages, borrow views, tournament results
- Unit tests (extension + runtime) and functional tests (admin list, catalog, deck show)
- Roadmap: F2.12 Done

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
| Sprite assets | Downloaded at build time (gitignored) | No git bloat (~3MB), no CDN dependency, cache-busted via Encore |
| Sprite build | `make sprites` + `copy-webpack-plugin` | Tarball download on demand, Webpack copies to public build |
| Sprite sizing | Fixed 40px CSS height | Consistent visual weight despite varying native dimensions (23–73px) |
| Sprite rendering | `image-rendering: pixelated` | Preserves pixel art crispness when scaled |
| Sprite placement | Between short tag and deck name | Visually identifies archetype at a glance without cluttering metadata |
| Markdown rendering | Reuse `MarkdownRenderer` | Already exists with league/commonmark |
| Sprite Twig helper | Extension + Runtime | Used in 5+ templates, cleaner than macro imports |
| Backlinking | Twig macro | Single source of truth for archetype name rendering |
| New archetypes | Default unpublished | Admin must publish to enable detail page |
| Admin UI | `ROLE_ADMIN` gated | Future `ROLE_ARCHETYPE_EDITOR` role deferred |
