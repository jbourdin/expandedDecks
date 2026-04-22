# Changelog

> **Audience:** Developer, AI Agent · **Scope:** Reference

← Back to [Main Documentation](docs.md) | [README](../README.md)

All notable changes to this project are documented in this file.
Format inspired by [Keep a Changelog](https://keepachangelog.com/).

Features reference the [Feature List](features.md) by ID.
Items marked *(partial)* have scaffolding or basic functionality but are not yet complete end-to-end.

---

## [Unreleased]

---

## [1.8.0] — 2026-04-23

Incremental TCGdex database sync — API-based cascade replacing the monolithic git-clone import.

### Features

- **Incremental TCGdex sync (F6.13)** — new async message cascade (Series → Serie → Set → Card → Complete) that detects new or changed data from the TCGdex REST API and pulls only what is missing. Supports three sync modes: *insert* (default, new entities only), *update* (refresh metadata and image URLs without per-card API calls), and *full* (re-fetch everything, CLI only). ([#449](https://github.com/jbourdin/expandedDecks/pull/449), [#451](https://github.com/jbourdin/expandedDecks/pull/451))
- **Rate limiting service** — `TcgdexApiThrottle` with configurable minimum delay (200ms), consecutive failure tracking, and cooldown (5 min after 3 failures). Filesystem-backed cache shared across workers. ([#450](https://github.com/jbourdin/expandedDecks/pull/450))
- **CLI command** — `symfony console app:tcgdex:sync --mode=insert|update|full` with queue depth reporting and `--force` guard for full mode. ([#451](https://github.com/jbourdin/expandedDecks/pull/451))
- **Admin dashboard sync card** — "TCGdex Database Sync" card with last sync timestamp, queue depth, cooldown status badges, and two buttons (insert + update mode). ([#451](https://github.com/jbourdin/expandedDecks/pull/451))
- **Webhook trigger** — anonymous `POST /webhook/tcgdex-sync` endpoint with HMAC-SHA256 signature verification for serverless cron jobs. Idempotent (returns 200 if sync already in progress). ([#451](https://github.com/jbourdin/expandedDecks/pull/451))
- **Entity image fields** — `logoUrl` on `TcgdexSerie`, `logoUrl` + `symbolUrl` on `TcgdexSet`, `imageBaseUrl` on `TcgdexCard`. `getImageUrl()` prefers `imageBaseUrl` when available, falling back to the computed URL. ([#449](https://github.com/jbourdin/expandedDecks/pull/449))
- **Card hydration service** — `TcgdexCardHydrator` extracted from the import command with `hydrateFromNdjsonRecord()` (git import) and `hydrateFromApiResponse()` (API sync, wraps English strings into multilingual format). ([#449](https://github.com/jbourdin/expandedDecks/pull/449))

### Bug Fixes

- **Image URL resolution** — `CardEnricher` and `CardIdentityResolver` now prefer the API-sourced `imageBaseUrl` over guessed/computed URLs, avoiding expensive HTTP reachability checks during enrichment. ([#451](https://github.com/jbourdin/expandedDecks/pull/451))
- **Broken image fallback** — HTML mosaic grid and card hover images now gracefully handle broken URLs: mosaic cells show a text placeholder with the card name, hover images hide instead of showing a broken icon. ([#455](https://github.com/jbourdin/expandedDecks/pull/455))

### Infrastructure

- **4 per-level Doctrine transports** — `tcgdex_sync_series`, `tcgdex_sync_serie`, `tcgdex_sync_set`, `tcgdex_sync_card` with `max_retries: 0` (handlers manage retry via redispatch). `make worker.sync` target added. ([#451](https://github.com/jbourdin/expandedDecks/pull/451))

### Testing & Quality

- **48 new unit tests** — covering all 5 sync handlers, webhook HMAC verification, CLI command modes, and card hydrator (NDJSON + API + update paths). Test count: 860 → 908. ([#451](https://github.com/jbourdin/expandedDecks/pull/451))

### Documentation

- **TCGdex sync deep-dive** — new `docs/technicalities/tcgdex_sync.md` covering cascade architecture, sync modes, rate limiting, change detection, triggers, environment variables, and edge cases. CLAUDE.md updated with sync transports and worker command. ([#455](https://github.com/jbourdin/expandedDecks/pull/455))

---

## [1.7.11] — 2026-04-20

Dependency security scanning pipeline and vulnerability fixes.

### Features

- **Health endpoint version** — the `/health` endpoint now includes the application version in its response, sourced from `composer.json`. ([#434](https://github.com/jbourdin/expandedDecks/pull/434))

### Bug Fixes

- **Mosaic share clipboard** — replaced Web Share API with direct clipboard copy for the mosaic share action, fixing unreliable behavior on desktop browsers. ([#435](https://github.com/jbourdin/expandedDecks/pull/435))
- **league/commonmark CVE-2026-33347** — updated from 2.8.1 to 2.8.2 to patch a medium-severity embed extension `allowed_domains` bypass. ([#440](https://github.com/jbourdin/expandedDecks/pull/440))
- **npm audit vulnerabilities** — resolved 11 JS dependency vulnerabilities (10 high, 1 moderate) via `npm audit fix` and an npm override for `serialize-javascript`. ([#441](https://github.com/jbourdin/expandedDecks/pull/441))

### Infrastructure

- **Dependency vulnerability scanning** — added `make audit` / `make audit.php` / `make audit.js` targets, a CI Security Audit job running `composer audit` and `npm audit` on every push/PR, GitHub Dependabot for weekly checks on both ecosystems, and `composer.json` `block-insecure: true` to gate installs. ([#439](https://github.com/jbourdin/expandedDecks/pull/439))

### Documentation

- **Security scanning docs** — new `docs/standards/security.md` documenting the full vulnerability scanning setup, response workflow, and npm overrides pattern. ([#442](https://github.com/jbourdin/expandedDecks/pull/442))

---

## [1.7.10] — 2026-04-19

Mosaic grid widened to 8 cards per row with transparent PNG background.

### Features

- **8-column mosaic grid** — increased mosaic layout from 6 to 8 cards per row in both the server-side GD-rendered PNG and the client-side HTML grid. Mobile breakpoint updated from 3 to 4 columns. Aligns with the original documented layout specification. ([#431](https://github.com/jbourdin/expandedDecks/pull/431))
- **Transparent mosaic background** — replaced the tiled `bg_fairy_quincunx.png` background with full PNG alpha transparency. Simplifies the GD rendering pipeline and removes the `$projectDir` dependency from `MosaicGenerator`. ([#431](https://github.com/jbourdin/expandedDecks/pull/431))

### Testing & Quality

- **2 new unit tests** covering `generateFromTiles()` empty input early return and incomplete last-row centering path for tile mosaics.

---

## [1.7.9] — 2026-04-17

Variant version history, public variant comparison page, and unified diff view with card image modals.

### Features

- **Variant version history** — admin-scoped version history at `/admin/archetypes/{id}/variants/{deckId}/versions` for archetype editors. Includes version list, compare, export, restore, and delete actions. Clock icon in variant list and edit form links to history. ([#412](https://github.com/jbourdin/expandedDecks/issues/412))
- **Restore previous version** — new "Restore" action on both user deck and admin variant version history pages. Sets `Deck.currentVersion` pointer to a previous version (no new version created). Dispatches re-enrichment if needed. Available on user deck history as well. ([#412](https://github.com/jbourdin/expandedDecks/issues/412))
- **Public variant comparison** — dedicated page at `/archetypes/{slug}/compare/{tagA}/{tagB}` showing a card-by-card diff between two archetype variants' current deck lists. Mantine Select pickers with sprites, swap button, and auto-swap on duplicate selection. Compare button added to archetype detail page variant selector. ([#413](https://github.com/jbourdin/expandedDecks/issues/413))
- **Unified comparison view** — both version compare and variant compare now display a single sorted list ordered by card type and quantity instead of separate added/removed/changed/unchanged sections. Green rows for additions, red for removals, orange for quantity changes. Delta shown inline as a smaller annotation: `2 (-2)`. ([#428](https://github.com/jbourdin/expandedDecks/issues/428))
- **Card identity merge** — variant comparison merges functionally identical cards with different printings (e.g. Boss's Orders from BRS vs PAL) via CardIdentity, using the canonical (lowest rarity) printing for display. ([#428](https://github.com/jbourdin/expandedDecks/issues/428))
- **Card image modal in comparisons** — clicking a card name opens a full-screen modal with the card image, quantity, and delta annotation in the title (colored by status). Works in both React version compare and Twig variant compare pages. ([#428](https://github.com/jbourdin/expandedDecks/issues/428))

### UX Improvements

- **Inline confirmation** — replaced browser `confirm()` dialogs with inline toggle pattern (trigger → Yes/Cancel with 5-second auto-reset) on version history restore and delete buttons. ([#412](https://github.com/jbourdin/expandedDecks/issues/412))
- **Swap buttons** — exchange arrows button between selectors on both version compare and variant compare, with auto-swap when selecting the same value as the other side.

### Infrastructure

- **Coverage memory limit** — bumped PHPUnit memory limit from 768M to 1536M in both `phpunit.xml.dist` and CI workflow to accommodate growing test suite.

### Testing & Quality

- **25 new functional tests** covering admin variant version history (access control, CRUD, compare, edge cases), user deck version restore, and public variant comparison page.

---

## [1.7.8] — 2026-04-17

Archetype variant deep-linking, extended reference tags, and editor copy helpers.

### Features

- **Variant URL anchors** — archetype page URLs now accept a `#shortTag` hash that auto-selects the corresponding variant on load. Selecting a variant updates the URL hash via `history.replaceState`, making variant links shareable. Browser back/forward navigation is supported via `hashchange` listener. ([F2.25](https://github.com/jbourdin/expandedDecks/issues/402))
- **Extended `[[archetype:slug:shortTag]]` tag** — the existing `[[archetype:slug]]` custom tag now supports an optional third segment with a variant's short tag. When present, the rendered link uses the variant's name and sprites instead of the archetype's, and points to `/archetypes/{slug}#shortTag`. Two-part tags remain unchanged (backward compatible). ([F2.25](https://github.com/jbourdin/expandedDecks/issues/402))
- **Copy-tag button for editors** — users with `ROLE_ARCHETYPE_EDITOR` or `ROLE_ADMIN` see a copy icon on each variant that puts `[[archetype:slug:shortTag]]` in the clipboard. ([F2.25](https://github.com/jbourdin/expandedDecks/issues/402))
- **Copy card reference in table view** — editors see a copy icon on each card row in the variant table view that copies `[[card:SET-NUMBER]]` to the clipboard for quick content referencing. ([F2.25](https://github.com/jbourdin/expandedDecks/issues/402))
- **RTE paste detection** — pasting `[[archetype:...]]`, `[[card:...]]`, or `[[deck:...]]` tags as plain text in the Tiptap editor now auto-converts them to their respective custom nodes via `addPasteRules()`. ([F2.25](https://github.com/jbourdin/expandedDecks/issues/402))
- **InsertReferenceButton multi-field input** — the archetype reference toolbar button now accepts `slug:SHORTTAG` format via a new `getAttributes` prop that parses the input into separate `slug` and `shortTag` attributes. ([F2.25](https://github.com/jbourdin/expandedDecks/issues/402))

### Testing & Quality

- **Variant tag expansion tests** — three new unit tests covering the `[[archetype:slug:shortTag]]` three-part tag: valid variant rendering, archetype-mismatch fallback, and unknown variant fallback.

---

## [1.7.7] — 2026-04-17

Variant selector grouped layout with dynamic overflow, and CI/test performance improvements.

### Style

- **Grouped variant selector** — desktop variant selector now renders current and outdated variants on separate rows. Outdated buttons use gray color, light variant, grayscale sprites, and italic text for a clearer visual distinction. Mobile Select dropdown uses group headers ("Current" / "Outdated") as separators.
- **Dynamic button overflow** — replaced fixed `MAX_BUTTONS` cap with a measurement-based approach (hidden row + `ResizeObserver`) that dynamically determines how many buttons fit on one line. Overflow items go into a styled Select dropdown with sprites and a "More variants…" placeholder.

### Bug Fixes

- **Coverage memory exhaustion** — added `$em->clear()` in `AbstractFunctionalTest::tearDown()` to flush Doctrine's identity map between tests, preventing cumulative entity memory growth during pcov coverage runs. Also fixed 41 pre-existing test failures caused by stale entity references leaking across tests.
- **`get_headers()` bypassing mock** — `CardEnricher::isImageReachable()` used PHP's native `get_headers()` which bypassed the mock HTTP client, hitting real CDNs during tests. Replaced with an injected `HttpClientInterface`.

### Infrastructure

- **CI job split** — split the monolithic `php-quality` job into parallel `php-lint` (CS-Fixer + PHPStan + unit tests, no MySQL) and `php-functional` (functional tests with MySQL). Lint and unit tests no longer wait for the MySQL health check.
- **Coverage memory limit** — bumped pcov coverage memory to 1G (from 768M) to account for coverage overhead.

### Testing & Quality

- **Mosaic generation stubbed** — replaced `GenerateDeckMosaicHandler` and `GenerateMinifiedMosaicHandler` with no-op stubs in functional tests, eliminating expensive GD image rendering. Functional test suite dropped from ~11m to ~2m.

---

## [1.7.6] — 2026-04-16

Mobile variant dropdown outdated styling.

### Bug Fixes

- **Mobile variant dropdown** — outdated variants in the mobile Select dropdown now show the same visual treatment (expansion badge, italic name, faded opacity) as the desktop pill buttons.

---

## [1.7.5] — 2026-04-16

Expansion set boundary, outdated variant flag, and card interaction refinements.

### Features

- **Expansion set boundary & outdated variant flag** — new `latestSet` field on Deck (ManyToOne → TcgdexSet) to characterize the format boundary. New `Outdated` status in `DeckStatus` enum for archetype variants. Expansion set dropdown (Expanded era only: BW onward) on both deck edit and variant forms. Outdated variants sort after current ones with faded badge + italic title styling, and a description banner showing the expansion name. ([F2.24](https://github.com/jbourdin/expandedDecks/issues/401))
- **Duplicate variant** — admin action to clone a variant with "Copy of" prefix, same list, description, sprites, and latest set. Redirects to the copy's edit page.
- **Re-enrich variant** — admin action (ROLE_TECHNICAL_ADMIN) to re-parse and re-enrich a variant's deck version from the variant edit form.
- **Enrichment pending state** — spinner placeholder on archetype variant view when card enrichment is still in progress.
- **Share mosaic on archetype variants** — Web Share API button (with clipboard fallback) for sharing the server-generated mosaic image.
- **Low-res mosaic generation** — server-generated mosaic now downloads `low.webp` instead of `high.webp` for faster async generation. Mosaic grid reduced to 6 cards per row to match the interactive grid.

### Refactoring

- **Click-to-modal for card names** — replaced desktop hover image preview with click-to-open-modal on card names in both deck and archetype table views. Extracted `CardImageModal` to a shared component. Responsive modal sizing capped at TCGdex native resolution (600×825px).

### Bug Fixes

- **Flush-reenrich SQL** — removed references to `tcgdex_id`, `image_url`, `trainer_subtype` columns that no longer exist on `deck_card` after the card identity refactor.
- **Nested form fix** — moved re-enrich form outside the main variant edit form to avoid invalid nested HTML forms.

### Testing & Quality

- 10 new tests: outdated toggle, duplicate variant, re-enrich, form fields, entity methods, and flush service SQL update.

---

## [1.7.4] — 2026-04-15

Interactive card mosaic replacing the static server-generated image.

### Features

- **Interactive card mosaic grid** — replaced the static server-generated PNG mosaic with a responsive 6-col (desktop) / 3-col (mobile) CSS Grid of `low.webp` card thumbnails. Clicking a card opens a `high.webp` modal with swipe/keyboard navigation and quantity display. Used on both the deck detail page and archetype variant view. Server-side mosaic generation is preserved for Web Share / social preview. ([F2.23](https://github.com/jbourdin/expandedDecks/issues/400))

### Bug Fixes

- **Hexagonal badge shadow on Firefox** — moved `filter: drop-shadow()` to a wrapper element to work around Firefox not rendering shadows when combined with `clip-path` on the same element.

### Infrastructure

- Enhanced `/next` skill with board hygiene and in-flight work assessment.

---

## [1.7.3] — 2026-04-13

Localized deck list support and canonical card name display.

### Bug Fixes

- **Localized basic energy enrichment** — French energy names like "Énergie Obscurité" now resolve to their English equivalent ("Darkness Energy") for TCGdex lookup. When TCGdex has no printing at all, the synthetic fallback uses the English name for a consistent `CardIdentity`.
- **Canonical card name display** — after enrichment, `DeckCard.cardName` is updated with the matched name from `CardIdentity` so tables and exports show "Boss's Orders" instead of the player's raw localized input (e.g. "Ordres du Boss").

### Testing & Quality

- 3 new unit tests: French energy resolution, synthetic fallback with English name, and canonical name update after enrichment.

---

## [1.7.2] — 2026-04-12

Card enrichment image fallback improvements and targeted re-enrich tooling.

### Features

- **Re-enrich single card** — new form in the technical dashboard to re-enrich a specific card by set code and card number. Detaches old printings, resets affected deck versions, and dispatches enrichment + mosaic/minified regeneration.

### Bug Fixes

- **Sibling-printing image fallback** — when a card has no image in TCGdex (e.g. new MEP promos), the enricher now checks sibling printings of the same CardIdentity before resorting to name search. Calls `expandPrintings()` to discover siblings from the local tcgdex_card table when none exist yet.
- **Skip name-based image search for Pokemon** — `findImageByName()` is no longer called for Pokemon cards, preventing false-positive matches across eras (e.g. Detective Pikachu Psyduck showing up for a Mega Evolution promo).
- **Card hover on version compare page** — `initCardHover()` was not called after React rendered the diff table, causing card image overlays to appear at the default fixed position instead of near the hovered row.

### Testing & Quality

- 5 new unit tests for the image fallback chain (sibling fallback, Pokemon gating, trainer fallback, most-recent preference, expand path).
- 10 new functional tests: controller (auth, CSRF, empty inputs, no match, dispatch), service (zero for unknown, count, detach), repository (match and no-match).

---

## [1.7.1] — 2026-04-10

Cache management tooling and card reference rendering fixes.

### Features

- **Admin cache management** — technical dashboard now has "Clear all app cache" button and "Delete specific cache key" input field, enabling cache invalidation on serverless deployments without console access.

### Bug Fixes

- **Smart cache TTL** — card data is cached for 24h only when both name and image URL are present. Unresolved or imageless cards use 5-minute TTL so they are retried quickly instead of being stuck for a full day.
- **Missing translations** — added `app.deck.enriched` and `app.deck.pending` keys (EN/FR) for variant list enrichment status badges.

---

## [1.7.0] — 2026-04-10

Archetype variant system: editorial decklists per archetype with admin management, public variant selector, copy-to-clipboard, and drag-and-drop ordering.

### Features

- **Archetype variant decks** — reuse the Deck entity with nullable owner and canonical boolean to represent editorial decklists attached to archetypes. `getOwnerOrFail()` for borrow/event contexts, `isArchetypeVariant()` convenience method.
- **Admin variant management** — create, edit, delete variant decks from the archetype edit page via a `+` button. Variant form with name, canonical toggle, sprite selector, decklist paste (reuses DeckVersion enrichment pipeline), and Markdown description via rich text editor.
- **Public variant selector** — client-side variant switcher on the archetype detail page. Desktop: pill buttons with sprites. Mobile: Mantine Select dropdown with sprites in options. Table/mosaic view toggle, defaults to mosaic on desktop and table on mobile.
- **Copy-to-clipboard** — "Copy list" button copies the variant's raw decklist (PTCG format) to clipboard with 2-second "Copied!" feedback via Mantine CopyButton.
- **Drag-and-drop ordering** — reusable SortableJS table helper for both archetype catalog ordering and variant ordering within an archetype. Accessible up/down buttons on mobile. AJAX endpoints persist positions.
- **Relevance sort** — archetype catalog defaults to position-based "Relevance" sort instead of alphabetical.

### Bug Fixes

- **Card hover sweep groups** — `initCardHover()` supports `data-card-hover-group` scoping so decklist cards sweep together while `[[card:...]]` references in descriptions open standalone without prev/next arrows.
- **Card modal title** — always shows "N × Card Name" for decklist cards; just "Card Name" for standalone references.
- **Sprite selector fix** — `archetype-form.tsx` reads `data-hidden-input-name` so the sprite selector works on both archetype and variant forms.
- **Canonical always first** — variant query orders by `canonical DESC, position ASC`; reorder endpoint pins canonical at position 0.

### Testing & Quality

- 30+ new tests: entity unit tests (canonical, variant detection, position), functional tests for admin variant CRUD, reorder endpoints, detail page variant selector, and repository queries.
- Regidrago archetype variant fixtures with parsed decklists for reproducible testing.

---

## [1.6.4] — 2026-04-10

CMS editor table support, page creation improvements, multi-channel locale handling, and content typography.

### Features

- **Table support in CMS editor** — Tiptap table extension with toolbar controls (insert, add/remove rows & columns, toggle header, delete). Table CSS for editor and public pages with navy-themed headers and striped rows.
- **Table rendering on public pages** — GFM `TableExtension` added to `league/commonmark` so markdown pipe tables render as HTML.
- **Page creation title field** — new title input on the page creation form with auto-generated slug.
- **Per-channel locale configuration** — channels define their available locales; locale resolution is constrained to the channel's configured set.
- **Page prefill from channel** — new page form auto-fills channel and menu category from the current admin context.
- **Display translation fallback** — `Page::getDisplayTranslation()` skips translations with empty content, falling back to the next available locale.

### Bug Fixes

- **H1 stripping** — editor automatically downgrades `# ` (h1) to `## ` (h2) on save; h1 is reserved for page titles.
- **Notification bell on non-deck channels** — hidden for channels that don't use deck features.
- **PHPStan type annotations** — added missing type hints on event listener data arrays.
- **Brand name footer default** — uses `brand_name` channel parameter in copyright footer.
- **Channel parameters transformer** — replaced model transformer with event listeners for channel parameter injection.

### Infrastructure

- **Dev domain rename** — `expanded-decks.wip` → `expandeddecks.wip` and added `expandedtalks` domain to Symfony proxy config.

### CMS Content Typography

- **Heading sizes** — h2/h3/h4 in `.cms-content` scaled to match Mantine editor proportions (1.5em / 1.25em / 1.1em).
- **Blockquote styling** — left border, light navy background, and comfortable padding on public pages.

---

## [1.6.3] — 2026-04-09

Channel parameters, theme refinements, and footer customization.

### Features

- **Channel parameters JSON field** — flexible key-value store on Channel entity. `channel_param('key', 'default')` Twig function reads values with graceful fallback (safe on error pages and CLI). Admin form with add/remove key-value pairs.
- **Theme CSS on error pages** — `channel_theme()` Twig function loads the channel's theme CSS on error pages when a channel is resolved, falling back to default.

### Bug Fixes

- **Footer theme** — Chaotic Swell overrides now use `.footer-pokemon` class (not generic `footer`) with correct colors for background, border, links, and headings.
- **Brand name from parameters** — all templates use `channel_param('brand_name', 'Expanded Decks')` instead of file-based `_brand.html.twig` partials. Removed theme override files.
- **Footer copyright from parameters** — `channel_param('copyright_footer', ...)` allows per-channel footer text.
- **Key-value form robustness** — transformer handles null/empty form values without 500. Remove button uses `×` symbol.
- **Migration fix** — JSON column uses nullable add → backfill → NOT NULL (MySQL 8 doesn't support DEFAULT on JSON).

---

## [1.6.2] — 2026-04-09

Theme path isolation, page cache invalidation, and per-channel brand in page titles.

### Bug Fixes

- **Theme path leak across PHP-FPM workers** — `ThemeRequestListener` now filters out previously prepended theme paths from the Twig `FilesystemLoader` on each request, preventing brand name and template bleed between channels served by the same worker process.
- **Page admin cache invalidation** — publishing, editing, deleting, duplicating, or saving a translation for a page now invalidates the menu navigation cache. Previously, changing a page's published status didn't refresh the navigation.
- **Brand name in page titles** — all `{% block title %}` suffixes now use `{% include '_brand.html.twig' %}` instead of hardcoded "Expanded Decks", so the browser tab shows the correct brand per channel.

### Testing & Quality

- Added functional tests for page edit, delete, and duplicate actions covering the `invalidateCache()` calls.

---

## [1.6.1] — 2026-04-09

Bug fixes for the channel system: homepage rendering, cache invalidation, and admin tooling.

### Features

- **Clear navigation cache** — new button on the technical admin dashboard (`/admin/technical`) to manually clear all channel-scoped menu and footer cache.

### Bug Fixes

- **Homepage fallback for unassigned layouts** — `findPublished()` now matches layouts with `channel_id = NULL` as fallback, so existing layouts still render after migration. Dashboard redirect only triggers on channels with `enableDecks = true`.
- **Homepage editor sends channelCode** — the React editor includes `channelCode` in the save payload, so each channel gets its own layout instead of overwriting a single unassigned one.
- **Menu cache invalidation on all admin actions** — create, edit (channel change), save translation, reorder categories, and page reorder now call `MenuRuntime::invalidateCache()` with correct channel-scoped cache keys. Previously, reorder methods cleared non-existent old keys.

---

## [1.6.0] — 2026-04-09

Multi-domain channel system — serve different feature sets and content from different domains, with per-channel theming.

### Features

- **Channel entity and resolver (F18.1, F18.2)** — `Channel` entity with code, domain, and feature flags. `ChannelResolverListener` resolves the current channel from the Host header on every request. `ChannelContext` provides the channel to any service via RequestStack. Lazy default channel creation on fresh installs.
- **Twig channel context (F18.3)** — `current_channel()` and `is_channel(code)` functions. Navigation conditionally rendered based on channel feature flags.
- **Feature-gate middleware (F18.4, F18.7)** — `ChannelFeatureGateListener` returns 404 for routes disabled on the current channel (decks, events, borrows, register). Login, profile, and admin always accessible.
- **Channel-aware URL generation (F18.5)** — `ChannelUrlGenerator` with `feature_url()` Twig function. Returns relative paths for same-channel links, absolute URLs for cross-domain. Cross-domain links open in new tab with `target="_blank"`.
- **Admin channel CRUD (F18.6)** — List, create, edit channels with feature toggles. Domain names displayed in admin toggles.
- **Channel on MenuCategory (F18.8)** — Per-channel navigation and footer. Admin category list with channel toggle, category selector on edit form.
- **Channel on Page** — Per-channel page scoping with composite `(slug, channel_id)` unique constraint. Page form with channel selector and channel-filtered categories. Admin page list with channel toggle and category button groups. Page duplicate action.
- **Channel on HomepageLayout (F18.10)** — Per-channel homepages. Admin editor with channel toggle.
- **Cross-channel linking (F18.19–F18.22)** — Archetype links from app channel open content channel in new tab. Deck links from content channel open app channel. Archetype catalog hides deck counts and sort-by-decks on content channel. Archetype detail hides "Latest decks" section on content channel.
- **Per-channel theme system (F18.28)** — `Channel.themeName` selects a theme. `ThemeRequestListener` prepends `templates/themes/{name}/` to Twig's loader paths. Per-theme SCSS via Webpack Encore entries. Theme dropdown in admin (scans theme directories). "Chaotic Swell" theme for the content channel with desert/storm color palette.
- **Brand name per theme** — `_brand.html.twig` partial overridden per theme ("Expanded Talks" on content channel).

### Bug Fixes

- Login, profile, forgot/reset-password always accessible on all channels (only register gated).
- Empty page list when channel has no categories.
- Admin view/preview links respect page channel domain.
- Migration backfills for existing pages, categories, and homepage layouts.

### Infrastructure

- Register `expandedtalks.wip` domain in `make install`.

### Testing & Quality

- 50+ new unit and functional tests covering Channel entity, context, resolver, URL generator, feature gate, theme listener, Twig runtime, admin CRUD, and channel resolution.

### Refactoring

- Renamed `isArchetypeSource` to `enableArchetypes` for consistency with other feature flags.

---

## [1.5.3] — 2026-04-08

UX polish and image fallback improvements for the deck show page.

### Bug Fixes

- **"I found this deck" button** — moved to the bottom of the deck page and restyled as a discreet subtle gray button instead of a full-width outlined one. (#330)
- **"Playstyle tags" label** — renamed to just "Tags" in both English and French translations. (#332)
- **Borrow login CTA** — replaced the prominent card with a discreet inline text line for anonymous visitors, moved after the card list.
- **Dialga GX FLI 82 broken image** — added image override for TCGdex CDN 404 (falls back to PokemonTCG.io).
- **Minified mosaic image fallback** — the tile-based mosaic path now uses `CardImageResolver` with full fallback chain (CDN variants, PokemonTCG.io, sibling printings) and persists corrected URLs on `CardPrinting`. Previously it did raw URL fetching with no fallback.
- **Minified card views stale URLs** — the mosaic handler now regenerates the card views JSON after mosaic generation, picking up URLs corrected by the fallback chain. Fixes broken hover images in the table view.
- **Card hover preview** — switched to `position: fixed` with JS-computed viewport-aware positioning, eliminating cropping on all screen edges. Responsive sizing based on viewport height (`clamp(280px, 33vh, 672px)`). Fixed flicker by setting position imperatively before display.

### Testing & Quality

- 3 new unit tests covering tile fallback resolution in `MosaicGenerator` and printing passthrough in `GenerateMinifiedMosaicHandler`.

---

## [1.5.2] — 2026-04-07

Bug fixes for delegated admin roles, CMS content rendering, and card image fallback.

### Bug Fixes

- **Admin sub-role access** — `ROLE_ARCHETYPE_EDITOR` and `ROLE_CMS_EDITOR` can now access their respective `/admin/*` routes. Added route-specific `access_control` rules before the `ROLE_ADMIN` catch-all. RTE endpoints (image upload, card image URL) accept both editor roles via expression-based `#[IsGranted]`. (#327, #328)
- **Custom RTE tags on content pages** — `[[card:...]]`, `[[archetype:...]]`, and `[[deck:...]]` tags now render on CMS content pages (previously only worked on archetype pages). `PageController` uses the tag-aware renderer; new `page_show` entry point initializes card hover and image modal. (#329)
- **Card image fallback for broken URLs** — enrichment now validates image URLs with a HEAD request and replaces broken ones (404) with PokemonTCG.io or name-based fallbacks. Mosaic generation adds a sibling-printing fallback: when all CDN sources fail, another printing of the same card is used. Working URLs are persisted on `CardPrinting`. (#331)

### Infrastructure

- Move CMS content pages before app links (Archetypes, Decks, Events) in the navigation menu. (#326)

---

## [1.5.1] — 2026-04-06

Session and remember-me duration configuration.

### Infrastructure

- Extend session lifetime to 1 day (`cookie_lifetime` and `gc_maxlifetime` set to 86400s) so sessions survive browser restarts and idle periods. (#323)
- Bump remember-me token lifetime from 7 days to 30 days. (#323)

---

## [1.5.0] — 2026-04-06

Asian set alias resolution, deck re-enrichment, version management, and UX improvements.

### Features

- **Asian set code resolution** — new `tcgdex_asian_set_alias` table maps ~119 Japanese/Asian set codes (SM8, S6K, SV1S, etc.) to their international equivalents. Enrichment uses name-within-set matching when an alias is found (card numbers don't transfer between JP and international products). (#321)
- **Deck re-enrich action** — technical admins can re-parse and re-enrich a deck from its raw list via the deck actions dropdown. Ensures new resolution strategies apply to previously imported decks. (#321)
- **Deck actions dropdown** — action buttons on the deck show page converted from flat horizontal list to a Bootstrap "..." dropdown menu. Import List remains standalone as the primary action. (#321)
- **Version history management** — export a version's deck list as `.txt` download; soft-delete previous versions (not current). `DeckVersion.deletedAt` column added. (#321)

### Bug Fixes

- Fix `DeckShowController` flash messages displaying translation keys instead of translated text (wrong base class).
- Fix unique constraint violation when re-enriching a deck (flush card removals before re-creating).

### Infrastructure

- `make fixtures` now runs `make tcgdex.import` to populate local card data before enrichment.

### Testing & Quality

- 3 new tests covering Asian alias resolution in `TcgdexApiClient`.

---

## [1.4.0] — 2026-04-06

Local-first card model — enrichment resolves from local `tcgdex_*` tables instead of the TCGdex API, with automatic image fallback and precomputed canonical printings.

### Features

- **Local-first card resolution** — `TcgdexApiClient.findCard()` and `findAllPrintingsByName()` check local `tcgdex_*` tables before falling back to the HTTP API. Same candidate fallback chain (exact, letter-stripped, zero-padded) applied to local lookups. (#314)
- **Canonical printing selection** — price-free algorithm using rarity tier + release date (no API dependency). Results cached via `is_canonical` flag on `CardPrinting`, computed lazily on first minified list request. (#314)
- **Image fallback chain** — new `CardImageResolver` service: when TCGdex CDN fails (dotted set IDs like sm3.5), tries dot-removed URL then pokemontcg.io. Updates `CardPrinting.imageUrl` on success for subsequent requests. (#314, #316)

### Refactoring

- **Card model restructured** — `CardPrinting` is now a proxy to `TcgdexCard` with `tcgdexCard` FK and `isCanonical` flag. `CardIdentity` gains `trainerType` for deck display sorting. `DeckCard` simplified: `tcgdexId`, `imageUrl`, `trainerSubtype` columns removed and replaced by computed accessors via `cardPrinting`. New `cardLocale` field (default "en").
- **CardEnricher** sets image URL and overrides on `CardPrinting` instead of `DeckCard`. `BASIC_ENERGY_IMAGES` restored as multilingual last-resort fallback (6 Western locales + Japanese) with synthetic `CardPrinting` creation.

### Bug Fixes

- **Mosaic generation** — skip gracefully for empty deck versions and empty tile lists instead of throwing (avoids messenger retry loop). (#319)

### Testing & Quality

- 31 new unit tests covering local-first resolution, image fallback URL generation, canonical selection, entity getters, trainerType backfill, and mosaic handler early returns. All codecov checks pass.

---

## [1.3.3] — 2026-04-05

Local TCGdex card database — dedicated `tcgdex_*` tables mirroring the cards-database repository for offline card resolution.

### Features

- **Local TCGdex card database** — new `tcgdex_serie`, `tcgdex_set`, and `tcgdex_card` entities storing full multilingual card data (en, fr, es, it, pt, de) in JSON columns. MySQL generated columns (`name_en`, `name_fr`) provide indexed lookups. (#314, #317)
- **Import CLI command** — `app:tcgdex:import --clone` clones the `tcgdex/cards-database` git repository and populates the local tables (20k+ cards, 191 sets, 20 series). Supports `--truncate` for full reload. Makefile target: `make tcgdex.import`.
- **TypeScript extractor** — `scripts/tcgdex-extract.ts` reads the cards-database repo and outputs NDJSON with series, sets, and cards including expanded legality computed from `meta/legals.ts` rules.

### Refactoring

- **Computed image URLs** — `TcgdexCard::getImageUrl()` derives the CDN URL from the serie/set/card hierarchy with configurable resolution and format (default: `high.webp`), instead of storing redundant URLs.

### Infrastructure

- Increase CLI memory limit to 512M via `.symfony.local.yaml`.
- Exclude Pokémon TCG Pocket serie from import (different card game, not relevant for Expanded format).

---

## [1.3.2] — 2026-04-02

Homepage block improvements, ImageUrlField component, CMS page model simplification, and entity-linked featured blocks.

### Features

- **F10.6 — ImageUrlField component** — reusable React component combining URL text input with drag-and-drop image upload to `/api/editor/upload-image`. Applied to ogImage field on CMS pages and carousel image fields in the homepage block editor. (#288)
- **F10.3 — Split richText into richText and pageEmbed** — `richText` now stores inline translatable Markdown content; `pageEmbed` references a CMS page by slug. Separate block types with distinct admin editor fields. (#309)
- **F10.9 — MarkdownEditor in block editor** — replace plain textareas with Tiptap rich text editor for richText content and featured block description fields. Extended MarkdownEditor to support onChange callback for React state. (#307)
- **Reworked featuredDeck** — takes a deck shortTag, resolves the Deck entity, renders mosaic, archetype sprites, translatable title/description, and link to deck detail. Defaults to col-6.
- **Reworked featuredEvent** — takes an event ID, resolves the Event entity, renders name, date, location, optional image, translatable title/description, and link to event detail. Defaults to col-6.

### Bug Fixes

- **ogImage validation** — accept relative URLs (`/api/editor/image/...`) alongside absolute URLs via `@Assert\Regex`.
- **ogImage fallback** — EN ogImage used when locale translation has none (graphical fields only).
- **Empty ogImage** — convert empty string to null with `empty_data` to pass URL validation.
- **findPublished** — order by ID DESC to handle duplicate layouts after fixture reload.
- **Featured block rendering** — translated title and description now properly resolved and displayed.

### Refactoring

- **Simplified CMS page model** — removed localized slugs, metaTitle, metaDescription, canonicalUrl from PageTranslation. Moved ogImage to Page (language-neutral). Translation tabs now only have title + content.

### Infrastructure

- Empty homepage seed migration (data seeded by fixtures in dev, admin editor in production).
- `tests/Enum/` directory added to phpunit.xml.dist (from previous release).

---

## [1.3.1] — 2026-04-02

Bug fixes for translation form editing and menu cache consistency.

### Bug Fixes

- **Per-locale translation form names** — both EN and FR translation forms for pages and archetypes shared the same form name, causing duplicate textarea IDs. The rich text editor for FR showed EN content. Fixed by using `createNamed()` with locale suffix for unique form names.
- **Content textarea validation** — disable HTML `required` attribute on the content textarea hidden behind the rich text editor, preventing "not focusable" browser error. Server-side `@Assert\NotBlank` still enforces the constraint.
- **Menu cache after page reorder** — page reorder uses raw DQL updates which bypass Doctrine lifecycle events, so the menu cache was stale until expiry. Explicitly flush `menu_categories` and `footer_categories` cache after reorder.

### Infrastructure

- Update legal notice fixture with real site owner, hosting (Scaleway Paris, Bunny CDN), contact (GitHub issues), intellectual property, liability, and deck lending/borrowing responsibility in EN and FR.

---

## [1.3.0] — 2026-04-01

Configurable homepage layout with admin block editor, footer category management, and universal homepage.

### Features

- **F11.2 — Footer menu categories** — add `isFooter` flag to `MenuCategory` entity. Admin category list with Menu/Footer toggle and SortableJS drag-and-drop reordering. New categories inherit type from the active view. Footer renders in the site footer with pages ordered by position. (#300)
- **F10.3 — HomepageLayout entity and data model** — `HomepageLayout` and `HomepageLayoutTranslation` entities with JSON block storage. `HomepageBlockType` string-backed enum (hero, richText, carousel, latestPages, featuredDeck, featuredEvent) with metadata methods. Repository with `findPublished()`. (#285)
- **F10.4 — Homepage rendering service and Twig block partials** — `HomepageRenderer` resolves layout into `ResolvedBlock` DTOs with startAt/endAt scheduling, dynamic data resolution (event/deck counts, latest pages, CMS content), and locale-aware translations. 6 Twig block partials with Bootstrap grid row grouping by `columnWidth`. Fallback to existing homepage when no layout published. (#286)
- **F10.5 — Homepage block editor (admin UI)** — React island with Mantine: sortable block list (SortableJS), add block type picker, edit modal with column width selector, scheduling datetime pickers, locale tabs for translatable content (hero, richText, featured deck/event), carousel item management, and live grid preview. Admin nav link for `ROLE_CMS_EDITOR`. (#287)
- **F10.7 — Carousel block** — Bootstrap 5 swipeable image carousel with per-item startAt/endAt scheduling, indicators, and prev/next controls. Admin editor for managing carousel items. (#289)
- **F10.8 — Universal homepage** — homepage at `/` visible to all users (anonymous and authenticated). Dashboard moved to `/dashboard`. Hero block hides Register/Login CTAs for logged-in users. (#290)

### Infrastructure

- Add `is_footer` column to `menu_category` table (migration).
- Create `homepage_layout` and `homepage_layout_translation` tables (migration).
- Idempotent data migration seeding default homepage layout for production.
- New Webpack Encore entries: `admin_menu_category_list`, `homepage_editor`.
- Add `tests/Enum/` directory to phpunit.xml.dist unit suite.
- Footer styling: no underline on links, brighter category headings.

### Documentation

- New `docs/models/homepage.md` — homepage layout entities, enum, and JSON block structure.
- Update `docs/models/cms.md` with `isFooter` field on `MenuCategory`.
- Roadmap milestone renumbering and zero-padding.

### Testing & Quality

- Unit tests for `HomepageBlockType`, `HomepageLayout`, `HomepageLayoutTranslation`.
- Service tests for `HomepageRenderer` (scheduling, all block types, carousel filtering, translation fallback).
- Functional tests for `AdminHomepageController` (auth, editor, save, preview).

---

## [1.2.2] — 2026-04-01

Content editing experience improvements: card image insertion, inline CRUD menus, draft preview, and admin page management.

### Features

- **F17.8 — Insert card image from reference** — new RTE toolbar button that prompts for a card reference (e.g. `UPR-100`), resolves it to a TCGdex image URL via `GET /api/card/image-url` (local DB first, TCGdex API fallback), and inserts the image with a default `max-width: 180px`. Supports resize and alignment like any other editor image.
- **F7.9 — Inline CRUD menu (three-dots)** — contextual ⋮ dropdown menus on public archetype show/list and CMS page show views, plus admin list views. Provides quick access to View/Preview and Edit actions. Hidden for users without the appropriate role.
- **F7.11 — Draft state with preview** — require `?preview=true` query parameter to view unpublished archetypes and pages (prevents accidental access). Edit forms show a "Preview" button for drafts and "View" for published content. Draft preview pages display a warning banner with eye icon.
- **Drafts filter on archetype catalog** — "Drafts" filter button visible to `ROLE_ARCHETYPE_EDITOR` users on the archetype catalog. Shows only unpublished archetypes with draft badge and preview links.
- **F7.10 — Admin pages: category filter and drag-and-drop sorting** — category dropdown filter on admin page list. When a category is selected, pages are sorted by position and reorderable via SortableJS drag-and-drop (desktop) or up/down arrow buttons (mobile). Positions persisted immediately via AJAX. Drag-and-drop enabled on page 1 only (50 items/page for category view).
- **View button on archetype edit form** — opens the public archetype page in a new tab, matching the existing pattern on page edit forms.

### Bug Fixes

- **Archetype role fix** — replace `ROLE_ADMIN` with `ROLE_ARCHETYPE_EDITOR` in `AdminArchetypeController`, `ArchetypeDetailController` preview check, public view menus, and navbar link. Users with just `ROLE_ARCHETYPE_EDITOR` can now manage archetypes without full admin.
- **Category filter empty string** — fix `FILTER_NULL_ON_FAILURE` error when submitting the admin page list with "All" category selected (empty string to `getInt()`).

### Infrastructure

- Add `position` column to `page` table (migration `Version20260401085844`) for category-based ordering.
- Install SortableJS (`sortablejs` + `@types/sortablejs`) for drag-and-drop page reordering.
- New `admin_page_list` Webpack Encore entry for sortable page list JS.
- CSS: `.no-caret` utility to hide Bootstrap dropdown caret on icon-only toggle buttons.

---

## [1.2.1] — 2026-03-31

Image upload, resize, alignment, and Pandoc-style attributes for the rich text editor.

### Features

- **F17.4 — Image upload backend** — dedicated Flysystem storage (`editor_upload.storage`, separate from mosaics) with `POST /api/editor/upload-image` (ROLE_CMS_EDITOR, validates MIME type + 5 MB max) and `GET /api/editor/image/{filename}` (public, 30-day immutable cache). Supports local and S3 adapters.
- **F17.5 — Image drag-and-drop in RTE** — drop or paste images into the editor for instant base64 preview, async upload to the backend, then replacement with the permanent URL. Uses `@tiptap/extension-image` and `@tiptap/extension-file-handler`.
- **Pandoc-style attributes** — enable `league/commonmark` `AttributesExtension` in `MarkdownRenderer` for server-side rendering of `{style="max-width: Xpx" .class}` on images and `{#anchor-id}` on headings.
- **Heading anchors** — custom `HeadingWithId` Tiptap extension that parses `{#id}` from heading text and serializes it back, enabling table-of-contents style anchors.
- **F17.7 — Image float and alignment** — four toolbar buttons (float left, center, float right, none) set Bootstrap-compatible CSS classes on images. Serialized as Pandoc-style `{.float-start}` in Markdown. CSS `:has()` propagates float from `<img>` to the ResizableNodeView container in the editor.

### Bug Fixes

- **Duplicate link warning** — disable `link` from StarterKit (Tiptap v3 now bundles it) and use explicit `@tiptap/extension-link` import.
- **Image resize handles** — add CSS for Tiptap `ResizableNodeView` handle elements (corner dots + edge bars with hover reveal).
- **Image resize Markdown serialization** — let ResizableNodeView write `width`/`height` natively, translate to `max-width`/`max-height` CSS at render and serialization time.
- **PHP image dimension rendering** — serialize dimensions as `style="max-width: Xpx"` instead of invalid `max-width` HTML attributes.
- **Responsive image sizing** — add `width: 100%` on images with `max-width` constraint so they fill their container and scale down on narrow viewports.

### Refactoring

- Use `max-width`/`max-height` instead of `width`/`height` for resized images, enabling responsive scaling.

---

## [1.2.0] — 2026-03-30

Rich text editor for archetype descriptions and CMS page content with custom tag support.

### Features

- **F17.1 — Mantine RichTextEditor with Markdown** — Replace plain textareas for archetype descriptions and CMS page content with a Tiptap-based rich text editor (`@mantine/tiptap` + `tiptap-markdown`). Supports headings, bold, italic, lists, links, code blocks, and blockquotes. Toggle between WYSIWYG and raw Markdown editing modes. Content stored as Markdown with no schema migration needed. Reusable `MarkdownEditor` React component with hidden textarea sync for standard Symfony form submission. New `page_form` Webpack Encore entry point.
- **F17.2 — Custom `[[card:SET-NUM]]` tag extension** — Custom Tiptap inline node that parses `[[card:SET-NUM]]` from Markdown via a markdown-it rule, renders as a blue badge in the editor, and serializes back to the original syntax on save.
- **F17.3 — Custom `[[archetype:slug]]` tag extension** — Custom Tiptap inline node for `[[archetype:slug]]` tags, rendered as a green badge in the editor with full Markdown round-trip.
- **Custom `[[deck:SHORT_TAG]]` tag extension** — Custom Tiptap inline node for `[[deck:XXXXXX]]` 6-character short tags, rendered as a dark badge in the editor with full Markdown round-trip.
- **F17.6 — Toolbar buttons for tag insertion** — Three popover buttons in the RTE toolbar let users insert `[[card:...]]`, `[[archetype:...]]`, and `[[deck:...]]` references with input validation, without switching to raw Markdown mode.

### Testing & Quality

- Unit tests for `MarkdownEditor` component (5 tests: render, toggle, mode switch, sync, empty content).
- Unit tests for `CardReference` extension (3 tests: single badge, multiple badges, complex set codes).
- Unit tests for `ArchetypeReference` extension (3 tests: single badge, multiple badges, mixed with card refs).
- Unit tests for `DeckReference` extension (3 tests: badge rendering, mixed references, invalid tag rejection).
- `ResizeObserver` mock added to Vitest setup for Mantine `SegmentedControl` compatibility.

---

## [1.1.1] — 2026-03-29

Hotfix for deck-found button not rendering in French locale.

### Bug Fixes

- **"I found this deck" button broken in French** — French translations containing apostrophes (e.g. "J'ai trouvé", "l'accueil") broke the `data-labels` HTML attribute, causing a JSON parse error that prevented the React island from mounting. Fixed by using `|e('html_attr')` escaping instead of `|raw`.

---

## [1.1.0] — 2026-03-29

Bot protection with Friendly Captcha, lost & found deck alert, and email sender improvements.

### Features

- **F12.4 — Bot protection with Friendly Captcha** — EU-based, GDPR-compliant proof-of-work captcha on registration, login, and forgot-password forms. Uses the official `friendlycaptcha/sdk` PHP SDK wrapped in `FriendlyCaptchaVerifier`, with a reusable `FriendlyCaptchaType` Symfony form type and `LoginCaptchaListener` for the login flow. JS widget loaded via `@friendlycaptcha/sdk` npm package bundled through Webpack Encore. Verification is skipped when `FRIENDLY_CAPTCHA_API_KEY` is empty (safe for tests and unconfigured dev).
- **F4.16 — Lost & found deck alert** — private decks no longer return 403; instead, a limited view shows the deck name, owner identity (screenName, playerId, full name), and a "I found this deck" button. The button opens a Mantine modal with a required message field, optional anonymous toggle (for logged-in users), Friendly Captcha protection, and a "Copy Discord username" clipboard button when the owner has one. Submitting creates an in-app notification and sends an email to the deck owner with the reporter's message. New `DeckFound` notification type with preferences toggle.
- **Discord username on User** — new optional profile field (`discordUsername`), editable in user profile settings. Shown to deck finders in the found-deck modal. Cleared on GDPR anonymization.

### Infrastructure

- **Email sender refactor** — all email senders now use `MAIL_SENDER_NAME` env var instead of hardcoded `'Expanded Decks'`. All `to` fields include the recipient's `screenName` via `Address` objects.
- **Friendly Captcha CSS** — global `.frc-captcha` and `.frc-captcha-container` full-width override for the SDK's hardcoded 316px inline width.
- **Notification list rendering** — `white-space: pre-line` for multi-line notification messages.

### Testing & Quality

- Unit tests for `FriendlyCaptchaVerifier`, `FriendlyCaptchaValidator`, `LoginCaptchaListener`, `DeckFoundNotificationService`, User `discordUsername` field, and `anonymize()`.
- Functional tests for `DeckFoundController` (5 scenarios: success logged-in, anonymous, owner blocked, empty message, invalid CSRF).
- Fixed pre-existing mock-vs-stub PHPUnit notices in `BorrowServiceOverdueTest`.
- Added `tests/Validator/` to `phpunit.xml.dist` unit suite.

---

## [1.0.8] — 2026-03-28

Overdue tracking with ending phase, private deck visibility fix, and multilingual basic energy support.

### Features

- **F4.6 — Overdue tracking with ending phase** — two-phase deck return tracking at events. The organizer starts the "ending phase" which cancels pending/approved borrows, locks new lending, and sends return reminders to borrowers and owners. Contextual banners appear on the event page for borrowers (return prompt), owners (custody/return counts), and organizer/staff (global progress). Finishing the event transitions all remaining lent borrows to overdue, sends urgent notifications, and notifies owners of delegated decks in staff custody to pick them up. Both actions are independent — finishing without ending phase fires all effects together.
- **F4.17 — Borrow & custody dispute** *(spec only)* — added feature stub for three-party dispute threads (organizer, owner, borrower) on borrow or custody issues. Full implementation deferred.

### Bug Fixes

- **Private decks hidden in event selection** — the "Your Decks" (lending) and "Deck Selection" (play) lists on the event page now show only public decks by default, with a "Show private decks" toggle. Already-selected or registered private decks remain visible.
- **Approve/hand-off buttons hidden during ending phase** — approve and hand-off actions are now hidden in the event view, borrow detail, and borrow inbox when the event is in ending phase or finished.
- **Multilingual basic energy validation** — basic energy cards exported from PTCGL in French, German, Spanish, Italian, Portuguese, or Japanese are now correctly recognized and exempt from the 4-copy limit. Previously only English names were supported.

### Documentation

- **Overdue tracking specification** — `docs/plans/overdue_tracking.md` with full lifecycle, banners, notifications, and implementation notes.
- **Updated event and borrow models** — new `endingPhaseAt` field, ending phase behavior section, enhanced finishment behavior, three-column comparison table (ending phase vs finished vs cancelled).
- **Updated feature descriptions** — F3.20, F4.6, F4.8, F8.3, and notification matrices updated to reflect the two-phase approach.
- **Context7 MCP documentation lookup** — added to CLAUDE.md as the preferred source for library/framework docs.

### Testing & Quality

- 35 new tests: unit tests for `StartEndingPhaseHandler`, `FinishEventBorrowsHandler`, `BorrowService` overdue/guards, `EventNotificationService` ending phase and custody pickup methods; functional tests for ending phase controller actions, banners, and lending locks; validator test for French basic energy.

---

## [1.0.7] — 2026-03-28

Archetype soft-delete hardening and custom Pokemon-themed error pages.

### Features

- **Custom error pages with Pokemon sprites** — error pages now display Pokemon sprites and themed messages: Snorlax (403), Ditto (404), Maushold family of four (429), Porygon (500), Psyduck (generic). Dev pages show full stack trace inside the app template. XHR/JSON requests receive JSON error bodies with correct HTTP status. Non-HTML requests get empty bodies.
- **CDN error page route** — `/cdn-error/{code}` returns 200 with the themed error page HTML, for Bunny CDN to fetch and cache as custom error pages. Does not trigger Sentry.
- **Test error route** — `/test-error/{code}` throws a real HTTP exception for previewing error pages in dev.

### Bug Fixes

- **Deleted archetypes hidden from all views** — soft-deleted archetypes are now filtered from the admin list, deck detail properties, deck catalog, event available decks, and tournament results.
- **Deleted archetype detail returns 404** — `/archetypes/{slug}` now returns 404 for deleted archetypes, including for admin users.
- **Archetype deletion guard** — archetypes can only be deleted when they have zero associated decks. The admin edit page hides the delete button and the server rejects deletion attempts when decks exist. A deck count column was added to the admin archetype list.
- **Soft-delete test fix** — `testDeckDeleteBlockedByActiveBorrows` no longer skips; switched to admin user who has decks with active borrows in fixtures.

### Documentation

- **Archetype soft-delete rules** — documented in `docs/models/deck.md`: `deletedAt` field, deletion guard constraint, and visibility rules.
- **Error pages technical reference** — `docs/technicalities/error_pages.md` covers request type handling, sprite mapping, template architecture, CDN integration, and Sentry behavior.

### Testing & Quality

- 17 new functional tests covering `CdnErrorController`, `TestErrorController`, and `ExceptionListener` (XHR/JSON, non-HTML, dev HTML, sprites per code).

---

## [1.0.6] — 2026-03-26

My Decks filter, retired deck visibility fix, mobile card gallery restoration, and translation cleanup.

### Features

- **My Decks filter** — added a "My Decks" shortcut button on the deck catalog page that filters to the current user's decks, including private and retired ones. Retired decks display a "Retired" badge in the card grid.
- **Mobile card image gallery** — restored the swipeable card image modal on mobile. Tapping a card name opens a Mantine modal with the card image, quantity, position counter (e.g. "3 / 28"), prev/next chevrons, touch swipe navigation, and keyboard arrow support with cycling.

### Bug Fixes

- **Retired decks visible in owner's catalog** — the deck catalog query now skips the retired-status filter when the owner views their own decks (`selfOwner`), so retired decks are no longer hidden.

### Refactoring

- **Translation deduplication** — consolidated 52 duplicate translation keys into shared `app.common.*` keys across both EN and FR XLIFF files. Removed 4 dead/unused keys. Net reduction of ~364 lines.
- **Removed dead Bootstrap card modal** — replaced the unused Bootstrap card image modal in the deck show template with the React/Mantine implementation.

---

## [1.0.5] — 2026-03-26

Soft deletion for core entities — archetypes, pages, events, and decks can now be soft-deleted and restored from the admin interface.

### Features

- **Soft deletion for archetypes, pages, events, and decks** — added `deletedAt` column and soft-delete/restore actions in admin controllers. Soft-deleted entities are excluded from public queries by default and can be restored by administrators.

### Testing & Quality

- 12+ functional tests covering soft deletion and restoration for all four entity types, including repository filtering and controller actions.

---

## [1.0.4] — 2026-03-26

Self-service organizer role — any user can activate the organizer role from their profile.

### Features

- **Self-service organizer role toggle** — new "I want to organize events" checkbox on the profile page. Any user can activate `ROLE_ORGANIZER` to create and manage events. Deactivation is blocked while the user has active (not finished or cancelled) events. Admins see the checkbox checked and disabled (role hierarchy grants organizer privileges automatically). Security token is refreshed after role change to avoid session invalidation.

### Documentation

- API access specs: event ID resolution, scope-role intersection model, userId/playerId attendee identification (Phase K milestone).

### Testing & Quality

- 9 functional tests for organizer role toggle: checkbox state per role/context, role activation/deactivation, locked enforcement, session persistence, `EventRepository::hasActiveEventsAsOrganizer()` query.

---

## [1.0.3] — 2026-03-25

Security fix — prevent recursive `_target_path` redirect loop caused by crawlers.

### Bug Fixes

- **Prevent recursive `_target_path` redirect loop** — bots bouncing between `/login` and `/register` were nesting the `_target_path` query parameter infinitely (~400k useless requests in 7 days). Fixed by using `pathInfo` instead of `requestUri` in nav links and adding a `containsNestedTargetPath()` guard that fully URL-decodes all percent-encoding levels before rejecting recursive targets.

### Testing & Quality

- Added 4 functional tests covering recursive target path rejection (single-encoded, deeply-encoded, logged-in redirect scenarios).

---

## [1.0.2] — 2026-03-24

Dashboard cleanup — remove global stats section for organizer view.

### Refactoring

- **Remove global stats from admin dashboard** — removed the "Global overview" row (total decks, active borrows, upcoming events, overdue returns) from the organizer dashboard. The per-user "My Events" stats section is preserved.

### Testing & Quality

- Updated `DashboardStatsTest` to reflect the removal of global stats (removed 3 tests, updated assertions).

---

## [1.0.1] — 2026-03-24

Custom Pokemon sprites on decks — deck owners can now set per-deck sprite overrides via an autocomplete selector, with archetype fallback.

### Features

- **F2.22 — Custom Pokemon sprites on decks** — new `pokemonSlugs` JSON property on `Deck` with a Mantine-based autocomplete multi-item selector showing all ~1478 PokéSprite slugs with image previews. Deck sprites take priority over archetype sprites everywhere decks are displayed. Sprites are copied to the archetype if it has none. The same React component replaces the vanilla JS comma-separated text input on archetype admin forms.
- **Auto-publish archetype** — when saving a public deck linked to an unpublished archetype, the archetype is automatically published.
- **`deck_sprites()` Twig function** — renders effective sprites (deck-level → archetype fallback), replacing 19 template call sites that previously used `archetype_sprites()` with a null-check guard.

### Infrastructure

- Build-time sprite manifest (`pokemon-sprites.json`) generated by webpack from PokéSprite PNGs.
- TypeScript module declaration for the generated manifest to support CI type-checking before build.

---

## [1.0.0] — 2026-03-23

First stable release — graduates from beta after 13 beta iterations. Includes all features from the beta series plus comprehensive test coverage improvements and release process hardening.

### Testing & Quality

- 133 new tests (unit + functional) covering CardEnricher, CardIdentityResolver, TcgdexApiClient, BannedCardsSyncService, RarityTierMapper, OriginalListFormatter, MinifiedCardView, MinifiedCardViewBuilder, MinifiedListGenerator, GenerateMinifiedListHandler, GenerateMinifiedMosaicHandler, BuildSetMappingsHandler, EnrichmentFlushService, EnrichRetryCommand, and 5 previously untested controllers (Health, AdminTechnical, AdminPage, AdminMenuCategory, Page).

### Documentation

- Release process: added critical back-merge verification step to prevent develop/main divergence.

---

## [1.0.0-beta.13] — 2026-03-23

Thirteenth beta — pre-computed deck card views, enrichment pipeline chaining, CI OOM fix, PTCGO promo code support, and comprehensive unit test additions.

### Features

- **Pre-computed minified card views** — new `minifiedCardViews` JSON column on `DeckVersion`, populated during async enrichment. Deck show page and Cardmarket wishlist formatter read pre-built JSON instead of computing at request time, eliminating all TCGdex API calls and per-card DB queries from the request path.

### Bug Fixes

- **Eliminated 36+ synchronous TCGdex API calls** from deck show page — removed `expandPrintings()` from `MinifiedCardViewBuilder` and auto-dispatch of `BuildSetMappingsMessage` from `DeckShowController`.
- **Chained enrichment pipeline** — `GenerateMinifiedMosaicMessage` is now dispatched by `GenerateMinifiedListHandler` after `CardPrinting` rows are populated, preventing race condition where minified mosaics rendered with missing images.
- **PTCGO short promo codes** — added `SMP`, `SWP`, `SVP`, `XYP`, `BWP` as static overrides. Cards pasted from old PTCGO client (e.g. "Trevenant & Dusknoir-GX SMP 217") now resolve correctly during enrichment.
- **`EnrichmentFlushService`** — added `minified_card_views = NULL` to flush SQL.
- **CI OOM** — wired `BuildSetMappingsHandler` to mock HTTP client in test env, increased memory limit to 768M, added `tearDown` cleanup.

### Administration

- **TCGdex Set Mappings** — set mappings now persisted in MySQL (`TcgdexSetMapping` entity) instead of APCu cache. Rebuild via admin dashboard button only (no auto-dispatch). Fixtures seed 162 mappings for dev/test.

### Testing & Quality

- 48 new unit tests (523 → 571): `RarityTierMapperTest` (24), `OriginalListFormatterTest` (7), `MinifiedCardViewTest` (9), `GenerateMinifiedListHandlerTest` (5), `BuildSetMappingsHandlerTest` (3).

### Documentation

- Updated `enrichment.md`, `mosaic.md`, `cardmarket_export.md`, `deck.md`, `CLAUDE.md` for pipeline chaining, pre-computed card views, DB-based set mappings, and PTCGO promo codes.

---
## [1.0.0-beta.12] — 2026-03-23

Twelfth beta — persistent TCGdex set mappings in MySQL replacing APCu cache, async build via Messenger, admin rebuild button, and Supervisor worker tuning.

### Bug Fixes

- **EXPANDEDDECKS-J** — Fixed production timeout on `/deck/{short_tag}` where `buildReverseSetMapping()` fired 100+ concurrent HTTP requests to TCGdex during an APCu cache miss, exceeding PHP's 30s `max_execution_time`. Set mappings are now persistent in MySQL, built asynchronously via a Messenger worker, and only wiped by explicit admin action.

### Administration

- **TCGdex Set Mappings card** on the technical dashboard: shows current mapping count (or "empty" badge) and a rebuild button that wipes the table and re-dispatches the async build.

### Infrastructure

- New `TcgdexSetMapping` Doctrine entity and repository (`tcgdex_set_mapping` table).
- `BuildSetMappingsMessage` / `BuildSetMappingsHandler` on the `deck_enrichment` transport.
- Scoped HTTP client `tcgdex.client` with base URI and 10s timeout.
- Added `--sleep=20` to all four Supervisor Messenger worker commands to reduce idle CPU usage.

### Testing & Quality

- Updated `TcgdexApiClientTest` and `TcgdexApiClientCoverageTest` for repository-based set mappings — replaced API-mocking helpers with repository stubs.

---

## [1.0.0-beta.11] — 2026-03-23

Eleventh beta — Cardmarket wishlist export rework (ability/attack-based format), pending state placeholders for async deck views, "My Decks" shortcut, and flush & re-enrich admin action.

### Deck Library

- **F2.19** — Pending state for async deck views: show generating placeholders (spinner + message) when mosaic or minified data is not yet available, instead of silently hiding UI. Variant and view mode toggles are always visible.
- **F2.20** — My Decks shortcut in user menu: "My Decks" link in the user dropdown (between Dashboard and Profile), pointing to the deck catalog filtered by the current user.
- **Lock icon for non-public decks**: deck cards in the catalog show a `bi-lock-fill` icon when the deck is not public (visible to owner only).
- **F6.11** — Cardmarket wishlist export rework: Cardmarket identifies cards by name + abilities + attacks (not set codes). Format changed to `{qty}x {name} {abilities} {attacks}` for Pokemon and `{qty}x {name}` for Trainer/Energy. Added `CARDMARKET_NAME_OVERRIDES` for ambiguous cards (e.g. Professor's Research → Professor's Research - Professor Sada).
- **F6.10** — Card identity model extended: `abilitySignature` (sorted, for dedup) and `abilityNames`/`attackNames` (original card order) added to `CardIdentity`. TCGdex enrichment now parses abilities from the API.

### Administration

- **Flush & Re-enrich All**: new combined action on the technical dashboard — flushes all enrichment data and re-dispatches enrichment for every deck version in one step. Replaces the standalone flush button.

### Bug Fixes

- **F4.11** — Handle race condition in `expandPrintings` when multiple workers process the same card identity concurrently.

### Documentation

- New `docs/technicalities/cardmarket_export.md` deep-dive: format rules, data flow, name overrides, known limitations.
- **F2.21** — Draft flag for decks documented in features.md (backlog, no milestone).
- Migrated roadmap tracking to GitHub Project board.
- Added Awaiting Validation and Ready for Release columns to project tracking workflow.

### Testing & Quality

- `CardIdentityResolverTest` — 7 tests for ability/attack signature computation.
- `TcgdexApiClientTest` — 3 new tests for abilities/attacks parsing from API responses.

---

## [1.0.0-beta.10] — 2026-03-21

Tenth beta — optional section headers in deck list import, basic energy image improvements, smarter minified export printing selection, marketplace IDs, and test infrastructure hardening.

### Deck Library

- **F6.1** — Optional section headers in deck list import: `Pokémon:`, `Trainer:`, `Energy:` headers are now optional. Cards without headers get `unknown` type, resolved during TCGdex enrichment. Basic energies detected by name at parse time.
- **Minified export — basic energies**: always use MEE (Mega Evolution Energy) for the 8 standard types and SUM (Sun & Moon) for Fairy. Static defaults from `DEFAULT_BASIC_ENERGY_PRINTINGS`, no DB query needed.
- **Minified export — two-pass printing selection**: tier 1–3 (Common/Uncommon/Rare) sorted by date DESC then price; tier 4+ sorted by price ASC then date. Trainer Gallery (TG) and Galarian Gallery (GG) cards excluded from passes 1–2.
- **Minified export — rarity tier bump**: cards beyond the set's official card count or with TG/GG prefix are bumped to tier 5 during enrichment, even when TCGdex reports them as "Rare" or "Ultra Rare".
- **Energy-set image resolution**: SVE and MEE cards resolved via static `ENERGY_SET_IMAGES` map with exact images from pokemon.com CDN. Card numbers normalized (SVE 4 / SVE 04 / SVE 004 all match).
- **Card number letter suffix**: exact card number tried before stripping letter suffixes (fixes GEN 28a Jolteon-EX resolving to full art g1-28).
- **PokemonTCG.io image fallback**: when TCGdex has no image for a card, build a PokemonTCG.io CDN URL from the tcgdex ID as first fallback.
- **Static override mechanism**: `IMAGE_OVERRIDES` in CardEnricher and `MINIFIED_PRINTING_OVERRIDES` in DeckListParser for known TCGdex data issues (GEN 73 Team Flare Grunt).
- **Marketplace IDs**: `cardmarketProductId` and `tcgplayerProductId` added to `CardPrinting` entity (+ migration), populated from TCGdex pricing data during enrichment.
- **Original list export**: new `OriginalListFormatter` generates proper PTCGL text with section headers and trainer subtype ordering.
- **Minified list export**: includes PTCGL section headers (`Pokémon:`, `Trainer:`, `Energy:`) and `Total Cards:` footer.
- **Original card table**: trainers sorted by subtype (supporter → item → tool → stadium).
- Centralized `BASIC_ENERGY_NAMES` in `DeckListParser` (removed 5 duplicate lists).

### Bug Fixes

- Basic energy validator checks by name only (supports headerless imports).
- Enrichment fallback images updated from old BW1 TCGdex URLs to MEE (pokemon.com) and pokemontcg.io (Fairy).
- `findSimplestBasicEnergyByName()` picks Common rarity + most recent release instead of first TCGdex result.

### Data & Documentation

- `data/basic_energies.json` — 194 known basic energy printings with multi-source image URLs and minified defaults.
- `docs/technicalities/basic_energy_images.md` — CDN research (pokemon.com, pokemontcg.io, TCGdex).
- `docs/technicalities/tcgdex_known_issues.md` — known data quality issues and workarounds.
- PHPUnit `createStub` vs `createMock` guidance added to CLAUDE.md.
- Updated features.md, models/deck.md, enrichment.md, docs.md.

### Testing & Quality

- `TcgdexMockHttpClient` replaces live TCGdex API calls in functional tests — eliminates flaky CI failures from API timeouts, ~30s faster test suite.
- Fixed 6 PHPUnit 13 notices (`createMock` → `createStub` where no expectations configured).
- Mock set mapping expanded to 45 sets covering all fixture data.

### Infrastructure

- PDF label: foldable layout with deck list on back, short tag routes, trainer subtype grouping.
- GitHub link added to footer.

---

## [1.0.0-beta.9] — 2026-03-19

Ninth beta — PDF label cards for home printing, GitHub link in footer.

### Labels & Printing

- **F5.7** — PDF label card (home printing) *(completed)*: generate downloadable PDFs with TCG card-sized labels (63.5 × 88.9 mm). Two variants: **(1) Simple label** on A4 portrait — deck name, archetype sprites (12mm, base64-embedded), QR code (18mm, linking to the deck page via `DEFAULT_URI`), short tag, owner identity (screen name + full name), and base URL. **(2) Foldable label** on A4 landscape (book layout) — left panel shows a compact deck list grouped by detailed type (pokemon/supporter/item/tool/stadium/energy) with alternating gray shades and dynamic font size (4–7pt computed from card count); right panel shows the same label. Fold along the center for a double-sided sleeve insert. Routes: `GET /deck/{short_tag}/label.pdf` and `GET /deck/{short_tag}/label-foldable.pdf` (owner-only). Uses Dompdf + endroid/qr-code v6. Content-box dimension workaround for Dompdf (no `border-box` support). Crop marks with full-width horizontal guides. Trainer cards split by subtype with `strtolower()` normalization.

### Infrastructure

- GitHub repository link added to the page footer.
- Version number in footer no longer uses reduced opacity (visible at smaller font size only).

---

## [1.0.0-beta.8] — 2026-03-19

Eighth beta — deck selection borrow conflict guards, PHP memory limit for mosaics, CI workflow improvements.

### Borrow Workflow

- **F3.7 / F4.11** — Deck selection borrow conflict guards *(completed)*: owner cannot select their own deck for an event when an approved/lent/overdue borrow exists (hard block with "Reserved" badge). Selecting a deck with pending borrow requests triggers a confirmation dialog; confirming cancels all pending requests via `BorrowService::cancel()`. New `BorrowRepository::findAllPendingBorrowsForDeckAtEvent()` query. Hardcoded UI strings replaced with proper translation keys (en/fr).

### Infrastructure

- PHP memory limit raised to 512M in Docker for mosaic generation.
- `/pr` workflow auto-creates feature branch from `develop` when invoked on the `develop` branch.

---

## [1.0.0-beta.7] — 2026-03-19

Seventh beta — card identity model, minified export/mosaic, enrichment edge cases, and React island refactor.

### Deck Library

- **F6.10** — Card identity and printing model *(completed)*: `CardIdentity` entity groups all printings of the same functional card (by name+HP+attacks for Pokemon, by name for Trainers/Energy). `CardPrinting` stores per-set printing with rarity tier (1–7), Cardmarket avg price in cents, set release date. `CardIdentityResolver` creates identities during enrichment and lazily expands all printings from TCGdex. `RarityTierMapper` maps TCGdex rarity strings to 7-tier system with blacklisted sets (Hidden Fates Shiny Vault, promos, trainer kits, McDonald's).
- **F6.8** — Minified deck list export *(completed)*: `MinifiedListGenerator` selects the lowest-rarity Expanded-era printing of each card, with price as tiebreaker. Basic energies use the latest printing. Duplicate entries merging when multiple cards resolve to the same printing. Stored on `DeckVersion.minifiedList`.
- **F6.6b** — Minified mosaic *(completed)*: second mosaic variant using lowest-rarity card images with merged tiles. `MosaicTile` DTO and `MosaicGenerator.generateFromTiles()` for clean separation. Stored on `DeckVersion.minifiedMosaicImageUrl`.
- **F6.9** — Improved energy card enrichment *(completed)*: detect basic energies by name regardless of set code (covers SVI, SVE, etc.). Three-step lookup: set+number → name search → static fallback. Excluded from name-match warning.
- **Deck detail React island** — replaced 209 lines of vanilla JS DOM manipulation with a `DeckCardList` Mantine component. Global Original/Minified toggle controls table, mosaic, and copy simultaneously. Table/Mosaic toggle: desktop inline swap, mobile table default with fullscreen mosaic modal. Single copy button copies the active variant. Share mosaic button (Web Share API on mobile, clipboard fallback).
- **Mosaic URLs** — changed from `/mosaic/{deckId}/...` to `/mosaic/{shortTag}/...` for human-readable, shareable URLs.
- **Shadow Rider Calyrex** fixture added with JP/TG/letter-suffix edge case cards.

### Bug Fixes

- **Trainer Gallery** (`ASR-TG 30`) — strip `-TG` suffix from set codes, prepend `TG` to card number.
- **Letter suffixes** (`FLI 113a`) — strip trailing letters from card numbers before lookup.
- **Japanese set codes** (`S6K`, `SM8`) — name-based fallback with full CardIdentity/CardPrinting linking for minified resolution.
- **TCGdex name search** — filter to exact name matches only (TCGdex `/cards?name=` is a contains match).
- **Reverse set mapping** — prefer `tcgOnline` codes (`NXD`) over `abbreviation.official` (`NEX`) for PTCGL/Limitless compatibility.
- **Rarity data** — unknown/unmapped rarities default to tier 7 (rarest); blacklisted sets always return tier 7.
- **Basic energy warning** — excluded from the "matched by name only" warning banner.

### Administration

- **Flush enrichment** — danger-zone button in technical admin to reset all enrichment data (card images, identities, printings, mosaics, minified lists). Double confirmation (JS confirm + CSRF).

### Documentation

- **`docs/technicalities/enrichment.md`** — comprehensive technical deep-dive: enrichment pipeline, TCGdex API (set mapping, card lookup, edge cases), card identity model, rarity tiers, minified export, energy handling, admin tools, known limitations.
- Updated mosaic doc with shortTag URLs and minified pipeline diagram.
- F6.6b, F6.8, F6.9, F6.10 marked Done. Phase A: 7/12 done. Total: 90 done / 27 remaining.

### Refactoring

- Deck card list display refactored from Twig + vanilla JS to React/Mantine island (`DeckCardList` component).
- `MosaicUrlResolver.resolve()` replaced by `resolveForVersion(DeckVersion, variant)`.
- `TcgdexApiClient`: `parseCardData()` extracted, `fetchCardById()`, `findAllPrintingsByName()`, `getReverseSetMapping()`, `buildReverseSetMapping()` added.
- `TcgdexCard` DTO extended with `hp`, `attacks`, `rarity`, `setReleaseDate`, `setCode`, `cardNumber`, `priceInCents`.

---

## [1.0.0-beta.6] — 2026-03-18

Sixth beta — deck mosaic image generation, copy-to-clipboard deck export, and production installation guide.

### Deck Library

- **F6.6** — Visual deck list (card mosaic) *(completed)*: server-generated composite image of the full deck list using PHP GD. Cards arranged in an 8-column grid on the site's Fairy energy background texture, with red hexagonal quantity badges (with shadow). Card order follows Pokemon community convention: Pokemon → Trainer (supporter, item, tool, stadium) → Energy. Async generation via `deck_enrichment` Messenger transport after card enrichment completes. Images stored via Flysystem (local in dev, Scaleway S3 in production). Served via `MosaicController` with 30-day immutable cache headers. Deck detail page includes a table/mosaic view toggle with localStorage persistence. Deck catalog shows mosaic as a desktop hover overlay on deck cards.
- **F6.7** — Export deck list as PTCGL text *(completed)*: "Copy list" button on the deck detail page copies the raw PTCGL text to clipboard with visual feedback.

### Infrastructure

- **GD extension** added to the production Dockerfile (`install-php-extensions gd`).
- **Flysystem** — `league/flysystem` and `league/flysystem-aws-s3-v3` installed for mosaic image storage. `MosaicStorageFactory` selects local or S3 adapter based on `MOSAIC_STORAGE_ADAPTER` env var.
- **Mosaic storage env vars** — `MOSAIC_STORAGE_ADAPTER`, `MOSAIC_STORAGE_LOCAL_DIR`, `SCALEWAY_S3_*`, `MOSAIC_PUBLIC_URL`.
- **CLAUDE.md** — added cache clear requirement (`symfony console c:c`) after every code modification.

### Documentation

- **Production installation guide** (`docs/installation.md`) — full reference of all 26+ env vars, Docker image build, worker setup, health checks, and minimal `docker run` example.
- **Mosaic technical deep-dive** (`docs/technicalities/mosaic.md`) — generation pipeline, GD rendering, Flysystem storage, file naming, dependencies.
- **Feature status** — added Status column to all feature tables in `docs/features.md` (86 Done, 28 remaining).
- **Roadmap** — marked F6.6, F6.7 as done; added Phase H (Export & Recovery) with F6.8 (optimized export) and F4.16 (lost & found deck alert).

### Administration

- **Mosaic generation admin card** — technical admin dashboard shows count of enriched deck versions missing a mosaic image, with a "Generate all" action button that dispatches `GenerateDeckMosaicMessage` for each.

### Testing & Quality

- 19 new unit tests covering `MosaicGenerator`, `GenerateDeckMosaicHandler`, `MosaicController`, `MosaicStorageFactory`, `MosaicRedispatchService`, and `MosaicUrlResolver`.
- Fixtures updated: `rawList` added to Iron Thorns v2/v3 and Regidrago v2.

---

## [1.0.0-beta.5] — 2026-03-18

Fifth beta — archetype localization and Sentry observability tuning.

### Deck Library

- **F9.6** — Archetype localization *(completed)*: archetype names and descriptions are now translatable via `ArchetypeTranslation` entities. Admin edit form supports per-locale translations. Archetype display adapts to the user's active locale across catalog, detail, and deck views.

### Infrastructure

- **Sentry logs action level** — `SENTRY_LOGS_ACTION_LEVEL` env var makes the Sentry logs `fingers_crossed` handler threshold configurable (default: `error`). Lowering to `info` sends all logs to Sentry even without an error trigger.
- **`/release-create` slash command** — added Claude Code skill for automated release branch, changelog, and PR creation.

---

## [1.0.0-beta.4] — 2026-03-17

Fourth beta — Sentry noise reduction and favicon redirect.

### Bug Fixes

- **Sentry AccessDeniedException filter** — `BeforeSendCallback` now drops `Symfony\Component\Security\Core\Exception\AccessDeniedException`, which bypassed the existing `HttpExceptionInterface` 4xx filter because it is thrown before the kernel converts it to a 403.
- **Favicon redirect** — added a 301 redirect from `/favicon.ico` to `/favicon.svg` to eliminate 404 noise from browsers and bots requesting the default favicon path.
- **Favicon route fix** — removed ambiguous empty `route` default that caused a `RuntimeException` in `RedirectController`.

### Testing & Quality

- Unit test for `AccessDeniedException` filtering in `BeforeSendCallback`.

---

## [1.0.0-beta.3] — 2026-03-17

Third beta — production observability improvements and version tracking.

### Infrastructure

- **Sentry 4xx suppression** — `BeforeSendCallback` drops all HTTP 4xx exceptions from Sentry issues. Monolog `excluded_http_codes` expanded to cover all common 4xx codes (400, 401, 403, 404, 405, 409, 410, 422, 429). Sentry structured logs (`sentry_logs`) wrapped in `fingers_crossed` handlers (buffering from info, triggering on error) with the same 4xx exclusions.
- **Sentry structured logs** — enabled via `enable_logs: true` in sentry-symfony config.
- **Sentry smoke-test routes** — `/health/sentry-logs` and `/health/sentry-error` for manual verification of Sentry integration.
- **Custom error page** — branded error template for 403, 404, and 500 responses.
- **Static favicon** — gray Fairy-type energy SVG at `public/favicon.svg`, eliminating 404 noise from browser requests.
- **APP_VERSION env var** — set at Docker build time via `--build-arg APP_VERSION=$(git describe --tags --always)`. Used as Sentry `release` and displayed in the footer.

### Documentation

- Full documentation consistency audit (Symfony/React version references, feature IDs).

### Testing & Quality

- Unit tests for `BeforeSendCallback` (4xx drop, 5xx keep, null hint edge cases).
- Banned cards sync service extracted and tested (`BannedCardsSyncService`).
- Test quality: replaced mocks with stubs where no expectations are set.

---

## [1.0.0-beta.2] — 2026-03-16

Second beta — deployment hardening, production observability, and infrastructure improvements. Sentry integration, Doctrine-based async messaging, APCu caching, technical admin dashboard, and container fixes.

### Infrastructure

- **F14.1–F14.6** — Deployment readiness features *(completed)*: per-transport Messenger DSN configuration, configurable session storage (database-backed by default), health check endpoints (liveness + readiness), production multi-stage Dockerfile with FrankenPHP, configurable mail sender and admin email. Interactive `app:create-admin` console command for initial setup.
- **F14.7** — Sentry error tracking *(new)*: `sentry/sentry-symfony` integration for production error tracking. `SENTRY_DSN` env var controls the connection (empty = disabled). Captures unhandled exceptions, Messenger worker errors, and Monolog error-level logs. Performance tracing configurable via `SENTRY_TRACES_SAMPLE_RATE` (default: 0). Disabled in dev/test.
- Switched async messaging from SQS webhook to Doctrine transport + cron job — eliminates external queue dependency.
- APCu cache adapter in production for in-process caching with FrankenPHP workers.
- Trusted `X-Forwarded-Host` header from CDN proxy.
- Multiple Dockerfile fixes for serverless container deployment.
- Database-backed sessions in all environments for horizontal scaling.

### Admin

- Technical admin dashboard with enrichment and banned cards sync actions (accessible to `ROLE_TECHNICAL_ADMIN`).

### Testing & Quality

- Coverage improvements for `BorrowNotificationEmailService` and `CreateAdminCommand`.
- On-demand coverage workflow, `/cover-pr` and `/cover-more` slash commands via Codecov.
- Integration test proving webhook cannot re-dispatch async messages.

### Documentation

- F9.6 archetype localization feature and content pages documented.
- F6.5-fix added to roadmap for banned cards sync refactor.
- `/ci`, `/pr`, `/next` slash commands added to tooling.

### Cross-Cutting

- 82 features documented (81 done + F14.7 new)
- PHPStan level 10, full CI pipeline

---

## [1.0.0-beta.1] — 2026-03-12

First beta of the 1.0.0 release — Phase 9 completion. Archetype ecosystem fully built out, dashboard action reminders, deck activity pagination, version history, and retire/reactivate workflow.

### Deck Library

- **F2.6** — Deck archetype management *(completed)*: full admin CRUD for archetypes with name, slug, published flag, description (Markdown), Pokémon slugs for sprites, and playstyle tags. Dedicated `ROLE_ARCHETYPE_EDITOR` role.
- **F2.7** — Retire / reactivate a deck *(completed)*: deck owners can retire a deck (auto-cancels pending borrows with warning dialog) and reactivate it later.
- **F2.9** — Deck version history *(completed)*: view all deck versions with side-by-side card comparison, card image hover, and enriched fixtures.
- **F2.10** — Archetype detail page *(completed)*: public page with Markdown description, custom tags (deck links, card images), cached rendering, and latest decks list.
- **F2.11** — Archetype backlinking *(completed)*: decks link to their archetype detail page across all views.
- **F2.12** — Archetype sprite pictograms *(completed)*: Pokémon box sprites displayed next to deck names across the entire UI (catalog, detail, dashboard, events, borrows, results). Build-time sprite download via PokéSprite fork.
- **F2.15** — Archetype playstyle tags *(completed)*: free-text tags on archetypes (e.g. "Aggro", "Control", "Toolbox") managed via Mantine TagsInput in admin form.
- **F2.16** — Archetype catalog *(completed)*: public browse page with card grid, multi-select tag filtering (OR logic), sort by name or deck count, sprites, and tag badges.
- **F2.17** — Deck catalog archetype filter UX *(completed)*: replaced text search with a searchable sprite dropdown (Mantine Combobox) showing all published archetypes with their sprites.
- **F2.18** — Admin archetype create/edit form *(completed)*: dedicated admin form for creating and editing archetypes with all fields.
- **F5.12** — Deck show activity pagination *(completed)*: deck detail page shows only the 5 most recent activity entries with a "See more" link.

### Dashboard & UX

- **F7.4** — Dashboard action reminders *(completed)*: warning widget showing borrows to return, pending requests to review, and events needing deck selection. Action links scroll to relevant page sections via anchors.

### Documentation

- Roadmap restructured: completed features removed from phase tables, 28 remaining features organized into 7 logical phases (A–G) with PDF labels before Zebra labels.

### Cross-Cutting

- Phase 9 progress: 17/34 done, 17 remaining
- All archetype features complete (F2.6, F2.10, F2.11, F2.12, F2.15, F2.16, F2.17, F2.18)
- 75 total features implemented

## [0.8.0] — 2026-03-10

Quality & i18n release — comprehensive test coverage, lint tooling, translation of all remaining controller/form strings, and dead code removal.

### Internationalization

- **F9.3** — Application translation *(completed)*: translate all remaining controller flash messages and form labels to use translation keys. Introduce `AbstractAppController` base class with `addTranslatedFlash()` helper to enforce translated flash messages project-wide.

### Quality & Testing

- **Test coverage**: 83.6% → 92.24% line coverage. Added 50+ test files covering entities, repositories, controllers, services, event listeners, message handlers, Twig runtimes, and security components. All repositories, services, and listeners at 100% line coverage.
- **Dead code removal**: removed unreachable unpublish guard in `DeckController` (form-disabled field already prevents the branch). Cleaned up orphaned translation keys.

### Tooling

- **Lint tooling**: added `make lint-all` target orchestrating all linters and fixers in dependency order: `lint-yaml` → `lint-i18n` → `cs-fix` → `eslint-fix` → `stylelint-fix` → `lint-container` → `phpstan`.
- New Make targets: `lint-yaml`, `lint-i18n` (XLIFF syntax + translation content), `lint-container`, `stylelint`, `stylelint-fix`, `eslint-fix`.
- Installed `stylelint` + `stylelint-config-standard-scss` for SCSS linting.
- Updated CLAUDE.md pre-commit checklist with all new lint targets.

## [0.7.0] — 2026-03-09

Phase 8 completion — Admin, Homepage & Polish: banned card list, mobile UX responsiveness pass with swipeable card gallery, localized validation messages, coding standards documentation.

### Card Data & Validation

- **F6.5** — Banned card list management *(completed)*: CLI command `app:banned-cards:sync` fetches the official Pokemon TCG banned card list from pokemon.com and syncs it to the database (add/remove/unchanged). `DeckListValidator` now checks imported deck lists against the banned card list. Sync runs automatically at the end of `make fixtures`. Cards identified by setCode + cardNumber for deduplication.

### Mobile UX

- **F10.1** — Mobile UX review *(completed)*: comprehensive mobile responsiveness pass. Borrow tables (inbox, dashboard, deck show) use card-based layout on mobile (`< md`) instead of horizontal-scroll tables. Deck catalog filters collapse behind a toggle on mobile. Card hover images replaced with tap-to-show swipeable modal on touch devices (prev/next buttons, touch swipe, keyboard arrows, quantity in title). Background scroll blocked while modal is open on iOS. Action buttons meet 44px touch target. Event info tables converted to definition lists. Notification bell redirects to notification list on mobile. Navbar items right-aligned on mobile. Dashboard stat cards stack vertically on smallest screens.

### Deck Library

- Swipeable card image gallery: mobile modal navigates through all deck card images in list order with touch swipe, prev/next buttons, and keyboard arrow support. Modal title shows "qty x card name".

### Internationalization

- Deck list parser and validator error messages are now localized via translation keys instead of hardcoded English strings.

### Documentation

- Expanded Make commands reference in CLAUDE.md with all targets, descriptions, and usage guidance.
- Added JavaScript/TypeScript coding conventions, naming rules, and additional PHP rules to coding standards.
- Mobile UX audit document (`docs/technicalities/mobile_audit.md`).

### Cross-Cutting

- 509 tests, 2154 assertions, PHPStan level 10
- Phase 8 fully complete (6/6 features done)

---

## [0.6.0] — 2026-03-09

Phase 8 progress — Admin, Homepage & Polish: admin user management, GDPR account deletion & data export, in-app notification center, dashboard enhancements. Major framework upgrade: Symfony 7.2 → 8.0, React 18 → 19.

### Admin & User Management

- **F7.2** — User management *(completed)*: admin user list with search and pagination, user detail page with role assignment (ROLE_ADMIN, ROLE_ORGANIZER, ROLE_CMS_EDITOR, ROLE_ARCHETYPE_EDITOR), disable/enable toggle, and account anonymization.
- **F1.8** — Account deletion & data export *(completed)*: users can export their data as JSON (profile, decks with raw lists, borrows, engagements, staff assignments) and request account deletion with email confirmation (24h token). Deletion is blocked if the user has unsettled borrows. Confirmation anonymizes the account (email stored as bcrypt hash for traceability), disables login, and logs the user out. Centralized `User::anonymize()` method shared by admin and self-service flows.

### Notifications

- **F8.4** — In-app notification center *(completed)*: React-based notification bell with unread count badge, polling, mark-as-read, and mark-all-read. Dropdown menu with notification list and timestamps.

### Dashboard

- **F7.1** — Dashboard enhancements: admin overview stats banner (total users, decks, events, active borrows), personal event stats for organizers and staff, stat card links to scoped list pages.

### Borrow Workflow

- Managed borrows inbox (`/lends?scope=managed`): cancel button now shown when the logged-in user is the borrower. Hand-off button hidden when deck is delegated to staff but staff hasn't physically received it (shows "Awaiting custody" badge). Scope preserved on redirect after actions.

### Infrastructure

- **Symfony 7.2 → 8.0**: full major upgrade via 7.4 bridge. Fixes: `UserCheckerInterface::checkPostAuth()` signature, route config `.xml` → `.php`, auto-generated `reference.php` excluded from CS-Fixer.
- **React 18 → 19**: updated `act()` wrapping in async tests, `eslint-plugin-react-hooks` 5 → 7 with new `set-state-in-effect` rule.
- **Dependency updates**: Mantine 8.3.16, webpack-cli 6, globals 17, regenerator-runtime 0.14, phpstan-symfony 2.0.15.
- Constraint widened to `^8.0` for automatic minor Symfony upgrades.
- 507 tests, 2123 assertions, PHPStan level 10

---

## [0.5.0] — 2026-03-08

Phase 7 completion — Engagement, Results & Discovery: deck event status overview, tournament results with monospace short-ID badges, and Pokemon event page sync.

### Deck Library

- **F2.14** — Deck event status overview *(completed)*: deck detail page now shows a summary of the deck's participation across events — engagement state, borrow status, and tournament placement at a glance.

### Event Management

- **F3.17** — Tournament results *(completed)*: dedicated results page with placement and match records. Player short-IDs displayed as monospace badges. First/last name shown alongside screen name.
- **F3.18** — Sync from Pokemon event page *(completed)*: import event metadata (name, date, location, structure) from a Pokemon event page URL. Maps tournament structures (League Challenge → swiss), handles unicode decoding, and includes functional test coverage.

### Cross-Cutting

- 460 tests, PHPStan level 10
- Phase 7 fully complete (10/10 features done)

---

## [0.4.0] — 2026-03-05

Phase 7 progress — Engagement, Results & Discovery: event lifecycle completion, visibility controls, engagement states, event discovery, event notifications with per-type user preferences.

### Event Management

- **F3.7** — Register played deck for event *(completed)*: tournament result fields (`placement`, `matchRecord`) on `EventDeckEntry`, with full CRUD on the event show page.
- **F3.10** — Cancel an event *(completed)*: cancellation action with async cascading borrow cancellation via Messenger (`CancelEventBorrowsMessage`). Pending and approved borrows are cancelled; lent/overdue borrows are preserved.
- **F3.11** — Event visibility: public, private, and invitation-only events. `EventVisibility` enum with `visibility` column on `Event`. Invitation-only events restrict player registration to invited users.
- **F3.13** — Player engagement states *(completed)*: full engagement lifecycle — interested, registered (playing/spectating), invited, withdrawn. Invitation-only flag, invite action for organizers, and preserved invitation status across mode switches.
- **F3.15** — Event discovery: public discovery page listing upcoming public events with search, available to all users (including unauthenticated).
- **F3.20** — Mark event as finished *(completed)*: finish action sets `finishedAt`, blocks new borrow requests and registrations for finished events.

### Notifications

- **F8.2** — Event notifications *(completed)*: email and in-app notifications for staff assignment, event updates, event cancellation, and user invitations. Templated emails with recipient locale support.
- **F8.3** — Notification preferences: per-type email/in-app settings on `/profile/notifications`. JSON column on `User` (null = all enabled, backwards-compatible). Checkbox table grouped by category (Borrow / Event) with column-toggle headers. All notification services (`BorrowService`, `BorrowNotificationEmailService`, `EventNotificationService`, `StaffCustodyService`) check user preferences before sending.

### Cross-Cutting

- Notification preferences link added to user dropdown menu
- EN + FR translations for all new features (~350 new translation keys)
- 425 tests, PHPStan level 10

---

## [0.3.0] — 2026-03-04

Phase 6 — Localization: multi-language support (en/fr), timezone-aware display, user profile page, and Gravatar navbar avatar.

### Localization

- **F9.1** — User language preference: Symfony locale listener detects user locale from session, user preference, or `Accept-Language` header. All UI strings render in the active locale.
- **F9.2** — User timezone display: `user_datetime` / `user_date` Twig filters convert UTC timestamps to the user's timezone with locale-aware formatting via `IntlDateFormatter`. Event dates display in the event's timezone with a tooltip showing the user's local time when different.
- **F9.3** — Application translation: all ~300 user-facing strings extracted to XLIFF catalogues (`messages.en.xlf`, `messages.fr.xlf`). Covers templates, controller flash messages, form labels, and email templates/subjects. Emails render in the recipient's preferred locale.
- **F9.4** — UTC datetime storage: event form uses `model_timezone` / `view_timezone` for automatic UTC conversion. PHP timezone set to UTC via `.symfony.local.yaml`.

### User Management

- **F1.3** — User profile page: edit screen name, player ID, preferred locale, and timezone. Locale changes apply immediately via `LocaleSwitcher`.
- **F1.11** — Gravatar avatar & navbar dropdown: 32px Gravatar avatar (64px Retina source) in the navbar with a Bootstrap dropdown menu (Dashboard, Profile, Logout).

### Cross-Cutting

- Abstract base controller (`AbstractAppController`) with auto-translating `addFlash()` for all controllers
- Bootstrap Icons and tooltip initialization for timezone display
- 363 tests, PHPStan level 10

---

## [0.2.0] — 2026-03-04

Borrow workflow maturity: staff custody chain, conflict management, owner inbox, and UI refinements.

### Deck Library

- **F2.13** — Inline deck list import on creation: owners can import a deck list directly during deck creation, removing the need for a separate import step.

### Event Management

- **F3.21** — Clear deck selection on withdrawal: when a participant withdraws from an event or switches participation mode, their deck selection is automatically cleared.
- **F4.13** — Event-scoped autocompletes: user search fields in staff assignment and borrow workflows are scoped to event participants for faster results.

### Borrow Workflow

- **F4.5** — Borrow history: paginated borrow and lend lists with full deck detail (was *(partial)* in 0.1.0).
- **F4.9** — Staff deck custody tracking: staff members assigned to an event can manage delegated decks on the event page. Delegated staff can also cancel borrows on behalf of the deck owner.
- **F4.10** — Owner borrow inbox: grouped-by-event layout with inline approve/deny/cancel actions (was *(partial)* in 0.1.0).
- **F4.11** — Multiple pending borrow requests: a deck can receive multiple pending borrow requests per event, allowing the owner to compare and choose. When a borrow is approved or a walk-up lend is created, all other pending borrows for the same deck at that event are automatically declined via `DeclineCompetingBorrowsMessage` (async `borrow_lifecycle` Messenger transport).
- **F4.14** — Staff custody handover tracking: owners confirm handing a delegated deck to staff; staff confirm returning it. Tracks `staffReceivedAt`/`staffReceivedBy` and `staffReturnedAt`/`staffReturnedBy` on `EventDeckRegistration`. Full chain-of-custody visibility: owner → staff → borrower → staff → owner. Guard conditions: staff cannot hand off or walk-up lend a delegated deck until the owner confirms physical handover; delegation cannot be revoked while the deck is with staff.
- **F4.14** — Custody return rules: staff cannot mark a deck as returned to owner while it is currently lent to a borrower (must collect it first). When staff returns the deck, remaining active borrows (returned, pending, approved) are auto-closed. New owner reclaim action: the owner can mark "returned to me" at any time, closing both the custody tracking and all active borrows (including lent/overdue) in one step. Borrowers with active lent/overdue borrows are notified.
- **F4.14** — Deck selection UI: own decks that are currently lent or handed over to staff are shown as disabled rows in the Deck Selection card (with "Lent" / "With staff" badges) instead of being hidden. A "Browse decks" link invites the owner to borrow an alternative.

### Dashboard & Homepage

- **F7.1** — Dashboard: "See all" link added to My Decks section.

### Cross-Cutting

- Transaction rollback for functional test isolation (performance improvement)
- PHPUnit test suite expanded (46+ test methods, 1 600+ assertions)

---

## [0.1.0] — 2026-03-03

First tagged release. Covers the core domain: authentication, deck library, event management, full borrow workflow with notifications, and card data pipeline.

### Auth & Foundation

- **F1.1** — User registration & authentication (email, screen name, player ID, target-path redirect)
- **F1.2** — Email verification (token-based activation link)
- **F1.4** — Role-based access control (admin, organizer, player, per-event staff)
- **F1.7** — Password reset (tokenized email flow)
- **F9.4** — UTC datetime storage

### Deck Library

- **F2.1** — Register a deck (name, archetype, format, auto-generated short tag)
- **F2.2** — Import deck list via copy-paste (PTCG text format, parsed & validated)
- **F2.3** — Deck detail view (card list, availability, card image hovers, public short-tag URL)
- **F2.4** — Deck catalog (browse, search, archetype/event/owner filters, paginated)
- **F2.5** — Deck availability status (available, lent, reserved, retired)
- **F2.8** — Update deck list (new version, preserves history)
- **F2.6** — Deck archetype management *(partial)* — name/slug catalogue with autocomplete; published descriptions, sprites, and editor role not yet implemented

### Event Management

- **F3.1** — Create an event (full form with tournament structure, entry fee, sync CTA placeholder)
- **F3.2** — Event listing (upcoming/past, publicly accessible)
- **F3.3** — Event detail view (tournament info, borrow requests, deck assignments)
- **F3.4** — Register participation (playing or spectating modes)
- **F3.5** — Assign event staff team (multi-field search with autocomplete)
- **F3.9** — Edit an event
- **F3.7** — Register played deck for event *(partial)* — `EventDeckEntry` creation works; placement and match record entry not yet implemented
- **F3.10** — Cancel an event *(partial)* — cancellation with cascading pre-handoff borrows; UI polish pending
- **F3.20** — Mark event as finished *(partial)* — sets `finishedAt`; overdue triggers not yet wired

### Borrow Workflow

- **F4.1** — Request to borrow a deck for an event
- **F4.2** — Approve / deny borrow request
- **F4.3** — Confirm deck hand-off (lend) — manual owner/staff confirmation
- **F4.4** — Confirm deck return
- **F4.7** — Cancel a borrow request (borrower or owner)
- **F4.8** — Staff-delegated lending (per-deck, per-event opt-in)
- **F4.11** — Borrow conflict detection (hard block on overlapping approved/lent, soft warning on pending)
- **F4.12** — Walk-up lending (direct lend at event, skips request/approval)
- **F4.5** — Borrow history *(partial)* — per-deck history visible; per-user history view not yet built *(completed in 0.2.0)*
- **F4.10** — Owner borrow inbox *(partial)* — basic view exists; grouped-by-event layout pending *(completed in 0.2.0)*

### Card Data & Validation

- **F6.1** — Parse PTCG text format (PHP `DeckListParser`, regex-based)
- **F6.2** — Card validation via TCGdex (async Messenger enrichment pipeline)
- **F6.3** — Expanded format validation (Black & White onward + ban list)
- **F6.4** — Display card images (high-res from TCGdex, hover overlay, energy fallbacks)

### Notifications

- **F8.1** — Borrow workflow notifications (email + in-app at each state transition)
- **F8.2** — Event notifications *(partial)* — scaffolding exists; full engagement-state triggers pending

### Dashboard & Homepage

- **F7.1** — Dashboard *(partial)* — basic layout with staffing and events cards; full widget set pending
- **F10.2** — Anonymous homepage *(partial)* — public landing with event list and deck catalog CTAs; full design pending

### Infrastructure

- **F1.8** — Account deletion & data export *(partial)* — soft-delete with anonymization scaffolded; confirmation email and JSON export not yet implemented
- **F2.7** — Retire / reactivate a deck *(partial)* — status transitions exist; UI controls pending
- **F9.1** — User language preference *(partial)* — locale field on User entity; preference UI and full i18n not yet applied *(completed in 0.3.0)*
- **F9.2** — User timezone *(partial)* — timezone field on User entity; display conversion not yet applied *(completed in 0.3.0)*

### Cross-Cutting

- PHPUnit test suite (unit + functional, 34+ test methods)
- PHP coverage reporting in CI (pcov + GitHub Action PR comments)
- Vitest frontend unit tests (@testing-library/react)
- PHPStan level 10, PHP-CS-Fixer @Symfony ruleset
- Docker Compose development environment (MySQL 8)
