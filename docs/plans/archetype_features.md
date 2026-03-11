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

## Step 3: F2.10 — Archetype detail page ✅

- Controller: `ArchetypeDetailController` at `/archetypes/{slug}`
  - Public route, 404 if `isPublished === false` (unless admin sees draft notice)
  - Description rendered via `ArchetypeDescriptionRenderer` service:
    - Markdown → HTML via `MarkdownRenderer` (league/commonmark)
    - Custom tag expansion: `[[archetype:slug]]`, `[[deck:SHORTTAG]]`, `[[card:SET-NUMBER]]`
    - Card data resolved from local DB (`DeckCardRepository`) then TCGdex API fallback
    - Individual card lookups cached 24h, full rendered output cached 1h (keyed by content hash)
  - SEO meta from `metaDescription`
- Template: `templates/archetype/show.html.twig`
  - Header: archetype name + sprites
  - Description card (rendered HTML with card hovers)
  - "Browse decks" CTA with available deck count
  - Card image modal for mobile (shared with deck show)
- Frontend: extracted shared `assets/shared/card-hover.ts` module, new `archetype_show` Webpack entry
- CSS: `.cms-content .card-hover` styled with `bi-file-fill` icon + dotted underline for card references
- Repository methods: `ArchetypeRepository::findPublishedBySlug()`, `DeckRepository::countPublicByArchetype()`, `DeckCardRepository::findOneBySetCodeAndCardNumber()`
- Translations (en + fr) for page labels
- Fixtures: Regidrago description with all 3 tag types, Kyurem + Salamence ex archetypes
- Tests: 12 functional + 11 unit tests
- Roadmap: F2.10 Done

## Step 4: F2.11 — Backlinking ✅

- Archetype names link to detail page inline in templates (no macro — simple `{% if archetype.published %}` conditional)
- Updated templates:
  - `templates/deck/show.html.twig` (deck detail archetype label)
  - `templates/deck/list.html.twig` (deck catalog archetype label)
  - `templates/event/results.html.twig` (tournament results archetype column)
  - `templates/event/available_decks.html.twig` (available decks archetype label)
  - `templates/admin/archetype/list.html.twig` (admin list — always links)
- Unpublished archetypes render as plain text (except admin list)
- Roadmap: F2.11 Done

## Step 5: F2.15 — Archetype playstyle tags (planned)

- Add `tags` field to `Archetype` entity (JSON array, e.g. `["control", "combo", "lock"]`)
- Predefined tag vocabulary: aggressive, control, combo, lock, spread, toolbox
- Display as colored badges on archetype detail page and optionally in catalog cards
- Enable filtering deck catalog (F2.4) by playstyle tag
- Admin UI to manage tags per archetype

## Key decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Sprite assets | Downloaded at build time (gitignored) | No git bloat (~3MB), no CDN dependency, cache-busted via Encore |
| Sprite build | `make sprites` + `copy-webpack-plugin` | Tarball download on demand, Webpack copies to public build |
| Sprite sizing | Fixed 40px CSS height | Consistent visual weight despite varying native dimensions (23–73px) |
| Sprite rendering | `image-rendering: pixelated` | Preserves pixel art crispness when scaled |
| Sprite placement | Between short tag and deck name | Visually identifies archetype at a glance without cluttering metadata |
| Markdown rendering | `ArchetypeDescriptionRenderer` wrapping `MarkdownRenderer` | Separates custom tag logic from generic Markdown |
| Custom tags | Post-process HTML with regex after Markdown | `[[...]]` syntax survives Markdown as plain text, safe to expand after |
| Card resolution | Local DB → TCGdex API fallback, per-card 24h cache | Avoids runtime API dependency; most cards enriched via deck imports |
| Rendering cache | Full output cached 1h by content hash | Eliminates repeated Markdown + DB + API work per page view |
| Card hover JS | Shared `card-hover.ts` module | Reused by deck show and archetype show, single source of truth |
| Card reference CSS | `bi-file-fill` icon + dotted underline in `.cms-content` | Visual affordance for hoverable card names, scoped to rich text only |
| Sprite Twig helper | Extension + Runtime | Used in 5+ templates, cleaner than macro imports |
| Backlinking | Inline conditional in templates | Simple `{% if archetype.published %}` — no macro needed for 5 occurrences |
| New archetypes | Default unpublished | Admin must publish to enable detail page |
| Admin UI | `ROLE_ADMIN` gated | Future `ROLE_ARCHETYPE_EDITOR` role deferred |
