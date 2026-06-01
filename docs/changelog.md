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

## [1.12.33] — 2026-06-01

Patch release: editing only a CMS page or archetype *translation* now refreshes the parent's publication date, so the public "Updated on" caption reflects real content edits instead of freezing at the last metadata change.

### Bug Fixes

- **Refresh publication date on translation edits** — The public "Updated on" date is read from `lastPublishedAt`, refreshed only by the entity's `#[ORM\PreUpdate]` hook. But content edits go through a standalone `saveTranslation()` action that mutates **only** the `PageTranslation` / `ArchetypeTranslation` child and flushes — and Doctrine fires `PreUpdate` per-entity, only when *that* entity's own fields are dirty. A child change in a `OneToMany` never dirties the owning `Page`/`Archetype`, so the hook (and the `PublishableTimestampsTrait` refresh) was skipped and the date stayed frozen at the last time a parent-level field changed. A new `PageFreshnessListener` mirrors the existing freshness pattern — on `PageTranslation` insert/update it buffers the parent page id and emits a single guarded bulk `UPDATE page SET last_published_at = :now WHERE id IN (:ids) AND is_published = 1` in `postFlush`, keeping the write off the in-progress UnitOfWork and avoiding any parent lifecycle re-entry. `ArchetypeFreshnessListener` (which already bumped freshness on variant-deck activity) is extended so its `collect()` also reacts to `ArchetypeTranslation` edits, which had the identical latent bug. The `is_published = 1` SQL guard reproduces the trait's "drafts never bump" rule; `firstPublishedAt` is left untouched. This is a behavioral fix with no backfill — after deploy, re-saving an affected translation once sets its date to the current day. Coverage: a new functional `PageFreshnessListenerTest` (published parent bumps, draft does not) plus an added `ArchetypeFreshnessListenerTest` case proving a translation edit bumps the archetype. ([#658](https://github.com/jbourdin/expandedDecks/pull/658))

---

## [1.12.32] — 2026-05-31

Patch release: the incremental TCGdex sync (F6.13) becomes multi-locale. TCGdex publishes a set in English first and adds French (and other) translations over the following days, so production previously never picked up the late-arriving translations. The sync now fetches the locale-independent data plus every configured locale, filling translation gaps as they become available, and captures a per-card `updated` baseline for future set-level freshness diffing.

### Features

- **TCGdex multi-locale sync — gap-fill + force update (F6.17)** — The `SyncMode` enum's three cases (`Insert`/`Update`/`Full`) are replaced by two: `Sync` walks the whole catalogue, inserts anything missing, and for each existing card fetches **only the locales it still lacks** — a card whose every configured locale is already populated is skipped with no HTTP call; `ForceUpdate` targets a single set and re-fetches every card across every configured locale unconditionally. Per-card fetching is now locale-aware: a base-locale (English) call carries the locale-independent fields while each additional locale is merged into the JSON columns by a new `TcgdexCardHydrator::mergeLocaleFields()` (abilities/attacks matched by list position, other locales preserved). 404 handling is split by locale — a base-locale 404 means the card genuinely doesn't exist (stop), while a translation-locale 404 means that translation isn't published yet (skip quietly, refill on a later sync). The locale list is the container parameter `app.tcgdex.locales` (`['en', 'fr']`, first entry is the base) so adding a locale is a one-line config change. A new nullable `tcgdex_updated_at` column on `tcgdex_card` (migration `Version20260531120000`, no backfill) captures the API's per-card `updated` timestamp on every touch — not yet a skip-decision input (locale completeness remains the active freshness signal), but a baseline so set-level diffing can switch to it once TCGdex exposes a set-level timestamp. The admin technical dashboard's two buttons become one **Sync** button plus a **Force update** set-picker form (`TcgdexForceUpdateFormType`); the CLI (`app:tcgdex:sync`) and signed webhook are simplified to gap-fill. The former card-count change-detection heuristic is removed (it could not detect locale gaps), so each Sync now walks every existing set — per-card calls are still avoided by the completeness skip and `TcgdexApiThrottle` rate-limits set discovery. ([#655](https://github.com/jbourdin/expandedDecks/pull/655))

---

## [1.12.31] — 2026-05-30

Patch release: drag-and-drop reordering no longer pretends an archetype or deck variant was *updated*. A position-only change now leaves the freshness timestamps untouched, so the catalog "Updated on" caption, the "sort by updated" order, the sitemap `<lastmod>`, and the JSON-LD `dateModified` stop reporting false content changes whenever items are merely re-ranked.

### Bug Fixes

- **Skip freshness timestamps on position-only reorder** — Reordering archetypes (F18.11/F18.12) or deck variants (F18.19) only moves the `position` field, but the `#[ORM\PreUpdate]` hooks re-stamped `updated_at` (and, for published archetypes, `last_published_at` via `PublishableTimestampsTrait`) as if the content had changed — and a variant reorder additionally bumped the *parent* archetype's `last_published_at` through `ArchetypeFreshnessListener`. Three stamping paths were involved, so the fix is applied at each: a new `StructuralChangeTrait` lets `Archetype` and `Deck` early-return from their `PreUpdate` hook when `position` is the sole changed field, and the listener moved its update collection from `postUpdate` to `preUpdate` (where Doctrine reliably exposes the change-set) to apply the same guard — leaving `postPersist` (a genuinely new variant) and the `postFlush` bulk writer intact. A change that touches a real field alongside `position` still bumps as before. Coverage: a unit `StructuralChangeTest` (position-only inert, content and position+content both bump, for both entities) plus functional `ArchetypeFreshnessListenerTest` cases proving a variant reorder bumps neither the deck nor the parent archetype while a variant content edit still bumps both. ([#652](https://github.com/jbourdin/expandedDecks/pull/652))

---

## [1.12.30] — 2026-05-29

Patch release: editor-defined Open Graph image and description controls land on decks, archetype translations, archetype variants, and the Banned/Staple Cards listing pages. Extends F18.28 (the existing site-wide OG meta tags) with editorial overrides via a single `OgMetaResolver` service that centralises the variant-and-locale fallback chain, and reuses the existing `ImageUrlField` drag-and-drop React component across the three new admin form surfaces.

### Features

- **Editor-defined OG image and description on decks, archetypes, variants (F18.30)** — Decks gain non-translatable `og_image` (varchar 255, URL regex matching `Page.ogImage`) and `og_description` (TEXT), nullable. `ArchetypeTranslation` gains the same two columns per-locale, kept independent from `metaDescription` so editors can tune length specifically for social cards. A new stateless `App\Service\Seo\OgMetaResolver` centralises the fallback chain: for a deck the owner's own values win; for an **archetype variant** (`owner === null` + `archetype` set, see `Deck::isArchetypeVariant()`) the resolver crosses into the parent archetype's locale-scoped values; `og_image` ultimately falls back to `Deck.currentVersion.mosaicImageUrl` to preserve the existing F18.28 behaviour. Archetype `og_description` gracefully falls back to `archetype.localizedMetaDescription(locale)` when no OG-specific copy is supplied, so existing meta descriptions keep working unchanged. The `DeckShowController` and `ArchetypeDetailController` inject the resolver and pass the resolved values into their templates; `_partials/opengraph.html.twig` (which already null-guards both fields) needs no template-system change. Admin form fields render with the existing `ImageUrlField` Mantine drag-and-drop component via the `admin/_image_url_field.html.twig` macro — the React mount loop is extracted to `assets/shared/mount-image-url-field.tsx` and reused from `page-form`, `deck-form`, and `archetype-form` Webpack entries (page-form's previously-inline copy is replaced by the same call). Coverage: 13 unit tests in `OgMetaResolverTest` exercising variant fallback, archetype meta-description fallback, and missing-translation paths. ([#650](https://github.com/jbourdin/expandedDecks/pull/650))

- **Editor-defined OG image and description on Banned & Staple Cards pages (F18.31)** — Extends F18.30 to the two CMS listing pages, which already pull their intro content from a `Page` entity via `ListingIntroPage::BANNED_CARDS_SLUG` / `STAPLE_CARDS_SLUG`. `PageTranslation` gains per-locale `og_image` (varchar 255, URL regex) + `og_description` (TEXT); the existing parent-level `Page.ogImage` is kept as a channel-wide default and overridden per-locale only when set. Render precedence in `OgMetaResolver::resolveForPage()`: `PageTranslation.ogImage` → `Page.ogImage` → no `og:image` tag; `PageTranslation.ogDescription` → no `og:description` tag. `og:title` deliberately keeps the existing `'app.banned_card.public.title'|trans` (and the staple equivalent) rather than reusing `PageTranslation.title`, because `PageTranslationFormType` hides the title field when `is_listing_intro` is true and editors don't author it for listing intros. `BannedCardController` and `StapleCardController` inject the resolver and pass the resolved values into their templates; `templates/banned_card/list.html.twig` is wired to emit the new fields; `templates/staple_card/list.html.twig` gains a full `{% block opengraph %}` block (it had none). `AdminPageController::duplicate()` also copies the new per-locale fields when cloning a page so duplicates carry their social metadata. Admin labels live under `app.cms.form.og_*_localized` to distinguish from the parent-level `app.cms.form.og_image`. Coverage: two functional tests on `BannedCardController` assert `og:image` and `og:description` render from editor input and degrade gracefully when blank. ([#650](https://github.com/jbourdin/expandedDecks/pull/650))

---

## [1.12.29] — 2026-05-28

Patch release: basic-energy fallback images are now homogeneous across all 9 colors, sourced from a single CDN (TCGdex `sm1/164–172`) and a single artwork era (Sun & Moon base, 2017). Previously the 8 non-Fairy colors pulled MEE artwork from `assets.pokemon.com` while Fairy alone came from `images.pokemontcg.io/sm1/172_hires.png`, and a separate set of persisted printings under PTCG-Live energy-only set codes carried legacy SVE pokemon.com art or 404-returning TCGdex `assets.tcgdex.net/en/me/mee/*` URLs. A two-pass data migration heals every affected `card_printing` row on deploy.

### Bug Fixes

- **Homogenize basic-energy fallback images on TCGdex `sm1/164–172`** — `CardEnricher::BASIC_ENERGY_IMAGES` now points all 9 basic-energy fallback URLs (× 7 localized name variants, 62 entries total) at `https://assets.tcgdex.net/en/sm/sm1/<164…172>/high.webp`. `sm1` is the only TCGdex-deployed set that contains every basic-energy type including Fairy (retired post-SWSH), and empirical CDN probes on 2026-05-28 confirm 9/9 colors are served — while `assets.tcgdex.net/en/me/mee/*` and `assets.tcgdex.net/en/sv/sve/*` still return 404 (TCGdex source data for both sets has existed since [tcgdex/cards-database#1125](https://github.com/tcgdex/cards-database/pull/1125) but the artwork has not been deployed). The visible cost is artwork-era regression: 8 of 9 colors revert from 2025 MEE/SVE "Basic" banner art to 2017 Sun & Moon art, in exchange for a single CDN, single era, and no `images.pokemontcg.io` runtime dependency (that source is deprecated upstream in favour of paid Scrydex). A new data migration `Version20260528230443` runs two passes to heal already-enriched decks: pass 1 matches the 9 exact pre-PR fallback URLs (synthetic `tcgdex_id LIKE 'energy-%'` printings); pass 2 JOINs `card_printing` on `card_identity` and remaps every printing whose `set_code` is one of the PTCG-Live energy-only codes (`MEE/SVE/SME/XYE/BWE` — the same list as `CardEnricher::ENERGY_SET_CODES`) and whose canonical name is a basic-energy name. That second pass is required because some persisted rows had `tcgdex_id='mee-001'` but `image_url=…/SVE/SVE_EN_1.png` (the row was originally enriched via the `ENERGY_SET_IMAGES['SVE|N']` path and later canonicalized to the MEE TCGdex ID without rewriting the image URL). `down()` reverses only the narrow URL-match pass — the broader sweep is intentionally one-way since original URLs varied per row and weren't recorded. `DeckListParser::DEFAULT_BASIC_ENERGY_PRINTINGS` (minified deck-list export, printed labels, mosaic, Cardmarket export) is **deliberately untouched** — its `setCode` is rendered on physical labels as `MEE 1`/`MEE 2`/etc. and is a separate user-visible identifier whose swap requires its own decision. `ENERGY_SET_IMAGES` (the exact `SVE|N` / `MEE|N` lookup map) is also unchanged — TCGdex still doesn't host that artwork. `data/basic_energies.json` adds the verified TCGdex URLs to the 9 canonical `sm1` entries (additive only; `defaultForMinified` flags continue to mirror `DEFAULT_BASIC_ENERGY_PRINTINGS`). When TCGdex finally deploys MEE artwork (re-probe `assets.tcgdex.net/en/me/mee/001/high.webp`), the 8 non-Fairy fallbacks can move to modern art in a follow-up while Fairy stays on `sm1`. ([#648](https://github.com/jbourdin/expandedDecks/pull/648))

---

## [1.12.28] — 2026-05-27

Patch release: damage-aware card identity signatures so cross-era Pokemon reprints that share an attack name but re-tune its damage no longer collapse into a single `CardIdentity` (e.g. `Sandile/Bite/20 dmg` in `bw2-60` vs `Sandile/Bite/30 dmg` in `swsh12-111`). Ships with an admin button at `/admin/technical` that rebuilds every Pokemon identity's signature from the local TCGdex mirror and splits the ~50 mis-merged identities into distinct rows. Also clears the upstream CVE wave published 2026-05-26/27 on the Symfony 8.0.x family, Twig 3, and the npm `tmp` package.

### Bug Fixes

- **Attack damage now disambiguates card identity signatures (F6.10 follow-up)** — Cross-era reprints share attack names but re-tune damage values, and the previous name-only `attack_signature` collapsed mechanically-distinct cards into one `CardIdentity`. Local prod-mirror analysis surfaced 50 such mis-merged groups versus only 10 genuine type-only variants. `CardIdentityResolver::computeAttackSignature` now folds each attack's damage into the sorted signature as `name|damage` (`|` is the separator because no TCGdex attack name contains it — verified against the local mirror — while `:` is unsafe due to "C.O.D.E.: Protect" in `sv08-069`). The `TcgdexCard` DTO gains an `attackDamages` parallel `list<int|string|null>` populated from both the HTTP parser (`parseCardData`) and the local-mirror hydrator (`buildDtoFromEntity`), with a matching `TcgdexCard::getAttackDamagesEn()` entity helper whose skip rule stays in lockstep with `getAttackNamesEn()` so the two arrays remain index-aligned. A new admin card at `/admin/technical` ("Card identity signatures") drives the migration: a one-shot service walks every Pokemon `CardIdentity`, recomputes its signature from the local `tcgdex_card` mirror, updates in place when all printings agree, and splits divergent identities into find-or-created clones (primary group keeps the original row, picked by largest count with lowest `CardPrinting.id` tie-break). `DeckCard` rows reference `CardPrinting` (not `CardIdentity`), so re-pointed printings carry their decks across automatically; a one-shot DB check confirmed no current `StapleCard` or `BannedCard` would split under the new rule. Coverage: four unit tests for the rebuilder (single-group update, multi-group clone-when-no-target, multi-group reuse-existing-target, mixed-printings-missing-tcgdex-data), an extra clone-cache test for the unflushed-DB race within a single transaction, five entity tests on `getAttackDamagesEn` (parallel-with-names skip rule, string damage like `"30+"`, missing/unsupported damage values, empty attacks), two functional tests covering the new admin endpoint (CSRF rejection + happy path), and updated assertions across the resolver test suite. ([#646](https://github.com/jbourdin/expandedDecks/pull/646))

### Infrastructure

- **Bump Symfony 8.0.x + Twig 3.27 + npm `tmp` to clear new CVE wave** — Eight upstream advisories published 2026-05-26/27 broke Security Audit on every open PR. Composer side: `symfony/http-foundation < 8.0.13` (CVE-2026-48736, SSRF bypass via IPv6 transition forms — 6to4 / NAT64 / Teredo / IPv4-compatible — in `NoPrivateNetworkHttpClient`), `symfony/routing < 8.0.13` (CVE-2026-48784, `UrlGenerator` dot-segment encoding collapse under RFC 3986 normalization), `symfony/security-http < 8.0.13` (CVE-2026-48489, firewall bypass via `failure_forward` subrequest granting unauthenticated access to `access_control`-protected GET routes), `twig/twig < 3.27.0` (five sandbox bypasses: CVE-2026-48808 via the `column` filter, CVE-2026-48807 via `Traversable` in `join`/`replace` and `in`/`not in`, CVE-2026-48806 via dynamic mapping keys, CVE-2026-48805 via deprecated internal wrappers in `core.php`, CVE-2026-46636 when sandbox state changes between renders). Full Symfony 8.0.x family resolved to 8.0.13 via `composer update "symfony/*" twig/twig --with-dependencies`. npm side: `tmp < 0.2.6` (GHSA-ph9p-34f9-6g65, path traversal via unsanitized prefix/postfix enabling directory escape) cleared with `npm audit fix`. ([#646](https://github.com/jbourdin/expandedDecks/pull/646))

- **One-shot SQL script to backfill `Archetype.last_published_at` from variant decks only** — `scripts/backfill_archetype_last_published_at.sql` mirrors the live `ArchetypeFreshnessListener` rule (`owner_id IS NULL`) for ops use against prod if any archetype freshness timestamp was bumped by a player-owned deck under an earlier listener version. Not a Doctrine migration: kept as plain SQL because it doesn't change schema and only needs to run once. Uses `COALESCE` with `a.first_published_at` as the no-variants fallback (not `GREATEST` with the existing `last_published_at` value) so the script can correct rows that were wrongly bumped, rather than treating those wrong values as a floor. ([#646](https://github.com/jbourdin/expandedDecks/pull/646))

---

## [1.12.27] — 2026-05-27

Patch release: fixes the Dialga GX duplicate-identity bug surfaced in production where mechanically-identical Pokemon printed with different elemental types (Dialga GX Metal vs Dragon, same name/HP/abilities/attacks) collapsed into a single `CardIdentity` row, plus picks up `symfony/polyfill-intl-idn` 1.38.1 to close CVE-2026-46644 which was published the day before this release.

### Bug Fixes

- **Pokemon type now disambiguates card identities (F6.10 follow-up)** — `CardIdentity` gains a new `pokemonType` column (sorted comma-joined elemental types, e.g. `"Metal"`, `"Dragon"`, `"Fire,Water"`; empty string for Trainer/Energy as the sentinel matching the existing `abilitySignature='' / hp=0` convention) and the unique index widens from `(name, category, hp, ability_signature, attack_signature)` to include it. The TCGdex `data['types']` payload now flows end-to-end: `TcgdexApiClient::parseCardData()` extracts the types array, `TcgdexCard` DTO carries it, `TcgdexApiClient::buildDtoFromEntity()` forwards it from the local mirror, and `CardIdentityResolver::computePokemonTypeSignature()` produces the sorted signature consumed by `findOrCreateIdentity()` and `expandPrintings()`. The data migration `Version20260526230542` walks every Pokemon identity via `card_printing.tcgdex_id` ⨝ `tcgdex_card.types` (joining on the always-populated string identifier, not the often-NULL `tcgdex_card_id` FK), picks the largest type group as the keeper, clones the identity row for each other type group, and repoints those printings to the clone via a single bulk `UPDATE … WHERE id IN (…)`. Printings whose mirror is missing get the empty-sentinel fallback and self-heal through future enrichment. Three new functional tests in `DialgaGxCardIdentityTest` exercise the real Doctrine layer (column, unique constraint, sorted signature) against MySQL: Metal vs Dragon split into distinct identities; same-type printings reuse one identity; dual-type signatures are sort-stable regardless of TCGdex JSON order. Four new parser tests cover the new types extraction (single, dual, missing-for-Trainer, local-mirror passthrough). Three new unit tests on `computePokemonTypeSignature` round out the helper's contract. ([#644](https://github.com/jbourdin/expandedDecks/pull/644))

### Infrastructure

- **Bump `symfony/polyfill-intl-idn` to 1.38.1 to clear CVE-2026-46644** — the advisory (insecure equivalence on `xn--` labels whose Punycode payload decodes to ASCII-only) was published 2026-05-26 against `>=1.17.1,<1.38.1`, breaking `composer audit` across every open PR. The dep update pulled along its sibling Symfony polyfills (`polyfill-intl-grapheme`, `polyfill-mbstring`, `polyfill-php83` to 1.38.1) plus minor patches on `doctrine/orm` 3.6.6 → 3.6.7, `phpstan/phpstan` 2.1.55 → 2.1.56, and `phpunit/phpunit` 13.1.11 → 13.1.12. Bundled into the same PR as the card-identity fix rather than spun off into a separate `chore/deps-bundle` because the Security Audit job blocked CI on every branch until the polyfill landed somewhere. ([#644](https://github.com/jbourdin/expandedDecks/pull/644))

---

## [1.12.26] — 2026-05-26

Patch release: weekly Dependabot sweep, bundled into one PR with the inline lint fix needed to keep Frontend Quality green. 21 npm minor/patch updates + 2 composer minor/patch updates land together; three React fetch-and-setState call sites get inline `react-hooks/set-state-in-effect` disable comments to match the tightened rule shipped in `eslint-plugin-react-hooks` 7.1.1. The Mantine 8 → 9 majors, the `@eslint/js` 9 → 10 (which requires `eslint` 10), and `webpack-cli` 6 → 7 are deliberately deferred so each major can be reviewed in isolation.

### Infrastructure

- **Bundle the weekly Dependabot sweep into one PR + silence the tightened `react-hooks/set-state-in-effect` rule** — collapses the two open grouped Dependabot PRs (#640 npm with 21 updates, #637 composer with 2 updates) into one bundle: Composer side picks up minor/patch bumps that `composer audit` reports as clean; npm side bumps Babel 7.17/7.16/7.28 → 7.29.7 across `core`/`preset-env`/`preset-react`, `@types/react` 19.2.14 → 19.2.15, `core-js` 3.38 → 3.49, `eslint-plugin-react-hooks` 7.0.1 → **7.1.1** (the rule-tightening one), `globals` 17.4 → 17.6, `react`/`react-dom` 19.2.4 → 19.2.6, `sass` 1.99 → 1.100, `stylelint` 17.4 → 17.12, `typescript-eslint` 8.0 → 8.60, `vitest` 4.1.5 → 4.1.7, `webpack` 5.74 → 5.107. `npm audit` reports 0 vulnerabilities. The 7.1.1 bump of `eslint-plugin-react-hooks` tightened `react-hooks/set-state-in-effect` to flag setState calls inside `useEffect` bodies — including the canonical fetch+setState data-loading pattern that the codebase already exempts in `ArchetypeVariantSelector.tsx`. Three call sites tripped the new check and now carry `// eslint-disable-next-line react-hooks/set-state-in-effect -- <justification>` comments matching the existing convention: `ArchetypeSelect.tsx:76` (typeahead fetch driven by debounced search), `DeckVersionCompare.tsx:130` (diff fetch keyed on `(fromVersion, toVersion)`), `NavbarSearch.tsx:75` (synchronous `setGroups([])` clear when query drops below the 2-char threshold so stale results don't stay visible). Deferred from this bundle, queued individually: **#607** (`@eslint/js` 9 → 10) — needs paired `eslint` 9 → 10 bump because `@eslint/js` v10 demands `eslint` v10 peer, ERESOLVE-fails on its own; **#605 / #606** (`@mantine/hooks` and `@mantine/core` 8 → 9) — major API changes worth reviewing standalone; **#520** (`webpack-cli` 6 → 7) — major plus a stale Security Audit failure that needs its own diagnosis. ([#641](https://github.com/jbourdin/expandedDecks/pull/641))

---

## [1.12.25] — 2026-05-25

Patch release: the durable fix for the IOPS/disk climb that 1.12.24's `rel="nofollow"` only deflected at the surface. Anonymous read-only requests no longer allocate a session row: `LocaleListener` stops writing `_locale` to the session on every request, and `base.html.twig` / `_hero.html.twig` gate every `app.user` and `app.flashes` access on cookie presence so Symfony's `SessionTokenStorage` is never consulted for cookieless visitors. After deploy, the `sessions` table should stop growing for crawler/bot traffic and the public catalog responses won't carry a `Set-Cookie` of the session cookie — which unblocks CDN caching on those URLs (queued for a later PR).

### Performance

- **Anonymous read-only requests no longer allocate a session (#634)** — the same root cause that drove the post-1.12.22 production IOPS / disk climb: every anonymous GET was opening a session row. Two changes close that off. **(1)** `LocaleListener::__invoke()` now refactors `applyLocale()` into a pure `setLocale()` that touches `Request::setLocale()` + `LocaleSwitcher` but never the session bag — the listener was previously calling `getSession()->set('_locale', $locale)` on every request, including locale-prefixed URLs like `/en/archetypes` where the URL is already authoritative. The session is now only *read* (for the `_locale` fallback chain) when a session cookie or `REMEMBERME` cookie says the visitor has prior state; cookieless visitors take the Accept-Language branch without consulting Symfony's session storage at all. `LocaleSwitchController` and `ProfileController` (the explicit user-action paths) keep their own `getSession()->set(...)` calls — by the time those fire the session cookie is already present. **(2)** `base.html.twig` and `home/blocks/_hero.html.twig` gate every `app.user` and `app.flashes` access on the same cookie-presence check via a local `current_user = has_session_cookie ? app.user : null` set at the top of the navbar block. Reading `app.user` consults Symfony's `SessionTokenStorage` even with `lazy: true` on the firewall, which was the second-largest session-starter after `LocaleListener`. The cookie-name check is dynamic — `Session::getName()` server-side, `app.session.name` in Twig — so it works against the production `PHPSESSID` cookie and the test env's `MOCKSESSID` (mock file session factory) without hard-coding either. `LocaleListenerTest` gains a `testAnonymousCookielessRequestStaysSessionFree` regression guard that asserts `Security::getUser()` is never consulted on a cookieless request. Verified end-to-end: `DELETE FROM sessions; curl six anonymous URLs;` leaves `sessions` empty and no `Set-Cookie` of the session cookie on any response, which unblocks the next step — emitting `Cache-Control: public, s-maxage=…` on the anonymous public-page response path so a CDN can cache them. ([#635](https://github.com/jbourdin/expandedDecks/pull/635))

---

## [1.12.24] — 2026-05-25

Patch release: a crawler-deflection follow-up to the F2.27 freshness UI. After 1.12.22/23 shipped, production showed a sharp DB IOPS and disk-space climb. Best-guess root cause: bot crawlers walking the combinatorial tag-filter space on `/archetypes` (with N tags, 2^N tag subsets × 2 modes × M sort options = exponentially many unique URLs), each anonymous request opening a session row. This release adds `rel="nofollow"` on every filter anchor in the archetype catalog so well-behaved crawlers stop traversing the permutation space. A broader follow-up — skipping session allocation entirely for anonymous read-only requests, and applying the same `rel="nofollow"` treatment to the deck catalog and event-list filter rows — is queued separately.

### Bug Fixes

- **`rel="nofollow"` on archetype catalog filter buttons** — bot crawlers walking every tag combination on `/archetypes` were creating one anonymous session per permutation (tag set × AND/OR mode × sort option = 2^N × 2 × M URLs), driving up DB IOPS and disk usage from session storage. The "All" reset, every tag toggle, the drafts toggle (admin only), and both AND/OR mode anchors in `templates/archetype/list.html.twig` now carry `rel="nofollow"` so well-behaved crawlers stop traversing the filter space. `noopener` was deliberately not added — it's a `target="_blank"` security shield (window.opener attack) and is a no-op on same-window internal links, so it would have added markup noise without value. Same treatment should follow on the deck catalog and event-list filter rows in a later PR. ([#632](https://github.com/jbourdin/expandedDecks/pull/632))

---

## [1.12.23] — 2026-05-25

Patch release: hotfix-grade follow-up to 1.12.22's F2.27 freshness UI. Production users in non-UTC timezones (the project's home base, CEST) saw a one-day drift between the archetype catalog's "Updated on …" caption and the same archetype's variant tile on the detail page, plus a locale mix where the prefix was in the request locale but the date itself was in the browser locale. The fix moves the variant date formatting from the React client to the PHP controller so the entire freshness rendering pipeline shares one locale and one timezone — the same pipeline Twig's `format_date('long')` uses on the catalog list.

### Bug Fixes

- **Archetype variant date no longer drifts a day on non-UTC browsers (F2.27 follow-up)** — production users in CEST (and any non-UTC timezone) saw the archetype catalog show "Updated on May 23" while the same archetype's selected variant tile showed "Updated on 24 mai". Two root causes: the React variant selector formatted the ISO timestamp with `new Date(iso).toLocaleDateString(navigator.language, …)` — which uses the **browser's timezone** (CEST shifts UTC midnight forward by 2h) **and** the **browser's locale** (always French on a French browser, regardless of the `/en/…` URL). The Twig list, by contrast, uses `format_date('long')` with the server timezone + request locale. Result: the same timestamp rendered as two different days **and** in two different languages on the same page. `ArchetypeDetailController::buildVariantsData()` now formats the variant's effective updated date server-side via `IntlDateFormatter::create($locale, IntlDateFormatter::LONG, IntlDateFormatter::NONE)` — the same locale + timezone Twig uses on the list — and passes the pre-formatted string as `effectiveUpdatedAtLabel` per variant. `ArchetypeVariantSelector.tsx` drops the `new Date(...).toLocaleDateString(...)` call and just renders the pre-formatted string after the `labels.updatedOn` prefix. ([#630](https://github.com/jbourdin/expandedDecks/pull/630))

---

## [1.12.22] — 2026-05-25

Patch release: CMS pages and the archetype catalogue gain a publish-aware freshness signal. A new `PublishableTimestampsTrait` adds `firstPublishedAt` / `lastPublishedAt` to `Page` and `Archetype` under a strict semantic — `firstPublishedAt` is stamped once on the draft→published transition, `lastPublishedAt` refreshes on every persist while published, drafts never bump either field. Public CMS pages render "Published on … · Updated on …" in a card footer; archetype catalog cards render the same caption with the "Updated on" branch automatically tipping on variant churn via a new `ArchetypeFreshnessListener` that bumps the parent archetype's `lastPublishedAt` whenever one of its variant decks (`owner IS NULL`) or DeckVersions is created or modified — emitted as a deferred bulk SQL `UPDATE` in `postFlush` so Archetype's own lifecycle isn't re-entered. The archetype detail's React variant selector gains an "Updated on <date>" caption derived from `max(Deck.updatedAt, latest DeckVersion.createdAt)` via a single non-N+1 SQL lookup. The archetype catalog sort dropdown adds a fourth "Most recently updated" option ordering by `COALESCE(lastPublishedAt, firstPublishedAt, createdAt) DESC`. JSON-LD `WebPage` and `Article` payloads now carry `datePublished` / `dateModified` from the publication timestamps. The initial migration backfills production rows mirroring the runtime rules — pages use `COALESCE(updated_at, created_at)`, archetypes use `GREATEST(updated_at, max variant Deck.updated_at, max variant DeckVersion.created_at)` — so freshness is correct from the first request after deploy. `twig/intl-extra` is added as a real composer dependency (it was only listed as a transitive suggestion, so the new `format_date` filter would have exploded at runtime otherwise). Dev fixtures backdate `firstPublishedAt` / `lastPublishedAt` on every published Page and Archetype to demonstrate both "published only" and "republished" branches of the rendering conditional.

### Features

- **CMS page publication dates (F11.4)** — `Page` gains nullable `firstPublishedAt` / `lastPublishedAt` columns via the new `PublishableTimestampsTrait`. The trait inspects `Doctrine\ORM\Event\PreUpdateEventArgs::hasChangedField('isPublished')` to detect a draft→published transition reliably: only that transition stamps `firstPublishedAt`; subsequent saves while published refresh `lastPublishedAt`; an unpublish save (`isPublished` changing to `false`) or a no-op save on a draft never bumps anything. Both `Page::onPrePersist()` and `Page::onPreUpdate()` are extended to call the trait's stamp helpers; PrePersist null-coalesces both fields so seeded values from fixtures or a data migration are preserved. The public page footer in `templates/page/show.html.twig` renders `{{ 'app.cms.published_on'|trans }} <long-date>` with an optional `· {{ 'app.cms.updated_on'|trans }} <long-date>` segment when `lastPublishedAt > firstPublishedAt`. The JSON-LD `WebPage` payload now emits both `datePublished` (from `firstPublishedAt`) and `dateModified` (from `lastPublishedAt`, falling back to `firstPublishedAt` → `updatedAt` → `createdAt`). Two new translation keys `app.cms.published_on` / `app.cms.updated_on` are added in en + fr and shared with F2.27. The initial migration backfills existing published rows with `first_published_at = created_at, last_published_at = COALESCE(updated_at, created_at)` so the public footer appears immediately after deploy; draft rows stay NULL so they only get stamped the first time they're actually published. `docs/models/cms.md` documents the new columns. ([#628](https://github.com/jbourdin/expandedDecks/pull/628))
- **Archetype publication dates + variant-driven freshness + sort (F2.27 / #610 / #611)** — `Archetype` shares the same `PublishableTimestampsTrait` as `Page` so the strict publish semantic is implemented once; both `Archetype::onPrePersist()` and `Archetype::onPreUpdate()` call the trait helpers alongside the existing slug regeneration. A new `App\EventListener\ArchetypeFreshnessListener` subscribes to `Doctrine\ORM\Events::postPersist` and `postUpdate` for `Deck` and `DeckVersion`: if the entity is a variant deck (`owner === null && archetype !== null`) or one of a variant deck's versions, the parent archetype's id is buffered into a per-listener array; in `postFlush` the buffer is drained into a single bulk SQL `UPDATE archetype SET last_published_at = :now WHERE id IN (:ids) AND is_published = 1` so Archetype's own lifecycle callbacks (slug regeneration, the trait's change-set inspection) aren't re-entered and the extra write stays out of the in-progress `UnitOfWork`. The archetype catalog card in `templates/archetype/list.html.twig` renders "Updated on <long-date>" when `lastPublishedAt > firstPublishedAt` (the listener-driven path on any archetype with variant churn) and falls back to "Published on <long-date>" otherwise; rendering is gated behind `archetype.firstPublishedAt` so unstamped drafts show nothing. The React variant selector in `assets/components/ArchetypeVariantSelector.tsx` receives a per-variant `effectiveUpdatedAt: string | null` field computed in `DeckRepository::findEffectiveUpdatedAtByDeckIds()` — a single raw-SQL lookup using `GREATEST(COALESCE(d.updated_at, d.created_at), COALESCE((SELECT MAX(dv.created_at) FROM deck_version dv WHERE dv.deck_id = d.id), d.created_at))` keyed by deck id — and renders an "Updated on <date>" caption under the selected variant via `Intl.DateTimeFormat` so the locale matches the browser without server round-trips. `ArchetypeCatalogController` extends its sort allow-list with `'updatedAt'`, and `ArchetypeRepository::findPublishedWithDeckCounts()` adds a fourth branch using DQL's `HIDDEN` keyword: `addSelect('COALESCE(a.lastPublishedAt, a.firstPublishedAt, a.createdAt) AS HIDDEN effectiveUpdatedAt')->orderBy('effectiveUpdatedAt', 'DESC')` — the `HIDDEN` keeps the existing `[archetype, deckCount]` result shape intact so the four existing controller callers and thirteen test invocations stay untouched. The catalog sort dropdown adds the fourth `<option>` between "Name" and "Most decks" using a new `app.archetype.sort_updated` translation key. The JSON-LD `Article` payload now emits `datePublished` from `firstPublishedAt ?? createdAt` and `dateModified` from `lastPublishedAt ?? updatedAt ?? published`, replacing the previous `createdAt` / `updatedAt` fallback. The initial migration backfills `archetype.last_published_at` with the same `GREATEST(updated_at, max variant Deck.updated_at, max variant DeckVersion.created_at)` rule the listener maintains at runtime so freshness is correct from the first request after deploy; `'1970-01-01'` neutralizes NULL results from `MAX()` on archetypes with no variants so `GREATEST()` keeps working. `twig/intl-extra` is added as a real composer dependency since it was only listed as a transitive suggestion and the new `format_date` filter would have exploded at runtime otherwise. Dev fixtures backdate every published Page (13 rows) and Archetype (7 rows) via a new `backdatePublication()` reflection helper to demonstrate both rendering branches; PrePersist's `??=` semantic preserves the seeded values during fixture flush. ([#628](https://github.com/jbourdin/expandedDecks/pull/628))

---

## [1.12.21] — 2026-05-24

Patch release: F2.28's archetype-variant rendering catches up with the data layer that landed in 1.12.20. The `DeckCard.sortOrder` column was being populated correctly for new imports and backfilled correctly for historical decks, but the only consumer of that data was the deck show page — which has a deferred React-island toggle. The archetype detail page (the URL pattern most editors actually navigate to: `/{_locale}/archetypes/{slug}`) was still rendering variant cards through `ArchetypeDetailController::buildVariantsData()` with the same generic subtype/quantity/name sort used for owner-decks. This release makes variants respect the editor's paste order within each section (Pokémon / Trainer / Energy), so an editor who deliberately ordered ace specs first, attackers next, and setup last sees that ordering preserved on the public archetype page.

### Features

- **Variants default to editor paste order within each section (F2.28 follow-up)** — `ArchetypeDetailController::buildVariantsData()` (lines 92–122) had its own copy of the grouped usort logic, separate from `DeckShowController::show()`, with the same subtype-aware comparator (`'trainer' === $type` → `supporter|item|tool|stadium`, then quantity DESC, then name ASC). When the variant being serialized has at least one card with a non-null `sortOrder` (which is true for any new import and any historical variant backfilled through the admin dashboard tile shipped in 1.12.20), the comparator now switches to `sortOrder ASC` — falling back to `PHP_INT_MAX` for cards with null `sortOrder` so they sort to the end deterministically. The pokemon/trainer/energy section grouping stays, the React island's two-column layout stays, but cards inside each section now appear in the order the editor pasted them. Owner-decks are unaffected because `Deck::isArchetypeVariant()` returns true exactly when `owner === null && archetype !== null`. The change also affects the **HTML mosaic view** in the variant page (the in-browser CSS grid of card images), because `ArchetypeVariantSelector.tsx:534–547` builds its `flatCards` array from the same `groupedCards` data the table view consumes — both interactive views now stay in lockstep. The **pre-rendered PNG mosaic** image referenced via `mosaicUrl` is unchanged: `MosaicGenerator::sortCards()` (lines 223–256) has its own independent usort with the original sort, untouched by this PR. The PNG mosaic is the share/social preview asset; the HTML mosaic and table are the reading views. The new `usort` branch is paired with a `testArchetypeVariantSortsCardsBySortOrderWithinSection` regression test that requests `/en/archetypes/regidrago`, decodes the `data-variants` JSON from the variant-selector root, locates the canonical Regidrago variant, and asserts at least one populated section's card order is not strictly alphabetical-by-name. The owner-deck toggle UI on `/deck/{shortTag}` (the React `DeckCardList` island third-axis work) remains deferred per F2.28's original spec — the React component hard-codes the pokemon/trainer/energy two-column layout and adding a true flat-list rendering needs a component refactor; this PR fixes the visible production gap on `/{_locale}/archetypes/{slug}` without that refactor. The duplicate sort logic across two controllers (here and `DeckShowController::show()`) is a smell worth consolidating into a `DeckCardSortStrategy` service, deliberately left as a future-PR follow-up to keep this diff focused. ([#623](https://github.com/jbourdin/expandedDecks/pull/623))

---

## [1.12.20] — 2026-05-23

Patch release: deck-library curation gets two opt-out controls and the archetype catalog gets a creation gate, plus the data layer for preserving the order users paste decks in. A `Deck.personal` flag (F2.30) lets owners opt any deck — Expanded *or* Standard — out of the borrow workflow and event registration while keeping it public and URL-viewable, complementing the existing format-based opt-out shipped in F2.23. The deck form's archetype combobox (F2.29) is tightened so only `ROLE_ARCHETYPE_EDITOR` holders see the "Create new archetype" affordance, with `POST /api/archetype` rejecting non-editors at the server-side gate; everyone else gets an "Ask an archetype editor to add it" empty state. F2.28 captures the source-line index from `DeckListParser` onto a new `DeckCard.sortOrder` column at every controller `new DeckCard()` site, plus an admin dashboard tile + async backfill (`BackfillDeckCardSortOrderMessage`) for historical rows, plus a safety-net call from `EnrichDeckVersionHandler` so fixtures and any future code path that bypasses the parser still end up populated. Two visual fixes ride along: the admin archetype edit page's variant-sprite previews stop 404ing (dead `build/sprites/pokemon/` URL → proxy route), and inline archetype sprites in RTE-rendered description prose stop stretching the line box (new `--inline` SCSS context sizes them in em). The Symfony 8.0.12 / Twig 3.26 ecosystem CVE wave from 2026-05-20 is also picked up.

### Features

- **Personal deck flag (F2.30)** — adds a nullable-default-`false` boolean `Deck.personal` orthogonal to `format` *and* to the existing `public` visibility flag. A personal deck is excluded from the borrow workflow and event registration but stays fully URL-viewable when `public = true`, so an owner can showcase a tournament deck publicly without committing it to the lending library. The opt-out is wired by extending the two existing wrapper helpers `Deck::isLendable()` and `Deck::isEventRegisterable()` with `&& !$this->personal` — `BorrowService` (lines 83, 289), `DeckShowController:157`, and `EventController` (lines 207, 1090) all pick up the new rule without further edits. The two repository queries that filter by `format` directly without going through the helpers (`DeckRepository::findAvailableForEvent`, `searchAvailableForEvent`) gain an explicit `andWhere('d.personal = false')`. Catalog/visibility queries (`countPublicDecks`, `findLatestPublicByArchetype`, etc.) deliberately do *not* filter on `personal` so a personal+public deck stays in the public catalog and on its show page; only borrow and event-registration paths are blocked. Toggling personal **on** is blocked while the deck has active borrows or event registrations (the existing `EventDeckRegistrationRepository::hasActiveRegistrations` + a new `BorrowRepository::countActiveBorrowsForDeck > 0` check); toggling **off** is unrestricted. A "Personal" badge appears on the owner's deck list alongside the existing Standard and Retired badges. Complements F2.23 — Standard format already excludes from lending; this flag extends the same opt-out to Expanded-format decks. ([#617](https://github.com/jbourdin/expandedDecks/pull/617))
- **Restrict inline archetype creation to archetype editors (F2.29)** — closes a curation pollution vector on the deck creation/edit forms: the archetype combobox previously wired `data-create-url="{{ path('app_archetype_create') }}"` for every authenticated user, so any player who typed a name not matching an existing archetype could inline-create a brand-new row (typos, casing variants, joke entries, duplicates) by clicking the "+ Create" combobox option. `ArchetypeController` is tightened from `#[IsGranted('ROLE_USER')]` at class level to `#[IsGranted('ROLE_ARCHETYPE_EDITOR')]`, matching the existing `AdminArchetypeController` pattern — a non-editor POST to `/api/archetype` returns 403 even if the UI gate is bypassed. The Twig templates emit `data-create-url` only when `is_granted('ROLE_ARCHETYPE_EDITOR')`; `ArchetypeSelect.tsx` treats an absent `createUrl` as "no inline-create permission" and renders a "No matching archetype. Ask an archetype editor to add it." `Combobox.Empty` instead of the "+ Create" option. The existing five hardcoded English strings in the React component were left as-is — the component already has accumulated i18n debt and adding one i18n key for a single new string would inflate scope; existing tests stay green via the role hierarchy (`ROLE_ADMIN` inherits `ROLE_ARCHETYPE_EDITOR`, so the admin user used in `ArchetypeControllerTest::testCreate*` covers the editor path unchanged). New functional coverage: `testCreateRejectedForNonEditorUser` (borrower POSTing → 403), `testNewDeckFormEmitsCreateUrlForArchetypeEditor`, `testNewDeckFormOmitsCreateUrlForNonEditor`. ([#618](https://github.com/jbourdin/expandedDecks/pull/618))
- **Preserve imported list order on `DeckCard` + admin backfill (F2.28, data layer)** — `DeckListParser::parse()` already iterates with `foreach ($lines as $lineNumber => $rawLine)` but discarded the line index. This release captures it: `ParsedCard` gains a `sortOrder` field, the parser passes `$lineNumber` at line 257, and every `new DeckCard()` construction site in the codebase (`DeckController::createDeckVersion`, `DeckShowController` re-parse, `AdminArchetypeController` × 3 — five sites total) now calls `setSortOrder($parsedCard->sortOrder)`. A new nullable `DeckCard.sort_order` int column is indexed on `(deck_version_id, sort_order)` so a future "Import order" view can sort within a version without a filesort. Historical rows stay null until backfilled: a new admin dashboard tile on `/admin/technical` shows the count of versions still needing backfill and a "Run backfill" button that dispatches `BackfillDeckCardSortOrderMessage` per `DeckVersion` with a stored `rawList`. The handler delegates to `DeckCardSortBackfillService::backfillVersion()`, a synchronous method that re-parses the rawList, indexes parsed cards by `(setCode, cardNumber)` — *not* `(setCode, cardNumber, cardName)` as the issue spec proposed, because `CardEnricher::enrich()` overwrites `cardName` with TCGdex's canonical form and the rawList still carries the user's original spelling, breaking a name-inclusive signature for every enriched deck — and updates each `DeckCard.sortOrder` idempotently (compares old vs. new before set). `EnrichDeckVersionHandler` also calls `backfillVersion()` after `cardEnricher->enrichVersion()` as a safety net: the method early-exits cheaply when every card already has a `sortOrder` (the normal-import case from the controllers), so the cost on the hot path is one `any nulls?` loop. The safety net catches fixtures and any future code path that creates `DeckCard` without going through the parser. The "Grouped / Import order" UI toggle on the deck show page is deliberately deferred to a follow-up issue — `DeckCardList.tsx` (405 lines, `variant × viewMode` state) needs a third state axis, and the data-layer work is what makes a future toggle possible. ([#621](https://github.com/jbourdin/expandedDecks/pull/621))

### Bug Fixes

- **Render variant sprites via the proxy route, not the dead `build/` URL** — the admin archetype edit page's variant table at `templates/admin/archetype/edit.html.twig:135` rendered each variant's sprite previews via `{{ asset('build/sprites/pokemon/' ~ slug ~ '.png') }}`, but `webpack.config.js`'s `copyFiles({ from: './assets/images' })` does *not* copy the legacy PokéSprite vendor bundle at `assets/vendor/sprites/pokemon/` to `public/build/`, so every `<img src="/build/sprites/pokemon/X.png">` had been silently 404ing since the F2.26 Pokemon HOME 3D render upgrade — visible as broken-image icons in the variant list next to the (correctly-rendered) `PokemonSpriteSelect` autocomplete that lived on the same page. Every other sprite call site (`ArchetypeSpriteRuntime`, `PokemonSpriteSelect`, `ArchetypeVariantSelector`, `VariantComparePicker`, `ArchetypeFilterSelect`) already uses the correct proxy route `/sprites/pokemon/{slug}.png` → `SpriteProxyController::pokemon()` → `SpriteResolver` → Pokemon HOME 3D render (cached on disk at `var/cache/sprites/`, 512×512 PNGs at 60–200 KB). The fix swaps the URL pattern to `{{ path('app_sprite_pokemon', {slug: slug}) }}` (named route, so a future route change can't silently re-break this template). A regression guard `testEditPageDoesNotUseLegacySpriteUrls` asserts the rendered HTML never contains the dead `build/sprites/pokemon/` substring. ([#619](https://github.com/jbourdin/expandedDecks/pull/619))
- **Inline archetype sprites in RTE prose stop stretching the line box** — `.archetype-sprite { height: 52px }` is correct for cards, table rows, and page headers, but when the same `ArchetypeSpriteRuntime::renderSprites()` was invoked from `ArchetypeDescriptionRenderer::expandArchetypeTags()` to expand a `[[archetype:slug]]` tag inside an RTE-authored paragraph, the 52 px image landed inside a ~24 px line of body text and stretched the surrounding paragraph. `ArchetypeSpriteRuntime::renderSprites()` and `::renderDeckSprites()` now accept an optional `string $context = 'block'` argument; when `'inline'` is passed, the wrapping `<span>` gets an `archetype-sprites--inline` modifier class whose SCSS sizes the sprite in em (`height: 1.2em`, `vertical-align: -0.2em` baseline nudge, `margin-right: 0.15em`) so it scales with the surrounding font and aligns to the prose baseline without stretching the line. The em-based sizing handles all RTE contexts uniformly — `<h2>` headings, `<p>` paragraphs, `<li>` items, `<blockquote>`, `<small>` — without per-element rules. `ArchetypeDescriptionRenderer::expandArchetypeTags()` passes `'inline'` at both call sites (variant + archetype branches, lines 107 and 110). The Twig `archetype_sprites()` and `deck_sprites()` functions default to `'block'` so the five existing template call sites render unchanged. The new SCSS rule lives under `.archetype-sprites { &--inline .archetype-sprite { … } }` (nested syntax) per the codebase's stylelint convention that rejects flat `.foo--bar` selectors. ([#620](https://github.com/jbourdin/expandedDecks/pull/620))

### Infrastructure

- **Bump Symfony 8.0.12 and Twig 3.26 for the 2026-05-20 CVE wave** — resolves the coordinated security disclosure that landed in the Symfony advisories database on 2026-05-20 against the entire 8.0 line and Twig 3.x: XSS in the profiler `HtmlDumper` (`symfony/http-kernel`, CVE-2026-47730), multiple Twig sandbox bypasses including object-destructuring assignment (CVE-2026-46639), `__toString()` policy bypass via unguarded string coercion (CVE-2026-47732), `column` filter array_column-on-objects bypass (CVE-2026-46635), and the `template_from_string()` SourcePolicy escape (CVE-2026-46634). The composer audit gate on every PR was blocked until this landed. Bumps span `symfony/{cache,dom-crawler,http-kernel,mailer,mime,monolog-bridge,routing,runtime,security-bundle,security-core,security-http,twig-bridge,web-profiler-bundle,yaml}` from `v8.0.{8,9,10,11}` to `v8.0.12`, `symfony/messenger` from `v8.0.11` to `v8.0.12`, and `twig/twig` from `v3.25.0` to `v3.26.0` (which contains the sandbox hardening). The bump also picked up unrelated patch releases for `doctrine/orm` 3.6.5 → 3.6.6, `aws/aws-sdk-php` 3.381.3 → 3.382.0, `phpunit/phpunit` 13.1.10 → 13.1.11, several `sebastian/*` packages, `guzzlehttp/*`, and `webmozart/assert` — all caught by `composer update` since the security update widened version constraints. `composer audit` reports no remaining advisories. ([#617](https://github.com/jbourdin/expandedDecks/pull/617))

---

## [1.12.19] — 2026-05-20

Patch release: surfaces and fixes the actual reason `worker-messenger` had been flipping to `FATAL` in production — the trigger that 1.12.18's eventlistener was meant to *recover* from. The supervisor command was passing `--memory-limit=512` (no suffix) to `messenger:consume`, and Symfony's `ConsumeMessagesCommand::convertToBytes()` only multiplies by 1024 when the value ends in `k/m/g/t`. A plain `512` is therefore parsed as **512 bytes**, and `StopWorkerOnMemoryLimitListener` fires on the worker's first idle tick because any bootstrapped PHP process sits at tens of MB long before its first transport poll. The worker exits cleanly with status 0 after ~1 second; Supervisor sometimes counts each cycle as a successful `RUNNING → EXITED → respawn` (`autorestart=true`), but occasionally the bootstrap is slightly slower and the worker exits in *under* `startsecs=1`, which increments `startretries` until the program flips to `FATAL`. The change is a single character — `512` → `512M` — plus an inline comment explaining the suffix requirement so the regression doesn't reappear during a future config sweep. The 1.12.18 eventlistener stays in place as the safety net for any future FATAL trigger.

### Bug Fixes

- **Set messenger worker `--memory-limit` unit to MB** — fixes the regression introduced in 1.12.14 when the five per-transport `[program:worker-*]` blocks were consolidated into a single `[program:worker-messenger]`. The changelog for that release stated the intent was 512 MB (matching the previous `deck_enrichment` ceiling sized for TCGdex JSON + GD mosaic buffers), but the `M` was dropped from the command. Static analysis cannot catch this — `--memory-limit` is a stringly-typed `InputOption::VALUE_REQUIRED` that parses silently as bytes when unsuffixed. The fix sets `--memory-limit=512M` in `supervisord.conf` and adds a 3-line comment above the `command=` line referencing `ConsumeMessagesCommand::convertToBytes()` so the suffix isn't dropped again. Confirmed against `vendor/symfony/messenger/Command/ConsumeMessagesCommand.php:334-357` (the parser switches on `substr(rtrim($memoryLimit, 'b'), -1)` against `t/g/m/k`) and `vendor/symfony/messenger/EventListener/StopWorkerOnMemoryLimitListener.php:39-41` (the listener compares `memory_get_usage()` against `$this->memoryLimit` in raw bytes). Post-deploy, `worker-messenger` should run for the full `--time-limit=1200` (20 minutes) between respawns instead of cycling every ~2 s. ([#615](https://github.com/jbourdin/expandedDecks/pull/615))

---

## [1.12.18] — 2026-05-20

Patch release: the production container now self-recycles when the consolidated `worker-messenger` program reaches the `FATAL` state. Until 1.12.17, supervisord retried 3 times then gave up and stayed alive serving FrankenPHP traffic with a permanently dead messenger consumer; Scaleway Serverless Containers happily kept the instance running because they ignore Docker's `HEALTHCHECK` directive and only respawn on PID-1 exit. A new supervisord eventlistener subscribed to `PROCESS_STATE_FATAL` now issues `supervisorctl shutdown` whenever `processname:worker-messenger` enters that state — supervisord exits, the container terminates, and the orchestrator brings up a fresh instance. `worker-messenger`'s `startretries` is bumped from the default 3 to 5 so transient DB blips don't trigger a full respawn, and the Dockerfile `HEALTHCHECK` is repointed at `/health/ready` (purely for visibility under plain `docker run` — Scaleway still ignores it).

### Bug Fixes

- **Self-heal container when `worker-messenger` reaches `FATAL`** — closes the loop that 1.12.17's `WorkerHealthChecker` opened: the readiness probe correctly flipped to 503 when supervisor gave up restarting the messenger consumer, but nothing on the platform reacted because Scaleway Serverless Containers only respawn on PID-1 exit, not on a Docker `HEALTHCHECK` failure or a 503 from the container port. A new `[eventlistener:worker-fatal-killer]` block in `supervisord.conf` runs `supervisor-fatal-listener.sh` — a tiny POSIX-sh implementation of supervisord's eventlistener protocol (write `READY\n`, read `len:N` header, read `N` bytes of payload, write `RESULT 2\nOK`) — subscribed to `PROCESS_STATE_FATAL`. When the payload carries `processname:worker-messenger`, the listener acknowledges the event *before* shutting down (otherwise supervisord blocks on the `RESULT` line and can hang its own shutdown), then calls `supervisorctl -c /etc/supervisor/conf.d/supervisord.conf shutdown` over the same unix socket the readiness probe uses. Supervisord exits PID 1, the container terminates, and Scaleway spawns a fresh instance on the next request. Other programs (`meilisearch`, `frankenphp`) keep their existing `autorestart=true` behaviour and never trigger the listener. `[program:worker-messenger]` gains `startretries=5` (default 3): with a FATAL transition now costing a full container respawn, three failed restarts felt aggressive — five gives transient DB blips more breathing room. The Dockerfile `HEALTHCHECK` is repointed from `/health` to `/health/ready` so a stuck consumer is visible to plain `docker run` / Compose users (Scaleway ignores the directive and continues to drive recovery via PID-1 exit). `docs/installation.md` § Health Check gains a "Self-healing on worker failure" subsection. ([#613](https://github.com/jbourdin/expandedDecks/pull/613))

---

## [1.12.17] — 2026-05-19

Patch release: the production container's `/health/ready` endpoint now consults a local Supervisor probe so the readiness probe flips to 503 when the consolidated `worker-messenger` program is not in `RUNNING` or `STARTING` — a fresh pod with a crash-looping consumer therefore stays out of orchestrator rotation until the async-message worker is actually up. A bundled weekly dependency sweep ships in the same release: nine Dependabot patch/minor bumps (Doctrine ORM, four Symfony components, `phpstan-symfony`, `webpack-encore` 5 → 6, two Tiptap packages, `@tabler/icons-react`) collapse into one PR, `npm audit fix` clears the moderate `brace-expansion` advisory that was blocking other PRs' Security Audit job, and `.github/dependabot.yml` gets a `groups:` block so future weekly runs land as one PR per ecosystem instead of ten.

### Features

- **Gate `/health/ready` on local `worker-messenger` state (F14.8)** — adds `App\Service\Health\WorkerHealthChecker`, an autowired service that shells out to `supervisorctl -c /etc/supervisor/conf.d/supervisord.conf status worker-messenger` over a unix socket and reports the program's state to `HealthController::readiness()`. `RUNNING` and `STARTING` are both treated as healthy — `STARTING` covers the brief cold-start window before `startsecs` elapses and the equally brief respawn after `messenger:consume --time-limit=1200` exits and Supervisor's `autorestart=true` kicks in. `STOPPING` is intentionally excluded — it only occurs during pod shutdown, when readiness *should* fail so the orchestrator drains traffic. `supervisord.conf` gains `[unix_http_server]`, `[rpcinterface:supervisor]` and `[supervisorctl]` sections; the socket is `chown=root:www-data` + `chmod=0770` so the FrankenPHP process (`www-data`) can reach it without elevating privileges and nothing else in the container can. Failure semantics mirror the existing MeiliSearch dimension: in `APP_ENV=prod` any failure (state outside the healthy set, `supervisorctl` unreachable, unparseable output) returns `status: fail` and the endpoint goes 503; in any other env the same conditions return `status: skipped` so local dev and CI — where `supervisorctl` is not provisioned — stay green. A cluster-wide queue stall heuristic over `messenger_messages` was deliberately *not* used: any healthy peer in the same cluster drains the queue and would mask a dead local worker, defeating the per-pod readiness contract. The runner is exposed as an injectable callable so the class stays `final` while remaining testable without forking a real process; an additional functional test invokes the default `Process`-backed runner end-to-end (the `defaultRunner(...)` static method that the injected callable replaces in unit tests). `docs/features.md` adds F14.8 and `docs/installation.md` documents the new readiness payload shape. ([#600](https://github.com/jbourdin/expandedDecks/pull/600))

### Infrastructure

- **Bundle the weekly Dependabot sweep into one PR + regroup `dependabot.yml`** — collapses the nine then-open CI-green Dependabot PRs (#568–#576) into one bundled update: Composer side bumps `doctrine/orm` 3.6.3 → 3.6.5, `symfony/asset-mapper` 8.0.9 → 8.0.11, `symfony/framework-bundle` 8.0.9 → 8.0.11, `symfony/validator` 8.0.9 → 8.0.10, and `phpstan/phpstan-symfony` (dev) 2.0.15 → 2.0.18; npm side bumps `@symfony/webpack-encore` (dev) 5.3.1 → 6.0.0 (its new engines constraint `^22.13.0 || >=24.0.0` is satisfied by CI's Node 24; local dev on Node ≥ 22.13 is recommended — Node 20 only emits an `EBADENGINE` warning rather than blocking the build), `@tabler/icons-react` 3.40.0 → 3.44.0, `@tiptap/extension-file-handler` 3.21.0 → 3.23.1, and `@tiptap/pm` 3.22.3 → 3.23.1. `npm audit fix` clears the moderate `brace-expansion` 5.0.2–5.0.5 advisory (transitive through `@typescript-eslint/typescript-estree`) that surfaced after the previous release and was blocking unrelated PRs' Security Audit job. The webpack-cli 6 → 7 major bump (#520) is intentionally kept as its own PR — majors retain individual review surfaces. `.github/dependabot.yml` gains a `groups:` block — one PR per ecosystem for combined minor + patch updates, one PR per ecosystem for combined security updates, majors still individual — and `open-pull-requests-limit: 5` caps the queue, taking steady-state Dependabot output from ten PRs/week to two. ([#601](https://github.com/jbourdin/expandedDecks/pull/601))

---

## [1.12.16] — 2026-05-14

Patch release: cleanup of the event list and detail pages — the organizer user account is hidden from non-admin views (conceptually the organizer is the venue/store, the account is only useful for admin verification), event list cards align horizontally even when the location is one line on some cards and two lines on others, and every event datetime now renders in the visitor's browser timezone with the canonical event-timezone string available on hover. Timezone strings switch from cryptic abbreviations (`CEST`, `EDT`) to full IANA names (`Europe/Paris`, `America/New_York`).

### Features

- **Hide event organizer user from list and detail (F3.24 refinement)** — moves the user-as-organizer notion off the public event surfaces. `templates/event/list.html.twig` no longer renders the `"Organized by {screenName}"` small text on each card (the right-aligned "Interested" button now lives in a `<div class="text-end">` wrapper instead of the previous `d-flex justify-content-between` container). `templates/event/show.html.twig` wraps the organizer dt/dd row in `{% if is_granted('ROLE_ADMIN') %}` — admins still see the user account for verification, every other viewer (anonymous, regular user, organizer-of-this-event, staff) no longer does. The Symfony `Event.organizer` foreign key, the dashboard's self-recognition badge, the transfer banner, the iCal `ORGANIZER` field, and the JSON-LD `organizer.name` are all left untouched — only the in-app event surfaces are affected. ([#598](https://github.com/jbourdin/expandedDecks/pull/598))
- **Browser-local datetime on event pages (F3.25)** — every event datetime rendered by `event/_datetime.html.twig` is wrapped in a `<time datetime="…" data-event-timezone="…">` element. A small vanilla TS script (`assets/event-datetime-localize.ts`, exposed as the `event_datetime_localize` Webpack entry and loaded globally from `base.html.twig`) reads the visitor's browser timezone via `Intl.DateTimeFormat().resolvedOptions().timeZone` on `DOMContentLoaded`. When the browser timezone differs from the event's, the visible text is rewritten to the browser-local form (e.g., `7 juin 2026, 11:00 Europe/Paris` becomes `7 June 2026, 5:00 AM America/New_York` for a New York visitor) and the original event-timezone string is exposed as a native `title` attribute so the canonical time stays discoverable on hover. The previously broken anonymous fallback that rendered `"Your time: 7 juin 2026, 09:00 UTC"` regardless of the visitor's actual location is removed; the authenticated-user path that surfaces the Bootstrap clock-icon tooltip from `User.timezone` is preserved. Vanilla TS chosen over a React island to avoid mounting 15–50 React roots on the event list and skip the Mantine bundle cost on every page that just decorates static `<time>` elements. ([#598](https://github.com/jbourdin/expandedDecks/pull/598))

### Bug Fixes

- **Align event list cards by reserving 2 lines for the location** — addresses on the event list cards vary between 1 line (`"Troll2jeux - 15-17 Pl. d'Aligre, 75012 Paris"`) and 2 lines (`"Les Gentlemen du Jeu, 13 Av. du Père Lachaise, 75020 Paris"`), so the Format row, tag pill, and Interested button below the location landed at different vertical positions across cards in the same grid row. Always rendering the location `<p>` (dropping the previous `{% if event.location %}` guard) with a new `.event-card-location` class that applies `min-height: calc(2 * 1.5em)` reserves a 2-line slot on every card so the content below the address aligns horizontally across the row. `min-height` (not `height`) — a hypothetical 3-line address still expands gracefully, only that one card breaks alignment for its row. ([#598](https://github.com/jbourdin/expandedDecks/pull/598))

### Refactoring

- **Use IANA timezone names instead of abbreviations on event pages** — `CEST` / `EDT` are cryptic to end users. The `event/_datetime.html.twig` partial and the `event-datetime-localize` JS now emit the full IANA name (`Europe/Paris`, `America/New_York`) in both the visible text and the authenticated-user tooltip — server-side via the raw `tz` variable (replacing the previous `dt|tz_abbr(tz)` call) and client-side by dropping `timeZoneName: 'short'` from `Intl.DateTimeFormat` options and appending the IANA string manually. The `tz_abbr` Twig filter is preserved for any other callers that still want the short form (iCal feed, unit tests, etc.). ([#598](https://github.com/jbourdin/expandedDecks/pull/598))

---

## [1.12.15] — 2026-05-14

Patch release: the event detail page (`/event/{id}`) and its sister "available decks" page (`/event/{id}/decks`) become reachable for anonymous visitors on public-visibility events. Anyone can now read the event metadata (name, date, location, organizer, registration link, description, tags), see the participants list, and browse the decks registered as available for borrow — useful for sharing an event link with prospective attendees who don't yet have an account. Player-bound actions (register as player/spectator, "I'm interested", request to borrow) are rendered as sign-in links carrying `_target_path` so the visitor returns to the same page after authenticating. Non-public events (Draft / Invitation-only) stay locked, and every POST action remains gated by `ROLE_USER`.

### Features

- **Publicly accessible event detail page (F3.24)** — moves the `#[IsGranted('ROLE_USER')]` attribute from the `EventController` class onto each individual action method (`participate`, `select-deck`, `toggle-registration`, `toggle-delegation`, custody endpoints, walk-up, withdraw, interested, transfer accept/decline), leaving `show()` and `availableDecks()` exposed without an auth attribute. `config/packages/security.yaml` gets two new `PUBLIC_ACCESS` rules — `^/event/\d+$` and `^/event/\d+/decks$` — both anchored with `$` so they cannot accidentally match longer action paths like `/event/4/select-deck`. `EventController::show()` now seeds safe defaults (`isParticipant = false`, `eventBorrows = []`, registration map empty, ending-phase stats zeroed) when `$user === null` and computes all player-bound data inside `if (null !== $user) { … }`; the variable names and shapes passed to the template are unchanged so logged-in users see the exact same staff / your-decks / deck-selection / ending-phase panels as before. `DeckRepository::findAvailableForEvent($event, ?User $excludeOwner)` accepts a null owner — when anonymous, the owner-exclusion filter is skipped so visitors see every deck registered as available. Anonymous viewers see the participate-as-player/spectator/interested buttons (and "Sign in to borrow" CTAs on the available-decks page) as anchors to `path('app_login', {_target_path: app.request.requestUri})`, mirroring the homepage hero pattern in `templates/home/blocks/_hero.html.twig`. Three new translation keys (`app.event.sign_in_to_borrow`, `app.event.participation.signin_to_browse`, `app.event.participation.signin_to_join`) ship in both EN and FR. Pinned by a new `EventControllerPublicAccessTest` covering anonymous read on public events (200), anonymous denial on draft events (302 → /login), sign-in link presence in the participation card, anonymous browse on the available-decks page, anonymous `POST /participate` → 302, and no regression on the authenticated organizer view. ([#596](https://github.com/jbourdin/expandedDecks/pull/596))

---

## [1.12.14] — 2026-05-13

Patch release: the production container's Symfony Messenger worker layout is consolidated from five separate `supervisord` programs into a single `worker-messenger` that consumes all eight transports in priority order (`transactional_email` → `notification` → `borrow_lifecycle` → `deck_enrichment` → `tcgdex_sync_series` → `tcgdex_sync_serie` → `tcgdex_sync_set` → `tcgdex_sync_card`). On low-throughput instances this drops the PHP worker footprint from five idle processes to one, lowers idle-poll pressure (`--sleep=60` vs. the previous `--sleep=20` per worker), and tightens memory hygiene by recycling the process every twenty minutes (`--time-limit=1200`) at the previous `deck_enrichment` memory ceiling (`--memory-limit=512`).

### Infrastructure

- **Consolidate Messenger workers into one supervisord program** — replaces the five `[program:worker-*]` blocks in `supervisord.conf` (`worker-transactional-email`, `worker-deck-enrichment`, `worker-notification`, `worker-borrow-lifecycle`, `worker-tcgdex-sync`) with a single `[program:worker-messenger]` that lists every transport on one `messenger:consume` invocation. Symfony Messenger drains the transports in the order given, so user-facing queues (`transactional_email`, `notification`, `borrow_lifecycle`) are placed before background bulk work (`deck_enrichment`, then the TCGdex sync cascade `series → serie → set → card`) and pre-empt it when both have messages. `--sleep=60` raises the post-empty-tick idle delay from 20 s to 60 s; this only takes effect when every transport is empty, so steady-load throughput is unaffected, but it lowers Doctrine `SELECT … FOR UPDATE SKIP LOCKED` polling on the `messenger_messages` table from `5 workers × 3 polls/min × 1 query = 15 q/min` to `1 worker × 1 poll/min × 8 queries = 8 q/min` during quiet periods. `--time-limit=1200` (down from 3600) keeps PHP memory drift bounded by exiting cleanly between messages every 20 min; `autorestart=true` brings the worker straight back. `--memory-limit=512` matches the previous `deck_enrichment` ceiling (sized for TCGdex JSON + GD mosaic buffers) and replaces the per-worker `256` limit other queues had — the headroom only kicks in for enrichment jobs. The previously enrichment-only `--limit=5` (process-restart-every-5-messages for GD hygiene) is dropped in favour of the now-shared time/memory limits. Tradeoff: a `transactional_email` message dispatched right after the worker enters its idle sleep can now wait up to 60 s before pickup (was up to 20 s) — acceptable for password-reset / verification UX. ([#594](https://github.com/jbourdin/expandedDecks/pull/594))

---

## [1.12.13] — 2026-05-13

Patch release: the post-login redirect becomes channel-aware so users on channels without the deck feature no longer land on `/dashboard` (which 404s there via `ChannelFeatureGateListener`), and the `_target_path` open-redirect filter is consolidated into a single `LoginRedirectResolver` service shared by the form-login success handler and the login / register controllers. The mobile navbar also gets a search-collapse UX where the wide `TextInput` is replaced by an icon below the `lg` breakpoint and expands inline on tap, and long CMS titles in navbar dropdowns now wrap correctly inside the collapsed mobile menu without breaking desktop's shrink-to-fit dropdown sizing.

### Features

- **Channel-aware post-login default redirect** — adds `App\Security\LoginRedirectResolver` that returns `app_dashboard` on channels with `enableDecks=true` and `app_home` (`/`) otherwise. `SafeAuthenticationSuccessHandler` now mutates `$this->options['default_target_path']` per request before delegating to the parent, so authenticated users on a no-decks channel like `expandedtalks.wip` land on `/` instead of `/dashboard` — which `ChannelFeatureGateListener` 404s on those channels. The `_target_path` safety filter that rejects absolute URLs (`://`), protocol-relative paths (`//evil.com`) and nested `_target_path` payloads is consolidated into the same resolver and reused by `SecurityController::login()` and `RegistrationController::register()` (the duplicated `isSafeRedirectPath()` helpers are removed). Pinned by `LoginRedirectResolverTest` covering the safe/unsafe path matrix and channel-aware route choice, plus functional tests against the `expandedtalks.wip` channel and an ad-hoc no-decks-but-register-enabled channel. ([#592](https://github.com/jbourdin/expandedDecks/pull/592))
- **Collapse navbar search to icon on mobile, expand on tap** — below the navbar's `lg` breakpoint, the wide centred `TextInput` is replaced by a Mantine `ActionIcon`; tapping it swaps in the existing input expanded inline with keyboard focus on, and `Escape` / tap-outside / a successful navigation collapse back to the icon. `useMediaQuery('(max-width: 991.98px)', false, { getInitialValueInEffect: false })` reads the viewport synchronously on first render so the icon paints from the very first frame (no input flicker on mobile), and `useClickOutside` ignores autocomplete dropdown clicks because the Combobox renders with `withinPortal={false}`. Desktop (`>= lg`) is unchanged — the existing inline Combobox + TextInput path stays as-is. New `app.search.navbar_open` translation key wired through the existing `data-label-*` pipeline as the trigger button's `aria-label`. ([#591](https://github.com/jbourdin/expandedDecks/pull/591))

### Bug Fixes

- **Wrap long navbar dropdown items on mobile without breaking desktop** — long CMS page titles (news article headlines, multi-page categories) in navbar dropdowns used to extend past the mobile viewport because Bootstrap's `.dropdown-item` is `white-space: nowrap`. Setting `white-space: normal` + `overflow-wrap: break-word` globally broke desktop, where the dropdown is `position: absolute` and shrink-to-fit — once items can wrap, the width collapses to Bootstrap's `--bs-dropdown-min-width` (10rem) and every news title wraps to a narrow column. The fix scopes both the wrap rule and the `.nav-item.dropdown` full-width stretch (which keeps the toggle pinned to the right edge on mobile) inside `@include media-breakpoint-down(lg)` — desktop reverts to Bootstrap's default `nowrap` shrink-to-fit behaviour, mobile gets a wide containing block that lets items wrap naturally. ([#589](https://github.com/jbourdin/expandedDecks/pull/589))

---

## [1.12.12] — 2026-05-12

Patch release: the **Expanded Talks** channel gets its own browser favicon and iOS Home Screen icon — a green Dowsing Machine artwork that replaces the default Expanded Decks favicon whenever the resolved theme is `expandedtalks`. Both assets ship through Encore's content-hashed copy-files pipeline under a theme-scoped path so the two channels' icons cannot collide in Bunny CDN's edge cache.

### Features

- **Per-channel favicon for Expanded Talks** — `base.html.twig` and `base_error.html.twig` now branch on `channel_theme()` to select the favicon: when the resolved theme is `expandedtalks`, the templates emit `<link rel="icon" type="image/svg+xml" href="…/dowsing_favicon.svg">` pointing at a 69 KB SVG that wraps a 256×256 PNG with a real alpha channel. The embedded raster was downscaled from a 1100×1100 transparent master with alpha-aware LANCZOS resampling (Pillow's RGBA mode preserves true `(0,0,0,0)` alpha at corners — the prior export-from-Adobe source PNG had a fully opaque background masquerading as transparent, causing Firefox to render the favicon on a white plate). The default Expanded Decks channel keeps the existing `/favicon.svg`. ([#587](https://github.com/jbourdin/expandedDecks/pull/587))
- **iOS Home Screen `apple-touch-icon` for Expanded Talks** — adds `<link rel="apple-touch-icon" sizes="180x180" href="…/apple_touch_icon.png">` inside the same `channel_theme() == 'expandedtalks'` branch in both base templates. The 29 KB 180×180 PNG is downscaled from the same transparent master, so an Expanded Talks bookmark added from iOS Safari → Share → Add to Home Screen now shows the green dowsing icon (with iOS auto-rounded corners applied at the system level — no pre-rounding applied client-side) instead of a generic Safari placeholder. The default channel remains unchanged. ([#587](https://github.com/jbourdin/expandedDecks/pull/587))

---

## [1.12.11] — 2026-05-12

Patch release: editor-uploaded images now produce working social-share previews. `og:image` and `twitter:image` were emitting the raw stored value (e.g. `/api/editor/image/banner.png`) — root-relative URLs that Facebook, Twitter, and LinkedIn crawlers don't resolve, so any homepage carousel slide, CMS page banner, or homepage OG image uploaded through the editor showed a blank preview when the URL was shared. Both meta tags are now absolutized at render time against the current channel's domain.

### Bug Fixes

- **Absolutize `og:image` and `twitter:image` to the channel domain** — adds `ChannelUrlGenerator::absolutizeUrl()` (and the `channel_absolute_url()` Twig function that wraps it) that prepends `{scheme}://{current_channel.domain}` to root-relative URLs while passing already-absolute `http(s)://` URLs through unchanged. `_partials/opengraph.html.twig` wraps both `og:image` and `twitter:image` emissions in the new function, fixing previews for every page that sets `og_image` (homepage, CMS pages with `Page.ogImage`, decks with `mosaicImageUrl`). Mirrors the existing `ChannelUrlGenerator::generateAbsoluteUrl()` shape so the canonical channel domain from the DB is used rather than `app.request.host` (which can drift under reverse proxies). Pinned by 5 new unit tests covering root-relative expansion, absolute `https://` and `http://` passthrough, empty-string passthrough, and current-channel domain resolution. ([#585](https://github.com/jbourdin/expandedDecks/pull/585))

---

## [1.12.10] — 2026-05-12

Patch release: editors can now set a per-locale **page title** and **Open Graph description** on the homepage from the admin block editor, and the secondary channel no longer leaks the app-channel's `"Expanded Decks — Shared Pokemon TCG Deck Library"` literal in its `<title>` tag — both behaviours now derive from `channel_param('brand_name', …)` with the per-page metadata layered on top. The admin editor's locale set also becomes channel-aware: only languages enabled on the current channel are exposed as inputs (and persisted on save), closing a latent gap where editors could translate copy on single-locale channels that would never render.

### Features

- **Per-locale homepage title and OG description in the block editor** — adds `title` (VARCHAR 255, nullable) and `og_description` (TEXT, nullable) columns to `homepage_layout_translation` and surfaces them in a new "Page metadata" card in the React admin editor, with one input pair per active channel locale. At render time, an empty `title` falls back to the channel's `brand_name` parameter; a filled title is prepended as `"{title} — {brand_name}"`. An empty `og_description` simply omits the `<meta property="og:description">` tag (the OG partial was already guarded by `is defined and og_description`). The admin save endpoint trims blank inputs back to `null` so the DB never stores whitespace-only metadata. ([#577](https://github.com/jbourdin/expandedDecks/pull/577))
- **Channel-aware locale set in the homepage admin editor** — the editor and `save()` endpoint now derive the locale list from `Channel::getLocales()` instead of a hardcoded `['en', 'fr']` constant. The content channel (`['en']` only in fixtures) no longer shows or persists FR inputs — neither for the new page-metadata fields nor for the existing block translations. Phantom translation rows can no longer be created on single-locale channels; a `FALLBACK_LOCALES = ['en']` constant guards the legacy null-channel path. Pinned by a `testSaveIgnoresLocalesNotEnabledOnChannel` regression test that posts FR meta against the content channel and asserts no FR row is created. ([#577](https://github.com/jbourdin/expandedDecks/pull/577))

### Bug Fixes

- **Secondary channel no longer leaks app-channel branding in `<title>`** — the two homepage templates (`home/index.html.twig`, `home/blocks.html.twig`) overrode `{% block title %}` with the hardcoded `app.home.title` translation key (`"Expanded Decks — Shared Pokemon TCG Deck Library"`), which rendered on every channel that used those templates — including the content channel. Both templates now resolve the title from `channel_param('brand_name', …)` (the same accessor `base.html.twig` already uses) with an optional per-locale `pageTitle` override from the new homepage metadata field. The obsolete `app.home.title` translation key is removed from both XLF files. ([#577](https://github.com/jbourdin/expandedDecks/pull/577))

---

## [1.12.9] — 2026-05-11

Patch release: lets search engines crawl editor-uploaded images. Adds `Allow: /api/editor/image/*` to both channels' robots.txt so the public-read endpoint that serves carousel slides, OG images, and CMS page banners is no longer caught by the broad `Disallow: /api/` rule — longest-match wins per the robots.txt spec, so the rest of `/api/` stays blocked.

### Features

- **Allow editor-uploaded images in robots.txt** — `RobotsTxtController` now emits `Allow: /api/editor/image/*` in both `buildContentChannelRules()` and `buildAppChannelRules()`. Before this change every image served from `GET /api/editor/image/{filename}` (the public-read endpoint behind `ImageUrlField` / `EditorUploadController`) was blocked by the existing `Disallow: /api/`, which kept the homepage carousel slides, OG images, and CMS page banners out of Google Images and other indexers. The new line is more specific than the disallow, so per Google's robots.txt spec it wins on overlap while the rest of `/api/` remains uncrawlable. Pinned by a new `RobotsTxtControllerTest::testEditorImageEndpointIsAllowedOnBothChannels` assertion. ([#566](https://github.com/jbourdin/expandedDecks/pull/566))

---

## [1.12.8] — 2026-05-11

Patch release: extends the homepage **carousel** with a per-slide image-brightness control. Each item now carries an optional `brightness` percentage that becomes a CSS `filter: brightness(N%)` on the `<img>` only — the caption overlay is a DOM sibling and stays at full brightness, so dimming the image to push a caption forward no longer dulls the text.

### Features

- **Per-slide brightness filter on carousel images** — adds an optional `brightness` integer (0–200, default `80`) to each carousel item, rendered as inline `filter: brightness(N%)` on the `<img>` in both the `slideshow` and `feature_grid` variants. The caption overlay introduced in 1.12.6 sits as a sibling of the image (not a descendant), so the filter doesn't cascade into it — captions stay crisp over a dimmed background image without any extra CSS gymnastics. `HomepageRenderer::resolveCarousel()` gains a `normaliseBrightness()` helper that clamps the value to `[0, 200]` and falls back to `80` for missing or non-numeric input, keeping the inline `style` attribute deterministic and preventing arbitrary CSS filter functions from being smuggled through a hand-edited JSON payload. The admin block editor exposes a Mantine `NumberInput` (step 5, `%` suffix, range 0–200) per slide, and new slides are seeded with `brightness: 80` so existing layouts keep their current look after the upgrade — no migration needed because the carousel block is plain JSON in `HomepageLayout.blocks`. ([#564](https://github.com/jbourdin/expandedDecks/pull/564))

---

## [1.12.7] — 2026-05-10

Patch release: refines the homepage **carousel caption** typography shipped in 1.12.6 — the font now grows substantially larger on both the slideshow layout and the narrower supporter cells of the `feature_grid` variant, and the horizontal padding cap is tightened so wider overlays leave more room for the text without losing the breathing-room feel.

### Features

- **Enlarge homepage carousel caption text** — bumps `.carousel-caption-overlay-text`'s `font-size` clamp from `clamp(1rem, min(7cqi, 80cqi / length), 3.5rem)` to `clamp(1.5rem, min(13cqi, 150cqi / length), 7rem)` so longer captions and the narrow supporter cells of `feature_grid` grow proportionally instead of bottoming out near the `1rem` floor. The 1.12.6 formula left supporter-cell captions visually identical to the previous default size; the new scalars produce a ~36% bump across both wide and narrow cells. Horizontal padding on `.carousel-caption-overlay` tightens from `1rem min(10%, 32px)` to `1rem min(10%, 24px)` — the 10% rule still applies on narrow overlays, but the cap drops from 32px to 24px so wider cells dedicate more width to text. Vertical padding stays at `1rem`; markup, enum modifiers, and React code are unchanged. ([#562](https://github.com/jbourdin/expandedDecks/pull/562))

---

## [1.12.6] — 2026-05-10

Patch release: substantive **homepage block editor** enhancements — the homepage now emits an editor-configurable `og:image` (it previously emitted none at all), the carousel block gains a 2/3 + 1/3 layout variant alongside the existing slideshow, and each carousel item can now carry a centered caption overlay with three colour presets that auto-scales by cell width and text length. Plus an **archetype catalog filter** behavioural change: selecting multiple playstyle tags now defaults to AND ("must match all") with an opt-in OR toggle, replacing the previous implicit OR. Rounded out by a bundled Dependabot deps bump and a sentence-case sweep of the "Staple cards" labels.

### Features

- **Configurable Open Graph image for the homepage (#554)** — adds a nullable `ogImage` field on `HomepageLayout` mirroring `Page.ogImage`'s validation pattern (`Length(255)` + `Regex('#^(/|https?://)#')`). The editor at `/admin/homepage` now exposes a "Homepage settings" panel above the block list with an Open Graph image field that reuses the existing `ImageUrlField` React component (drag-drop + `/api/editor/upload-image` upload — no new plumbing). At render time the homepage uses a two-layer fallback: `HomepageLayout.ogImage` → `channel_param('og_default_image', '')` → no tag emitted. Closes the F18.28 gap where the homepage's "site default" rule had no actual default image, leaving social-media link previews blank. ([#556](https://github.com/jbourdin/expandedDecks/pull/556))
- **2/3 + 1/3 grid variant for the carousel block (#553)** — new `HomepageCarouselVariant` PHP enum with `Slideshow` (default — current behaviour) and `FeatureGrid` (new — 2/3 + 1/3 stacked layout). The variant lives as an optional JSON key on the carousel block, so no DB migration is required and existing carousel blocks render byte-identically. The renderer **downgrades `feature_grid` → `slideshow` when fewer than 3 items survive `startAt`/`endAt` scheduling**, preventing a half-empty cell on the homepage. New shared `_carousel_item.html.twig` partial used by both variants. Editor adds a Mantine `SegmentedControl` with an inline warning when fewer than 3 items are configured for `feature_grid`. ([#559](https://github.com/jbourdin/expandedDecks/pull/559))
- **Caption text overlay on carousel images (#555)** — per-item centered caption rendered over each image, in both carousel variants. Editors pick from three colour presets per item: `white_on_black` (default, best on darker photos), `black_on_white` (lighter photos), and `brand` (`--ed-gold` text + `--ed-navy` outline). The four-corner `text-shadow` technique is used over `-webkit-text-stroke` because Firefox renders `text-stroke` inside-out, losing the small-outline look on photographic backgrounds. Sizing scales on two axes via `container-type: inline-size` + a Twig-passed `--caption-length` custom property: `clamp(1rem, min(7cqi, calc(80cqi / var(--caption-length))), 3.5rem)`. Result: the same caption appears larger in the feature_grid's big left cell than in its supporter cells, and longer captions automatically shrink to fit. Outline offsets use `em` so they stay proportional at every font size. The renderer normalises invalid `captionStyle` values to the default. **Note:** caption is currently non-translatable, mirroring the carousel item's existing `alt` field — lifting both to per-locale translation is a documented follow-up. ([#560](https://github.com/jbourdin/expandedDecks/pull/560))
- **AND/OR combinator for archetype playstyle tag filter, default AND (#548)** — the playstyle tag filter on `/archetypes` previously combined selected tags with **OR**. It now defaults to **AND** ("must match every selected tag") with an opt-in toggle to switch back to OR via `?tagsMode=or`. The toggle is hidden when fewer than 2 tags are selected (the combinator has no effect there). The user's choice persists across other filter changes (sort, chip toggles) by threading `tagsMode` through every `path()` call alongside `currentTags`/`currentSort`. **Behavioural change:** existing bookmarked URLs like `/archetypes?tags[]=A&tags[]=B` now return fewer results (intersection instead of union) — users wanting the prior union behaviour need `?tagsMode=or`, reachable via the new toggle. ([#557](https://github.com/jbourdin/expandedDecks/pull/557))

### Bug Fixes

- **Sentence-case "staple cards" labels across navigation, page titles, and CMS intro pages (F6.15)** — the navbar link, admin dropdown, public page heading, admin list heading, technical-dashboard section, channel admin form (FR locale), and the `CreateListingIntroPagesCommand` page title literals all flipped from "Staple Cards" to "Staple cards". After this change no occurrence of literal "Staple Cards" with a capital C remains in tracked translations, templates, or PHP source. ([#558](https://github.com/jbourdin/expandedDecks/pull/558))

### Infrastructure

- **Bundle four Dependabot npm bumps (#516–#519)** — `ts-loader` 9.5.4 → 9.5.7 (devDep), `@friendlycaptcha/sdk` 0.2.0 → 1.0.0 (regular dep — explicitly documented as a marketing version bump with no API changes from `0.2.3` to `1.0.0`), `vitest` 4.0.18 → 4.1.5 (devDep), `sass` 1.97.3 → 1.99.0 (devDep). Bundling these four into a single update saved develop one lock-file regeneration and one CI run instead of four sequential rebases; lockfile shrank net `−286` lines as transitive dependencies consolidated to single versions. ([#558](https://github.com/jbourdin/expandedDecks/pull/558))

---

## [1.12.5] — 2026-05-09

Patch release: the **Expanded Talks** channel gets a Dowsing Machine card illustration as its navbar logo on both the main layout and error pages; the light/auto/dark color-scheme switcher (F20.1) now also lives in the error-page footer; and an infrastructure fix makes channel resolution survive 404 / exception rendering — previously the channel resolver ran after Symfony's `RouterListener` and so was skipped on any URL that didn't match a route.

### Features

- **Dowsing Machine navbar logo for the Expanded Talks theme (F20.2 / F18.28)** — adds `assets/images/themes/expandedtalks/logo.png` (Dowsing Machine illustration by Ryo Ueda) and renders it left of the brand name on both the main navbar and the error-page navbar of the `expandedtalks` channel; the default `Expanded Decks` channel is byte-identical to before. Extracts the inline brand markup at `base.html.twig` into `templates/_partials/navbar_brand.html.twig` and provides a theme-scoped override at `templates/themes/expandedtalks/_partials/navbar_brand.html.twig` that the existing `ThemeRequestListener` picks up automatically — no `if theme == ...` branching anywhere. Asset pipeline: new Encore `copyFiles({ from: './assets/images', ... })` rule (with `file-loader` as a dev dependency, Encore's required peer for `copyFiles`) so PNG/SVG/JPEG/WEBP/GIF assets compile to `public/build/images/...` with content-hashed filenames preserving `enableVersioning` cache-busting. Logo constrained to 40px height with `object-fit: contain` inside the theme-scoped SCSS bundle. `alt=""` + `title="Dowsing Machine (ill. Ryo Ueda)"` so screen readers skip the redundant image announcement (the brand text follows immediately) while sighted mouse users discover the illustrator credit on hover. Closes [#522](https://github.com/jbourdin/expandedDecks/issues/522) with reduced scope — the broader Dowsing Machine-inspired default-theme palette overhaul originally tracked there is parked. ([#551](https://github.com/jbourdin/expandedDecks/pull/551))
- **Light/auto/dark switcher reaches error pages (F20.1)** — the FOUC-prevention inline script and the three-button switcher are extracted into reusable partials (`_partials/theme_color_scheme_script.html.twig`, `_partials/theme_switcher.html.twig`) consumed by both `base.html.twig` and `base_error.html.twig`. The error template additionally loads `encore_entry_script_tags('app')` so `assets/app.tsx` wires the button clicks. The error template's "no failing services" contract is preserved — `|trans` was already in use, and `encore_entry_*()` and `asset()` are pure helpers. ([#551](https://github.com/jbourdin/expandedDecks/pull/551))

### Bug Fixes

- **Channel resolution now precedes routing, so error pages stay channel-aware (F18.2 / F18.28)** — `ChannelResolverListener` ran at priority 10 on `kernel.request`, *after* Symfony's `RouterListener` at priority 32. On any URL that didn't match a route the router threw `NotFoundHttpException` immediately and the channel resolver never got a turn — leaving error-page templates without `_channel`, with `channel_param()` returning the static fallback, and with the theme override partial unreachable. Bumped `ChannelResolverListener` to priority 100 and `ThemeRequestListener` to 99 so both run before the router; neither needs route attributes (the host header is available at request boundary). Plus: `ChannelContext::getChannel()` now reads from `RequestStack::getMainRequest()` instead of `getCurrentRequest()` because Symfony's `ErrorListener` renders error pages via a sub-request created with `$request->duplicate(null, null, $attributes)`, which **replaces** the attribute bag and drops `_channel`. Reading from the main request always sees the resolved channel. As a bonus, ESI fragments and `render(controller(...))` calls are now correctly channel-aware too — previously a latent bug in the same code path. New `ChannelContextTest::testGetChannelReadsFromMainRequestInsideSubRequest` pins the sub-request semantics so this can't regress silently. ([#551](https://github.com/jbourdin/expandedDecks/pull/551))

---

## [1.12.4] — 2026-05-09

Patch release: card detail modals on the public **Banned Cards** and **Staple Cards** pages are now scrollable on mobile, so the explanation, source link, and printings list below the card image are reachable.

### Bug Fixes

- **Scroll banned & staple card modals on mobile (F6.14, F6.15)** — the modal-body `touchmove` handler called `event.preventDefault()` whenever vertical drag dominated horizontal drag, which cancels the browser's native scroll on iOS/Android — only the card image was visible, and the textual content sat below the fold without any scroll affordance. Drop the `touchmove` listener entirely so scrolling works natively, and require horizontal dominance (`|deltaX| > |deltaY|` plus the existing `SWIPE_THRESHOLD`) in the `touchend` swipe-detection so left/right swipes still navigate between cards but a fast vertical drag does not spuriously trigger navigation. Add Bootstrap's `modal-dialog-scrollable` class to both modals so `.modal-body` owns the scroll and the close `×` button stays pinned at the top of the dialog on small screens. Net `−18` lines, no SCSS changes. ([#549](https://github.com/jbourdin/expandedDecks/pull/549))

---

## [1.12.3] — 2026-05-08

Patch release: hardens the **listing-intro CMS pages** that shipped in 1.12.2 — non-editable fields are now locked and hidden, the reserved pages can no longer be deleted, and they route through their menu category when one is set. Plus three Mantine dark-mode fixes and a security bump for `fast-uri`.

### Features

- **Route listing-intro pages through their menu category (F11.1)** — when a reserved listing-intro page (`banned-cards-intro`, `staple-cards-intro`) has a `MenuCategory` assigned, links to the listing now go through the category's URL instead of the bare `/banned-cards` / `/staple-cards` route. `MenuRuntime` resolves the canonical link via `PageRepository`, falling back to the listing route when no category is bound. ([#546](https://github.com/jbourdin/expandedDecks/pull/546))
- **Block deletion of reserved listing-intro pages** — `AdminPageController::delete` now refuses to remove a page whose slug is one of the `ListingIntroPage` reserved constants, with a translated flash message. Closes the obvious data-loss footgun for editors browsing the admin page list. ([#546](https://github.com/jbourdin/expandedDecks/pull/546))
- **Lock non-editable fields on listing-intro pages** — `PageFormType` / `PageTranslationFormType` mark slug, status, and channel as locked when editing a reserved-slug page; the form binds them as `disabled` so they survive submit without being mutable. ([#546](https://github.com/jbourdin/expandedDecks/pull/546))
- **Hide locked fields on listing-intro pages instead of disabling** — follow-up polish: the locked rows are removed from the rendered template (`templates/admin/page/edit.html.twig`) rather than greyed out, since editors can't act on them anyway. Cleaner edit form for the reserved pages. ([#546](https://github.com/jbourdin/expandedDecks/pull/546))

### Bug Fixes

- **Mantine RichTextEditor dark-theme overrides** — extend the `[data-bs-theme="dark"]` overrides to the toolbar buttons, content area, and link popover surfaces of the Mantine RichTextEditor so the admin page edit form reads correctly in dark mode. ([#546](https://github.com/jbourdin/expandedDecks/pull/546))
- **Bridge Mantine color scheme to `data-bs-theme`** — `AppMantineProvider` now mirrors Mantine's resolved color scheme onto `<html data-bs-theme>` so Bootstrap and Mantine surfaces stay in lock-step when the user switches themes via the footer toggle. ([#546](https://github.com/jbourdin/expandedDecks/pull/546))
- **Break feedback loop in Mantine color-scheme manager** — the bridge above caused a write-read-write loop when both Mantine and the inline pre-paint script tried to own `data-bs-theme`. Guard the bridge so it only writes when the resolved scheme actually differs from the current attribute value. ([#546](https://github.com/jbourdin/expandedDecks/pull/546))
- **Simplify printings-section labels in modals and admin panels (F6.14, F6.15)** — `app.banned_card.modal.banned_printings`, `app.admin.banned_card.field.printings`, and `app.staple_card.modal.printings` all collapse to "Printings" / "Éditions" / "Versions". The previous "Banned printings" duplicated the modal title's banned qualifier, and "Available printings" implied a non-existent "unavailable" counterpart. Translation-only change in both locales. ([#547](https://github.com/jbourdin/expandedDecks/pull/547))

### Infrastructure

- **Bump `fast-uri` to 3.1.2** — clears the GHSA high-severity advisory for `fast-uri < 3.0.6` that Dependabot flagged on `develop`. Transitive via `ajv` → JSON-Schema validation in build tooling; no runtime impact. ([#546](https://github.com/jbourdin/expandedDecks/pull/546))
- **Bump `@babel/plugin-transform-modules-systemjs` to 7.29.4** — clears [GHSA-fv7c-fp4j-7gwp](https://github.com/advisories/GHSA-fv7c-fp4j-7gwp) (high severity, arbitrary-code-generation in module compilation). Transitive via `@babel/preset-env`, used at build time only; non-breaking lockfile-only bump applied via `npm audit fix`. ([#547](https://github.com/jbourdin/expandedDecks/pull/547))

---

## [1.12.2] — 2026-05-08

Patch release: editable Markdown intro on the public **Banned cards** and **Staple Cards** pages, both listings newly searchable through Meilisearch, plus a one-click search reindex button on the admin technical dashboard.

### Features

- **Editable intro block on banned and staple cards pages (F11.1 + F18.1)** — replaces the previously hardcoded `app.banned_card.public.subtitle` / `app.staple_card.public.subtitle` translations with admin-editable Markdown content backed by reserved-slug `Page` entries (`banned-cards-intro`, `staple-cards-intro`), centralised in `App\Constants\ListingIntroPage`. Listing controllers fetch the page per channel and render through `ArchetypeDescriptionRenderer`, so `[[archetype:]] / [[deck:]] / [[card:]]` tags work inside the new block. Admin-only "Edit" button sits in each listing's `card-header-themed`; reserved slugs are filtered out of `/admin/pages` while staying reachable via direct URL through the in-page edit button. `SearchIndexer::mapPage` augments the Meilisearch document for these reserved slugs by appending every active card name plus stripped-Markdown note/explanation, so a search for any card on the listing surfaces the listing page; `SearchIndexListener` refreshes those documents on `BannedCard` / `StapleCard` / `*Printing` lifecycle events. `SearchRuntime` and `PageController::show` route reserved-slug links to the canonical listing routes (`/banned-cards`, `/staple-cards`) instead of `app_page_show`. Idempotent `app:listings:seed-intros` console command runs at cold start (wired into `docker-entrypoint.sh` next to the existing search reindex) so a fresh deployment renders the previous subtitle copy under the H1; gated per channel by `enableBannedCards` / `enableStaples`. New "Search index" card on `/admin/technical` (`ROLE_TECHNICAL_ADMIN`) exposes `SearchIndexer::reindexAll()` as a CSRF-protected button with a Meilisearch health pre-check, mirroring the existing `app:search:reindex` CLI. Both listing pages restructured into Bootstrap card blocks (`card-header-themed` + `card-body cms-content`) so the editable region and the actual card mosaics inherit properly-themed surfaces in dark mode and shrink the FOUC blast radius. Old `*.public.subtitle` translation keys removed; their copy moved into the seed-command defaults so existing channels see no UX regression. ([#544](https://github.com/jbourdin/expandedDecks/pull/544))

### Testing & Quality

- **SearchIndexer / SearchRuntime / controller coverage for the listing-intro path** — three reflection-driven `SearchIndexerTest` cases verify the augmented indexed `content` for both reserved slugs and a "must not call repos for unreserved slugs" guard; two `SearchRuntimeTest` cases pin reserved-slug → listing-route URL routing; `BannedCardControllerTest` covers the `card-body.cms-content` rendering, anonymous-hidden + editor-visible Edit button states; `AdminPageControllerTest` covers the admin-list slug filter and direct-URL edit-form reachability. ([#544](https://github.com/jbourdin/expandedDecks/pull/544))

---

## [1.12.1] — 2026-05-08

Patch release: eliminate the N+1 on the public **Staple Cards** page.

### Performance

- **Eager-load relations on `/staple-cards`** — the public list page issued **83 DB queries** to render a 7-bucket / ~37-card layout, dominated by per-card lazy loads of `representativePrinting`, the `printings` collection, and the `cardPrinting → tcgdexCard → set → serie` walks inside `StapleCardImageResolver`. New `StapleCardRepository::findActiveGroupedByBucket()` fetches every active staple across every bucket in **one** DQL with `LEFT JOIN`s on the full relation graph the controller and image resolver consume; `StapleCardController::list` calls it once instead of looping `findActiveByBucket()` per bucket. Local profile: 83 → **3** queries (-96%), TTFB 180 → 95 ms. On Scaleway managed MySQL the ~80 saved round-trips × ~7 ms RTT shave **~0.5–0.6 s** off page load. Response body is byte-identical pre/post. ([#542](https://github.com/jbourdin/expandedDecks/pull/542))

---

## [1.12.0] — 2026-05-08

Minor release: editor-curated **Staple Cards** (F6.15) ship as a full feature — admin CRUD, public per-bucket grids, technical re-enrich, 36 seeded entries from the editor team. Plus the user-controlled **light/dark/auto theme switcher** (F20.1 follow-up), four homepage / CMS bug fixes (latest-pages block accuracy, ordering, category selector, rich text new-tab links), and a dark-mode heading fix.

### Features

- **User-controlled theme switcher in the footer (F20.1 follow-up)** — three-button group (Light / Auto / Dark) added to the footer as a `col-sm-4 col-md-3` column with `ms-md-auto`, matching the CMS-categories grid rhythm. Selection persists in `localStorage` under key `ed-theme`. The pre-paint inline `<head>` script in `templates/base.html.twig` now reads the stored preference first and falls back to `prefers-color-scheme` only when the mode is `auto` or unset — no flash of wrong theme on reload, and explicit light/dark choices survive OS toggles. A small `window.__edTheme = { get, apply }` bridge lets `assets/app.tsx` toggle the mode and update `.active` + `aria-pressed` on the buttons. Mantine sync unchanged: even in `auto` mode the inline script writes a concrete `light`/`dark` to `data-mantine-color-scheme`. Five new translation keys in `messages.en.xlf` and `messages.fr.xlf`. ([#530](https://github.com/jbourdin/expandedDecks/pull/530))
- **Card rulebox model (F6.10 extension, foundation for F6.15)** — new `CardIdentity.ruleboxType` nullable string column captures the card's rulebox mechanic. `App\Constants\RuleboxType` enumerates 13 known types (Ace Spec, V/VMAX/VSTAR, ex/EX-classic, GX, BREAK, Mega/Mega-classic, Radiant, Prism Star, G) with documented detection patterns (rarity for Ace Spec, name suffixes / prefixes for the others). PR-1 detects Ace Spec only via `tcgdexCard.rarity = 'ACE SPEC Rare'`; the other 12 are documented for future implementation, with a `markTestSkipped` data-provider test enumerating 27 future-detection cases as the green target. Migration backfills the 4 currently-known Ace Spec identities (Brilliant Blender, Megaton Blower, Secret Box, Unfair Stamp) from `card_printing.rarity`. ([#533](https://github.com/jbourdin/expandedDecks/pull/533))
- **Staple Cards (F6.15)** — editor-curated frequently-played cards displayed publicly as per-bucket CSS grids on `/{_locale}/staple-cards`. Seven buckets in display order: Pokémon, Supporter, Item, Tool, Stadium, Energy, Ace Spec. Editors with `ROLE_ARCHETYPE_EDITOR` add staples by entering a single card code; `StapleCardEnricher::createFromCode` resolves the TCGdex card via `CardIdentityResolver`, expands sibling printings, and computes the bucket via priority rule (Ace Spec wins over the type-based buckets). Drag-and-drop reorder per bucket via the existing `assets/shared/sortable-table.ts` helper, scoped to `(bucket, position)` indexes on `staple_card`. **Hotness rating** (default `CardHotness::STAPLE_THRESHOLD = 5`) drives a `?minHotness=N` filter on the public list — bridge to the broader watchlist concept in [#437](https://github.com/jbourdin/expandedDecks/issues/437). **Per-channel gating** via new `Channel.enableStaples` boolean (mirrors `enableBannedCards`); the public page returns 404 when disabled, and the navbar link hides on the same flag. **Technical re-enrich button** on `/admin/technical` (ROLE_TECHNICAL_ADMIN) plus `app:staple-cards:enrich --force` CLI for content-import recovery. Seed migration `Version20260508110000` ships **36 editor-curated staples** (6 Pokémon, 10 Supporters, 9 Items, 4 Tools, 5 Stadiums, 2 Energies) with verbatim editor prose attached as Markdown notes; running the technical re-enrich populates the CardPrinting/CardIdentity links and expands sibling printings. Dev fixture enables staples on the `expandedtalks` channel. The public modal shows the editor note above a flex-wrapped pill list of available printings with prev/next chevrons, ←/→ keyboard nav, and 50px swipe threshold. The editor's chosen printing is auto-pinned as `representativePrinting`, so re-submitting with a different sibling flips the displayed art. ([#538](https://github.com/jbourdin/expandedDecks/pull/538))

### Bug Fixes

- **Homepage latest-pages block: count, ordering, and category selector (F11)** — three issues on the same surface. (1) **#534** `PageRepository::findPublishedByCategory` now uses `Doctrine\ORM\Tools\Pagination\Paginator` with `fetchJoinCollection: true` so `LIMIT` applies to root entities (was: SQL LIMIT applied to translations-joined rows, yielding 2-3 pages for `limit=5`). (2) **#535** Same query now orders by `position ASC` then `createdAt DESC` (was: only `createdAt DESC`, ignoring admin drag-reorder). (3) **#536** The block editor's free-text `<TextInput categorySlug>` becomes a Mantine `<Select>` populated from a channel-scoped category list passed via `data-categories` on the editor mount; stores the new `categoryId` (int) field, with `HomepageRenderer::resolveLatestPages` preferring it and falling back to legacy `categorySlug` for blocks not yet rewritten. Migration `Version20260508120000` translates existing blocks (categorySlug → categoryId) by case-insensitive English-name match within the same channel. ([#539](https://github.com/jbourdin/expandedDecks/pull/539))
- **Rich text external links open in new tab (F11)** — the rich text editor's "open in new tab" choice was stripped on render because CommonMark's default attribute filtering doesn't preserve target/rel from attribute syntax. `MarkdownRenderer` now adds `League\CommonMark\Extension\ExternalLink\ExternalLinkExtension` configured with `open_in_new_window: true`, `noopener: 'external'`, `noreferrer: 'external'`. Every external link (anything whose host is not in `internal_hosts`) renders with `target="_blank"` and `rel="noopener noreferrer"` to prevent tab-nabbing; relative and same-host links keep their default in-tab behaviour. Sidesteps the per-link UI question entirely — external is always external. ([#540](https://github.com/jbourdin/expandedDecks/pull/540))
- **Headings follow body color in dark mode** — Bootstrap 5.3's `--bs-heading-color: inherit` should propagate body color through the cascade, but explicit `color` declarations on intermediate wrappers (channel-theme overrides, `.hero-pokemon`, `.cms-content`) break the chain when the theme is set via JS after first paint. Force `h1, h2, h3, h4, h5, h6 { color: var(--bs-body-color) }` inside the `[data-bs-theme="dark"]` block in `assets/styles/app.scss`. ([#538](https://github.com/jbourdin/expandedDecks/pull/538))
- **TCGdex CDN URL dot-stripping rule narrowed to sm-era only** — the legacy convention "strip dots from set IDs in the CDN path" (`sm7.5` → `sm75`) only applies to the SM era. Modern `xy / bw / swsh / sv / me` set IDs keep dots verbatim (`swsh4.5`, `sv08.5`, `me02.5`). Both `BannedCardImageResolver` and `StapleCardImageResolver` (which share the dot-stripping logic by clone) now use a `setIdForCdn()` helper that strips only when the set ID starts with `sm`. Latent bug since the SV era launched — exposed by F6.15 staples (Budew from sv08.5, Night Stretcher from sv06.5) and the four banned-card Ace Specs from PRE/sv08.5. ([#538](https://github.com/jbourdin/expandedDecks/pull/538))
- **Banned cards modal layout parity with staples (F6.14)** — same vertical-ladder problem (printings list pushed editor content off-screen). Modal layout now reads top-to-bottom as effective date + source → explanation → banned printings (was: printings → date/source → explanation). Printings render as inline `bg-secondary-subtle` flex-wrap pills instead of a `<ul>` of `<li><code>`. Forest of Giant Plants (8+ printings) collapses from 8 vertical rows to 1-2 wrapped rows. ([#538](https://github.com/jbourdin/expandedDecks/pull/538))
- **Staples polish — incremental fixes during F6.15 development** — deprecated Twig `{% for x in y if z %}` syntax replaced with `|filter` (Twig 3 removed the for-if modifier); FR vernacular uses "Staple Cards" / "staple" instead of the calque "carte pilier" (matches French TCG community usage); hotness removed from public modal (private editor info only); `StapleCardEnricher::enrichAllActive` now expands sibling printings + recomputes buckets so seed-imported staples fully populate after one technical re-enrich; sibling-printing pills wrap horizontally instead of stacking vertically; editor's chosen printing is pinned as `representativePrinting` so the displayed art matches editor intent rather than the lowest-rarity sibling. ([#538](https://github.com/jbourdin/expandedDecks/pull/538))

### Testing & Quality

- **CardIdentityResolver — rulebox detection coverage** — 5 active unit tests (`detectRuleboxType` direct on Ace Spec / regular / null rarity, new-identity-set, existing-backfill, no-overwrite) plus a 27-row `markTestSkipped` data-provider enumerating future-detection cases for V/VMAX/VSTAR/ex/EX-classic/G/GX/BREAK/Mega/Mega-classic/Radiant/Prism plus negative + collision guards. ([#533](https://github.com/jbourdin/expandedDecks/pull/533))
- **StapleCardEnricher — bucket priority rule coverage** — 10 unit tests covering Pokémon / Energy / each Trainer subtype / Ace Spec winning over Item / Ace Spec winning over Energy / unknown-trainer fallback to ITEM / null-identity fallback. ([#538](https://github.com/jbourdin/expandedDecks/pull/538))
- **HomepageRenderer — categoryId resolution coverage** — 3 unit tests covering categoryId-prefers-id-over-slug, falls-back-to-categorySlug, returns-empty-when-neither-matches. Plus the existing categorySlug coverage. ([#539](https://github.com/jbourdin/expandedDecks/pull/539))
- **PageRepository — Paginator + translations coverage** — strengthen the existing limit test from `assertLessThanOrEqual` to `assertCount`; add `testFindPublishedByCategoryRespectsLimitDespiteJoinedTranslations` as the regression-proof for #534. ([#539](https://github.com/jbourdin/expandedDecks/pull/539))
- **MarkdownRenderer — external link rendering coverage** — 4 unit tests covering external-link-gets-target-blank-and-rel, preserves-noopener-noreferrer, relative-link-stays-in-tab, javascript-link-stripped (existing safety still works alongside the new extension). ([#540](https://github.com/jbourdin/expandedDecks/pull/540))

---

## [1.11.0] — 2026-05-07

Minor release: case-sensitive archetype playstyle tags, login button removed from the Expanded Talks navbar via theme override, and a dark-mode SCSS DRY pass.

### Features

- **Case-sensitive archetype playstyle tags (F2.15)** — drop the forced title-case normalization on archetype playstyle tags in both the PHP backend (`AdminArchetypeController::normalizeTag`) and the React `PlaystyleTagSelect` component, so editors keep whatever casing they type. Within an archetype, `Control` and `control` are now two distinct tags (strict byte-comparison); the URL filter `?tags[]=Aggro` and `?tags[]=aggro` return different results. The autocomplete suggestion list folds case variants and surfaces the most-frequent casing per case-folded key (ties go to first-seen) so editors don't see noisy duplicates while existing title-cased rows coexist with new mixed-case input. No DB migration: MySQL JSON columns are stored using `utf8mb4_bin` regardless of the table's default collation, so storage and the existing `LIKE`-based tag filter were already case-sensitive — only the application-side normalizers and suggestion folding changed. Existing rows are intentionally left as-is. ([#527](https://github.com/jbourdin/expandedDecks/pull/527))
- **Themeable unauthenticated navbar with Expanded Talks override (F18.28)** — extract the unauthenticated navbar items (login + conditional register) from `templates/base.html.twig` into a new `_partials/navbar_unauthenticated.html.twig`, so themes can override the include via the existing channel theme path mechanism. Add an empty override at `templates/themes/expandedtalks/_partials/navbar_unauthenticated.html.twig` — the Expanded Talks channel is content-only (decks/events/registration already disabled) and now renders no user-facing auth UI in its navbar. Default channels keep the exact same login + register markup as before. No new `Channel::enableLogin` flag, no migration, no admin form binding; future themes can ship their own variant of the unauthenticated nav (e.g. a custom CTA in place of login) by dropping a partial in their theme directory. ([#528](https://github.com/jbourdin/expandedDecks/pull/528))

### Refactoring

- **DRY dark-mode surfaces with shared CSS tokens (F20.1)** — promote five repeated dark-surface literals in `assets/styles/app.scss` to CSS custom properties declared once on `:root[data-bs-theme="dark"]`, then reference them throughout the dark-mode block: `--ed-surface-elevated` (#1a2238, replaces 5 literals), `--ed-border-faint` (rgb(255 255 255 / 12%), 7 literals), `--ed-overlay-faint` (4%, 3 literals), `--ed-overlay-soft` (6%, 3 literals), `--ed-overlay-medium` (8%, 2 literals). Drop the redundant body `background-color` override by routing it through `var(--ed-bg)`. Affected selectors: `.card`, `.dropdown-menu`, `.modal-content`, `.popover`, `.list-group`, `.table`, `.alert`, `.form-control`, `.form-select`, `.card-header-themed`, `.table-themed thead`. The `expandedtalks` theme dark `.card-header-themed` now picks up `--ed-overlay-soft` for cross-theme consistency, and `.form-control` border opacity unifies at 12% (was 15%, lone outlier among seven 12% borders). Internal-only change; visual output is identical to 1.10.0. ([#526](https://github.com/jbourdin/expandedDecks/pull/526))

---

## [1.10.0] — 2026-05-06

Minor release: OS-preference dark theme.

### Features

- **F20.1 — Dark theme following OS preference** — auto dark mode that mirrors `prefers-color-scheme` onto `<html data-bs-theme>` and `<html data-mantine-color-scheme>` via an inline `<head>` bridge script in `templates/base.html.twig`. The script runs synchronously before first paint (no flash of light theme) and listens for live OS toggles. Bootstrap 5.3's native dark variables drive framework components; new `--ed-navy/--ed-blue/--ed-gold/--ed-red/--ed-bg` CSS custom properties carry the custom palette and switch under `[data-bs-theme="dark"]`. Custom dark-tinted surfaces (`.hero-pokemon`, `.card-header-themed`, `.table-themed thead`, `.cms-content blockquote`, rich-text tables, status badges) get explicit dark overrides since their light recipes use semi-opaque navy on white. Mantine islands wrap a shared `<AppMantineProvider defaultColorScheme="auto">` so popovers and inputs match the surrounding chrome. Applied to both the default theme and the `expandedtalks` channel theme. Strictly auto: no user toggle, no `User.preferredTheme` field, no localStorage. ([#524](https://github.com/jbourdin/expandedDecks/pull/524))

### Bug Fixes

- **Restore Bootstrap dark mode for cards and other surfaces** — the compile-time SCSS overrides at the top of `app.scss` (`$card-bg: #fff`, `$card-border-color: #ddd`, `$card-cap-bg`, `$table-striped-bg`) get burned into Bootstrap's component selectors as `.card { --bs-card-bg: #fff }`, which has higher specificity than `:root[data-bs-theme="dark"]`. In dark mode body text adapted (light gray) but cards stayed white → light-on-white = invisible. Re-declared the affected CSS custom properties on the component selectors (`.card`, `.dropdown-menu`, `.modal-content`, `.popover`, `.list-group`, `.table`, `.alert`, `.form-control`, `.form-select`) under `[data-bs-theme="dark"]`. ([#524](https://github.com/jbourdin/expandedDecks/pull/524))

### Documentation

- **Drop milestone-based planning guidance** — remove milestone instructions from `CLAUDE.md` (issue creation + board ordering) and the Milestones (Phases) section from `docs/roadmap.md`. The phase milestones in the repo are no longer used as a planning structure; the kanban project board is the planning surface. ([#523](https://github.com/jbourdin/expandedDecks/pull/523))

---

## [1.9.4] — 2026-05-03

Patch release: a deck-form field-order fix and a project-wide test coverage push from 85.87 % to ~92.4 %.

### Bug Fixes

- **Deck form field order** — `format` (Expanded vs Standard) was missing from the explicit `form_row` list in both `templates/deck/new.html.twig` and `templates/deck/edit.html.twig`, and `latestSet` was missing from the new template. `form_end(form)` was emitting them at the bottom of the form. Both fields now render in their intended positions: `format` right after `name`, `latestSet` between the languages island and the `public` checkbox. ([#514](https://github.com/jbourdin/expandedDecks/pull/514))

### Infrastructure

- **Drop unused `symfony/stimulus-bundle` + `symfony/ux-turbo`** — the project frontend is React/Mantine + Twig/Bootstrap with no Stimulus controllers, no `<turbo-frame>` elements, and no `data-controller` attributes; both bundles were Flex-recipe leftovers that Dependabot kept churning major-bump PRs against. Removed the composer packages, the four orphaned npm deps (`@hotwired/{stimulus,turbo}`, `@symfony/{stimulus-bridge,ux-turbo}`), `config/packages/ux_turbo.yaml`, and the dead Turbo Drive comment boilerplate in `webpack_encore.yaml`. Closed Dependabot PRs #488 and #489. ([#508](https://github.com/jbourdin/expandedDecks/pull/508))
- **Drop unused `symfony/amazon-sqs-messenger`** — every `MESSENGER_TRANSPORT_*_DSN` defaults to Doctrine; no `sqs://` DSN, no `AsyncAws` imports anywhere. Removed the package and its transitive `async-aws/{sqs,core}`. `aws/aws-sdk-php` stays — it's used by Flysystem for Scaleway S3 storage. ([#508](https://github.com/jbourdin/expandedDecks/pull/508))
- **Add Twig-CS-Fixer for template style enforcement** — `vincentlanglet/twig-cs-fixer ^3.14` as a dev dep with the default standard, `make twig-cs-fix` / `twig-cs-check` targets wired into `lint-all`, CI Twig dry-run step. Initial pass on 26/103 templates: trailing commas, hash-key quote cleanup (`{'_target_path': ...}` → `{_target_path: ...}`), and `{% include %}` tag → `{{ include() }}` function (closes #467). ([#508](https://github.com/jbourdin/expandedDecks/pull/508))
- **Composer dependencies refresh** — Symfony 8.0.8 → 8.0.9 across components, doctrine/persistence 4.1.1 → 4.2.0, async-aws/sqs 2.8.1 → 2.9.0 (before removal), phpstan 2.1.54, phpunit 13.1.8, aws-sdk-php 3.379.11, polyfills 1.37. ([#508](https://github.com/jbourdin/expandedDecks/pull/508))
- **Restore strict_types + license header on `config/bundles.php`** — Flex stripped the project's standard PHP header when unconfiguring stimulus/ux-turbo recipes; PHP-CS-Fixer caught the regression in CI and the header is now back. ([#508](https://github.com/jbourdin/expandedDecks/pull/508))

### Testing & Quality

- **F6.14 banned-card coverage backfill (closes #498)** — 62 new tests across 9 files: `BannedCardImageResolverTest` (14, all four URL-resolution branches + rarity-tier sort + serie-prefix guesses), `BannedCardEnricherTest` (11, local hit / TCGdex API hit / alias fallback / null path / force-mode reset / reparent identity-cache regression), `AdminBannedCardControllerTest` (12, auth + role + active/history tabs + CRUD + CSRF rejection), `BannedCardSeedDataTest` (7, applyTo / per-printing seeds for Unown LOT 90 vs LOT 91 / applyAll counts), `BannedCardsEnrichCommandTest` (3), `BannedCardsSeedCommandTest` (2), `AdminTechnicalControllerTest` extension (4 for banned-cards-enrich), `BannedCardPrintingRepositoryTest` (4), `CardPrintingRepositoryTest` (3 for `findFirstBySetCodeAndCardNumber`), `BannedCardFormTypeTest` (4), plus two extensions to `BannedCardsSyncServiceTest` (in-loop `parentsByIdentityId` cache regression + empty-printings soft-delete branch). Patch coverage on the next touch ≥ 87.48 %. ([#509](https://github.com/jbourdin/expandedDecks/pull/509))
- **Sprite subsystem coverage (F2.26 — was 0 %)** — `SpriteResolverTest` (10 tests covering cache-hit short-circuit, CDN→PokeAPI fallback, in-memory pokedex-id memoization, exception swallow, data-URI encoding), `SpriteMappingSyncServiceTest` (5 tests for CSV parsing, alias merging, insert/update counts, fetch-failure throw), `SpritesSyncMappingCommandTest` (2), `SpriteProxyControllerTest` (3, 404 on resolver miss + 200 with `image/png` body + JSON slug listing), `PokemonSpriteMappingRepositoryTest` (4). Also added `tests/Form` and `tests/Sentry` to `phpunit.xml.dist`'s unit suite — 17 previously-orphaned tests were not being run by CI. Source-side fix: `str_getcsv($line, escape: '')` to silence a PHP 8.4+ deprecation surfaced once tests started exercising the parser. ([#510](https://github.com/jbourdin/expandedDecks/pull/510))
- **Admin Page + MenuCategory controller coverage** — 28 new functional tests covering list filters (q/category/channel + view variants), reorder JSON endpoints (valid + invalid payload), new-form submit with channel/category/footer prefill, edit GET + POST round-trip, saveTranslation existing-locale + new-locale + 404, delete + duplicate CSRF rejection, and the cross-translation duplicate clone path. ([#511](https://github.com/jbourdin/expandedDecks/pull/511))
- **PDF generator coverage (was 0 %, 319 LOC)** — `PdfDecklistGeneratorTest` (15, every data-prep branch + Dompdf rendering on tiny stubbed HTML so `renderPdf` is also exercised), `PdfLabelGeneratorTest` (9, simple + foldable variants, sprite resolution, slug title-casing, font-size auto-fit). Tests stub `Twig::render` with a context-capturing callback to assert on grouping, sorting, font-size, gravatar/symbol embedding without touching real templates. ([#512](https://github.com/jbourdin/expandedDecks/pull/512))
- **AdminTechnical + DeckShow PDF + mosaic handler branches** — `AdminTechnicalControllerCoverageTest` (16, every action's CSRF reject path plus happy paths for cache-clearing actions where the handler doesn't reach external services), `DeckShowPdfRoutesTest` (9, label/foldable/decklist routes return `application/pdf` for owners and 403 otherwise; re-enrich requires `ROLE_TECHNICAL_ADMIN` + valid CSRF), and 4 new `GenerateMinifiedMosaicHandlerTest` cases (catch branch with logged + rethrown exception, static printing-overrides short-circuit, tile dedup with summed quantity, type+quantity sort order). ([#513](https://github.com/jbourdin/expandedDecks/pull/513))

Project-wide coverage moved from **85.87 % → ~92.4 %** across this batch (about 1,000 newly-covered lines).

---

## [1.9.3] — 2026-05-03

Patch release: typo fix on the empty-channel coming-soon screen and a CLI memory bump for `cache:clear`.

### Bug Fixes

- **Empty-channel teaser typo** — `app.empty_channel.message` (en) had a stray "keeping is" that read "Togepi is keeping is waiting." Now reads "Togepi is waiting." to mirror the French copy ("Togepi attend."). ([#506](https://github.com/jbourdin/expandedDecks/pull/506))

### Infrastructure

- **Project `php.ini` with 512M memory limit** — the dev container (~1.5k services, 31 entities, ~12k translation units) peaks ~150–170 MB during `cache:clear`'s two-phase compile-and-swap, exceeding the Symfony CLI's default 128M and causing OOMs. The CLI auto-loads a project-root `php.ini`, so a 512M limit unblocks `make` targets that shell out to `bin/console`. ([#506](https://github.com/jbourdin/expandedDecks/pull/506))

---

## [1.9.2] — 2026-05-02

Patch release: UX refinements on the public banned-cards page (F6.14) — denser grid that actually packs 9 per row at `lg+`, and a swipable modal.

### Features

- **Swipable banned-card modal (F6.14)** — clicking a card on `/{_locale}/banned-cards` now opens a modal you can navigate without closing: prev/next chevrons inside the modal body, ←/→ keyboard arrows, and a 50 px horizontal-swipe threshold for touch devices, all cycling through the banned cards in grid order. Inline `<script>` extracted into a typed module (`assets/banned-card-list.ts`) that mirrors the deck-card swipe pattern from `shared/card-hover.ts`. ([#504](https://github.com/jbourdin/expandedDecks/pull/504))

### Bug Fixes

- **Banned-cards grid actually 9 per row at `lg+`** — the 1.9.0 attempt to widen the grid to 9 cards per row was a no-op because Bootstrap 5 only ships `row-cols-{1..6}` by default, so `row-cols-lg-9` resolved to nothing and the page stayed at 6 (and dropped to 4 between `md` and `lg`). Bumped `$grid-row-columns: 9` in `assets/styles/app.scss` so the class actually exists, and raised density at `sm`/`md` (3→4, 4→6) so smaller-than-`lg` viewports stop rendering gigantic cards. ([#504](https://github.com/jbourdin/expandedDecks/pull/504))

---

## [1.9.1] — 2026-05-02

Patch release: respect the channel `brand_name` on every error page.

### Bug Fixes

- **Error page brand name** — `base_error.html.twig` (used by every error page and the empty-channel coming-soon screen) was rendering the literal "Expanded Decks" in the navbar brand link and the footer copyright, ignoring the active channel's `brand_name` parameter. Both now go through `channel_param('brand_name', 'Expanded Decks')`, matching the title block and `base.html.twig`. ([#502](https://github.com/jbourdin/expandedDecks/pull/502))

---

## [1.9.0] — 2026-05-02

Public banned-cards page with admin CRUD (F6.14), empty-channel coming-soon screen, and a denser 9-per-row card grid across deck mosaics and the banned-cards list.

### Features

- **Banned cards public page (F6.14)** — `/{_locale}/banned-cards` lists every Expanded-format ban as a Bootstrap mosaic with click-to-modal details (full image, all banned set/number printings, effective date, source link, Markdown explanation). Schema split into a parent `BannedCard` (one per `CardIdentity`) and child `BannedCardPrinting` (one per upstream `(setCode, cardNumber)` pair) so admins manage a single row per ban. New `Channel.enableBannedCards` flag drives the navbar link, JSON-LD `ItemList` mirrors the archetype catalog pattern, hreflang covers en/fr. ([#497](https://github.com/jbourdin/expandedDecks/pull/497))
- **TCGdex enrichment for banned cards (F6.14)** — `BannedCardEnricher` walks the existing deck-list enrichment chain (local `CardPrinting` → TCGdex API by setCode+cardNumber → alias-set name search) on every banned printing, populates `CardPrinting` + `CardIdentity`, and reparents the printing under the canonical parent for that identity. `TcgdexApiClient::findCard` gained leading-zero stripping (`022` → `22`) and skips its promo-prefix prepend when the upstream value already includes it (`PR-SW SWSH022` no longer becomes `swshp-SWSHSWSH022`). ([#497](https://github.com/jbourdin/expandedDecks/pull/497))
- **Image fallback chain for banned cards (F6.14)** — `BannedCardImageResolver` returns the lowest-rarity printing with a resolvable URL, walking through stored `CardPrinting.imageUrl` → TCGdex CDN built from `tcgdexId` (with dot-stripped set IDs for `sm3.5` → `sm35`) → PokemonTCG.io CDN → TCGdex CDN built from the upstream PTCG setCode via `TcgdexSet`. ([#497](https://github.com/jbourdin/expandedDecks/pull/497))
- **Markdown rationale per ban (F6.14)** — every banned card ships with the verbatim "Details of Changes" wording from the original pokemon.com announcement (Burning Shadows 2017, Celestial Storm 2018, Team Up 2019, Cosmic Eclipse 2019, Vivid Voltage 2020, Paldean Fates 2024, Stellar Crown 2024, Mega Evolution 2025, Mega Evolution: Perfect Order 2026; Bulbanews fallback for the 2015 Lysandre's Trump Card ban whose original pokemon.com URL is dead). Bold on banned card / Ability / attack proper nouns, italics on expansion names — same emphasis the original pages used. The two Unown bans in LOT (DAMAGE 90, HAND 91) carry per-printing seed overrides since they shipped in different announcements. ([#497](https://github.com/jbourdin/expandedDecks/pull/497))
- **Banned-card admin CRUD (F6.14)** — `/admin/banned-card` (ROLE_ADMIN) with active / history tabs, soft-delete + restore via the project inline-confirm pattern, Markdown editor (Mantine + tiptap) on the explanation field reusing the existing `_rich_text_editor.html.twig` macro and a new `banned_card_form` Webpack entry. Re-banning a previously soft-deleted entry reactivates it instead of failing on the unique constraint. ([#497](https://github.com/jbourdin/expandedDecks/pull/497))
- **Banned-card sync hardening (F6.14)** — `BannedCardsSyncService` now soft-deletes parents that disappear from upstream (audit history preserved), reactivates archived rows when the upstream re-lists them, and dispatches the `BannedCardSeedData` service on every freshly-created parent so the metadata ships with the row. New `app:banned-cards:enrich [--force]` and `app:banned-cards:seed` console commands plus matching admin buttons on the technical dashboard for re-attempting unresolved cards or seeding metadata. ([#497](https://github.com/jbourdin/expandedDecks/pull/497))
- **Empty-channel coming-soon screen** — channels with every `enable_*` flag off now render a Pokémon-styled coming-soon page (Togepi sprite + "Something will hatch here soon" hatching teaser) instead of the empty welcome fallback. The check runs **before** the `HomepageLayout` and dashboard-redirect branches so the screen wins even when an admin published a layout but didn't enable any feature yet — the layout would have nothing meaningful to link to anyway. New `home/empty_channel.html.twig` extends `base_error.html.twig` for a no-services lightweight layout, en + fr translations on `app.empty_channel.{title,lead,message}`. ([#499](https://github.com/jbourdin/expandedDecks/pull/499))

### Bug Fixes

- **Banned-card grouper safety (F6.14)** — fall back to per-printing keys instead of card-name when no `CardPrinting` is linked, so two functionally-distinct cards that share a name (Unown HAND vs DAMAGE) stay as separate tiles. The representative printing now prefers the lowest-rarity printing with a resolvable image URL rather than the lowest-rarity printing overall. ([#497](https://github.com/jbourdin/expandedDecks/pull/497))
- **Banned-card placeholders never reach the page (F6.14)** — `BannedCardRepository::findActiveOrderedByEffectiveDate` does an `INNER JOIN` on the printings collection and groups by parent id, hiding any parents that ended up without children (e.g. transient placeholders from an interrupted sync). A defence-in-depth migration also deletes pre-existing empty parents. ([#497](https://github.com/jbourdin/expandedDecks/pull/497))

### Refactoring

- **9-per-row card grids** — public banned-cards page now fits 9 cards per row at `lg+` (was 6) via Bootstrap 5 `row-cols-*` classes. The interactive deck card mosaic on the deck show page bumped from 8 → 9 thumbnails per row at `md+`. The server-rendered `MosaicGenerator` (the .webp attached to each deck for sharing) also bumped to 9; existing stored mosaics regenerate on the next enrichment cycle. ([#500](https://github.com/jbourdin/expandedDecks/pull/500))

### Testing & Quality

- **Functional tests** for the new banned-cards public list (one tile per parent, modal data attributes, JSON-LD ItemList, hreflang, soft-delete exclusion, two-Unown collision stays separate) and the empty-channel screen (en + fr renders, regular fallback wins on a non-empty channel, regression test pinning the empty-channel-wins-over-published-layout precedence). ([#497](https://github.com/jbourdin/expandedDecks/pull/497), [#499](https://github.com/jbourdin/expandedDecks/pull/499))
- **Coverage backfill tracked in [#498](https://github.com/jbourdin/expandedDecks/issues/498)** — Codecov reported 42.75 % patch coverage on the F6.14 PR (308 lines missing on `BannedCardImageResolver`, `BannedCardEnricher`, `AdminBannedCardController`, etc.); follow-up issue lists every uncovered file with a sequenced order of attack to land tests on a future PR.

---

## [1.8.13] — 2026-05-01

Event tags, public + personal iCal calendar feeds, organizer handover, and the allow-custody flag for staff delegation.

### Features

- **Event tags (F3.12)** — `EventTag` entity with ManyToMany on `Event` and cascade-persist; Mantine `TagsInput` React island wired to a HiddenType field on the event form (existing tags fed via data attribute, names resolved server-side by slug). Tag chips on the event detail page and on event-list cards link to a new `/event/tag/{slug}` listing, plus a tag-filter row on `/event` with an active-tag highlight. ([#494](https://github.com/jbourdin/expandedDecks/pull/494))
- **Public iCal feed (F3.16)** — `eluceo/ical` 2.16 + `EventIcalBuilder` produce RFC 5545 VCALENDAR/VEVENTs with stable UID, UTC `DTSTART`/`DTEND`, `SUMMARY`, `LOCATION`, `URL`, `STATUS`, plus injected `X-WR-CALNAME` / `NAME` for the human-readable feed name. `GET /event.ics` and `GET /event/tag/{slug}.ics` serve `text/calendar` with `Cache-Control: public, max-age=3600`. Subscribe button on every event list with three actions (Subscribe via `webcal://`, Download `.ics`, Copy feed URL) — URL adapts to the active tag. ([#494](https://github.com/jbourdin/expandedDecks/pull/494))
- **Personal agenda + private iCal feed (F3.14)** — `GET /event/agenda` (ROLE_USER) lists upcoming events where the user is **organizer**, **on staff**, or holds **any engagement** (interested / invited / playing / spectating). One ORed query in `EventRepository::findUpcomingForUserAgenda`. Cards show role and engagement badges. New `User.calendarToken` (varchar 64, unique) generated lazily by `PersonalCalendarTokenService::ensureToken()` (base64url-encoded `random_bytes(32)`); cleared in `User::anonymize()` for GDPR. `GET /calendar/event/{token}.ics` serves the user's calendar to anonymous calendar clients (404 on unknown token). `POST /event/agenda/regenerate-token` (CSRF-protected) rotates the token so a leaked URL can be invalidated. Single feed block on the agenda page combines the URL field, copy / subscribe / download actions, and the regenerate flow. **My Agenda** link added to the user dropdown after **My Decks** and as an outline-primary button in the event list header. ([#494](https://github.com/jbourdin/expandedDecks/pull/494))
- **Organizer handover (F3.23)** — two-step transfer with target confirmation. `Event` gains `pendingTransferTo` + `pendingTransferRequestedAt` and helpers `requestTransferTo()` / `clearPendingTransfer()` / `hasPendingTransfer()`. Four routes under `/event/{id}/transfer/`: `initiate` and `cancel` for the organizer, `accept` and `decline` for the target — protected by a new `denyAccessUnlessTransferTarget` gate. The target must accept on their side before the organizer is swapped; a declined transfer is a no-op. New `NotificationType` cases (`EventTransferRequested` / `EventTransferAccepted` / `EventTransferDeclined`) deliver in-app notifications. The event detail page surfaces a target-side accept/decline banner above the staff card and an organizer-side "Hand over organization" card with a user-picker (reusing the staff autocomplete). The Send button uses the project's inline-confirm pattern (no `confirm()` dialog). ([#495](https://github.com/jbourdin/expandedDecks/pull/495))
- **Allow custody flag (F4.8 gate)** — new `Event.allowCustody` (default false). When the organizer hasn't accepted custody for an event, players can no longer toggle delegation in `EventController::toggleDelegate` and the **Delegate** button is disabled with an explanatory tooltip. The today-event fixture seeds `allow_custody=true` to keep its delegated registrations consistent. ([#495](https://github.com/jbourdin/expandedDecks/pull/495))

### Bug Fixes

- **Event form rendering** — `Visibility` and `Invitation only` rows no longer leak past the submit button on event/new and event/edit. They're now rendered explicitly between `tournamentStructure` and `isDecklistMandatory`. ([#495](https://github.com/jbourdin/expandedDecks/pull/495))
- **Event list firewall** — `^/event` was falling through to the `ROLE_USER` rule, so anonymous fetches of `/event.ics` were redirected to the login page (with Apple Calendar then complaining "données non valides"). Added PUBLIC_ACCESS for `/event.ics`, `/event/tag/<slug>(.ics)?`, and `/calendar/event/<token>.ics`. ([#494](https://github.com/jbourdin/expandedDecks/pull/494))

### Testing & Quality

- **EventTagTest** — slugify cases (including unicode-aware paths) + lifecycle. ([#494](https://github.com/jbourdin/expandedDecks/pull/494))
- **EventIcalBuilderTest** — VCALENDAR shape, UID/URL, named feed, cancelled status, empty list. ([#494](https://github.com/jbourdin/expandedDecks/pull/494))
- **PersonalCalendarTokenServiceTest** — ensureToken assigns + persists, idempotent on existing token, regenerate replaces, findUserByToken. ([#494](https://github.com/jbourdin/expandedDecks/pull/494))
- **EventCalendarControllerTest** (functional + unit) — anonymous calendar fetches, tag list/feed 404s, tag-filter query param, plus a no-kernel unit test for cache-header verification (Symfony's SessionListener rewrites them in WebTestCase). ([#494](https://github.com/jbourdin/expandedDecks/pull/494))
- **EventAgendaControllerTest** — auth redirect, lazy token assignment, regenerate flow (old token now 404s, new token works anonymously), unknown-token 404, CSRF-rejected regenerate. ([#494](https://github.com/jbourdin/expandedDecks/pull/494))
- **EventTagRepositoryTest** — resolveByNames creating unknown tags, dedup by slug, ignoring empty / punctuation-only entries, ordered listing. ([#494](https://github.com/jbourdin/expandedDecks/pull/494))
- **EventControllerTagsTest** — create-with-new-and-existing-tags upserts the EventTag rows, edit replaces the tag set, empty / malformed `tagsInput` payloads clear tags. ([#494](https://github.com/jbourdin/expandedDecks/pull/494))
- **EventCustodyAndHandoverTest** — custody default off, custody enable via edit, delegation refused / accepted depending on flag, transfer initiate, self-transfer rejection, accept swaps organizer, decline keeps organizer, accept by non-target → 403, cancel clears pending. ([#495](https://github.com/jbourdin/expandedDecks/pull/495))

---

## [1.8.12] — 2026-05-01

Dashboard "My decks" filter, PTCG Live export padding fix, and CI tooling permissions.

### Features

- **Dashboard "My decks" filter** — `/dashboard` "My decks" section now filters to active Expanded decks only (excludes retired and Standard format decks), matching the dashboard's purpose as a borrow-ready overview. ([#490](https://github.com/jbourdin/expandedDecks/pull/490))

### Bug Fixes

- **PTCG Live export padding** — card numbers were rendered zero-padded (e.g. `DRI 051`, `WHT 086`) in PTCG Live exports and on-screen tables, while TCG Live's text format expects plain numbers (`DRI 51`, `WHT 86`). Pasting the export into TCG Live produced an invalid deck list. New `CardNumberFormatter` PHP helper, `card_number` Twig filter, and `displayCardNumber` JS util applied at every user-facing surface: original + minified text exports, deck show table, archetype variant selector, version-compare diffs, foldable label PDF, and printed decklist PDF. Storage and `[[card:SET-NNN]]` shortcode lookups remain unchanged. ([#492](https://github.com/jbourdin/expandedDecks/pull/492))

### Infrastructure

- **CI tooling permissions** — allow `gh issue view` and `gh project item-list` in `.claude/settings.json` so read-only project board queries don't trigger permission prompts. ([#491](https://github.com/jbourdin/expandedDecks/pull/491))

### Testing & Quality

- **CardNumberFormatter unit tests** — 9 cases covering padded numerics, all-zero collapse, empty input, and alphanumeric forms (`TG14`, `SWSH001`, `001A`, `4a`). Padding-regression cases added to `OriginalListFormatterTest` and `MinifiedListGeneratorTest`. ([#492](https://github.com/jbourdin/expandedDecks/pull/492))

---

## [1.8.11] — 2026-04-27

Admin sprite mapping fix, error page sprite update, and CI hardening.

### Bug Fixes

- **Admin sprite mapping rebuild** — use injected `TranslatorInterface` instead of service locator in `AdminTechnicalController::spriteMappingRebuild()`. Fixes a runtime error when the translator was not accessible via `$this->container->get()`. ([#485](https://github.com/jbourdin/expandedDecks/pull/485))
- **Error page sprite** — 500 error page now uses Psyduck sprite instead of Porygon, with increased sprite display size (80px → 120px) on error pages. ([#485](https://github.com/jbourdin/expandedDecks/pull/485))

### Infrastructure

- **Claude Code permissions** — hardened permission rules in `.claude/settings.json`. ([#486](https://github.com/jbourdin/expandedDecks/pull/486))

### Testing & Quality

- **CdnErrorControllerTest** — updated 500 error sprite assertion from Porygon to Psyduck to match template change. ([#485](https://github.com/jbourdin/expandedDecks/pull/485))

---

## [1.8.10] — 2026-04-27

Pokemon HOME 3D sprite upgrade — CDN pull-through proxy architecture.

### Features

- **Pokemon HOME 3D sprites (F2.26)** — Replace PokéSprite pixel art (42×42px) with Pokemon HOME 3D renders (512×512px) via a CDN pull-through proxy. New `PokemonSpriteMapping` entity maps slug → PokeAPI dex ID. `SpriteProxyController` at `/sprites/pokemon/{slug}.png` serves sprites from filesystem cache, fetching from PokeAPI on miss. Container-safe: re-fetches from own CDN on cold start. `app:sprites:sync-mapping` CLI syncs from PokeAPI CSV (1350+ entries) with slug alias support. Admin "Rebuild sprite mapping" button. `PokemonSpriteSelect` React autocomplete fetches slugs from `/api/sprites/slugs` API instead of build-time manifest. ([#483](https://github.com/jbourdin/expandedDecks/pull/483))
- **Larger sprite display** — Sprite CSS height increased from 40px to 52px, taking advantage of the higher-resolution source. ([#483](https://github.com/jbourdin/expandedDecks/pull/483))

### Infrastructure

- **Removed PokéSprite build pipeline** — No more tarball download, `copy-webpack-plugin`, `assets/vendor/sprites/` directory, or build-time manifest generation. Sprites are served on demand via the proxy controller. ([#483](https://github.com/jbourdin/expandedDecks/pull/483))
- **`make sprites.sync`** — New Makefile target, integrated into `make fixtures`. ([#483](https://github.com/jbourdin/expandedDecks/pull/483))

---

## [1.8.9] — 2026-04-26

Standard format personal decks and printable A4 tournament decklist PDF.

### Features

- **Standard format personal decks (F2.23)** — `DeckFormat` enum (`Expanded`, `Standard`) with format selector on deck create/edit forms. Standard decks serve as a personal library: visible on show page and "Found this deck", printable labels work, but excluded from search indexes, public catalog, borrow workflow, and event registration. "Standard" badge on deck cards. ([#478](https://github.com/jbourdin/expandedDecks/pull/478))
- **Dedicated My Decks routes** — `/mydecks` (Expanded) and `/mydecks/standard` with format toggle, simplified filter bar (no owner/event filters), owner column hidden in card grid. All nav links updated. ([#478](https://github.com/jbourdin/expandedDecks/pull/478))
- **Printable A4 decklist PDF (F5.13)** — tournament-ready A4 PDF generated from deck card data via Dompdf. Two modes: personal (player name, ID, year of birth, Gravatar, trigram auto-filled) and anonymous (wide blank fields for handwriting). Pokemon section shows set symbol icons (fetched from TCGdex CDN). Trainer section sub-grouped by subtype. Localized card names from TCGdex database based on deck language, with English inline when different. Dynamic font sizing adapts to fit any deck on one A4 page. Route: `GET /deck/{short_tag}/decklist.pdf?anonymous=0|1` (owner-only). ([#480](https://github.com/jbourdin/expandedDecks/pull/480))
- **Year of birth on User entity** — optional `yearOfBirth` field editable via profile form, used on decklist PDFs. GDPR-compliant (cleared on anonymization, included in data export). ([#480](https://github.com/jbourdin/expandedDecks/pull/480))

### Testing & Quality

- **7 new tests** — `DeckFormat` enum convenience methods (3), `/mydecks` route access and filters (4). Test count: 1966 → 1975.

---

## [1.8.8] — 2026-04-26

Channel-scoped page search results.

### Bug Fixes

- **Channel-aware page search** — CMS pages from other channels no longer leak into search results. Added `channelCode` as a filterable attribute on the MeiliSearch pages index; both `SearchController` and `SearchApiController` now pass the current channel code so MeiliSearch filters pages by channel.

---

## [1.8.7] — 2026-04-26

Channel-aware search filtering and comprehensive search test coverage.

### Bug Fixes

- **Channel-aware search** — search results now respect channel feature flags. Archetypes/variants only appear on channels with `enableArchetypes`, decks on `enableDecks`, events on `enableEvents`. Pages always included. Both the search results page and navbar autocomplete API filter by channel. Template type filter tabs are conditionally rendered. ([#472](https://github.com/jbourdin/expandedDecks/pull/472))

### Infrastructure

- **Global dependency update** — Symfony 8.0.4–8.0.7 → 8.0.8 (30+ packages), PHPUnit 13.0 → 13.1.7, PHPStan 2.1 → 2.1.51, PHP-CS-Fixer 3.94 → 3.95.1, Twig 3.0 → 3.24, Sentry 5.9 → 5.10, Doctrine ORM 3.6 → 3.6.3, Flysystem 3.32 → 3.33, MeiliSearch PHP 1.16 → 1.16.1. No security advisories.

### Testing & Quality

- **80 new search tests** — comprehensive coverage of the full search stack: `SearchIndexerIntegrationTest` (17 tests with mocked MeiliSearch client verifying reindex orchestration and per-entity lifecycle), `SearchResultTest` (11), `SearchServiceTest` (18), `SearchRuntimeTest` (6), `SearchIndexListenerTest` (12), `SearchControllerTest` (7), `SearchApiControllerTest` (5), plus 7 additional `SearchIndexerTest` mapper tests. Test count: 1886 → 1966. ([#472](https://github.com/jbourdin/expandedDecks/pull/472))

---

## [1.8.4] — 2026-04-26

MeiliSearch full-text search engine, Open Graph meta tags, and archetype variant card-based search.

### Features

- **MeiliSearch search engine (F18.1)** — MeiliSearch sidecar process in Docker (local dev: Compose service, production: binary in container managed by Supervisor). Ephemeral index rebuilt from MySQL on cold start. `SearchIndexer` manages 5 indexes: archetypes, variants, pages, events, decks. Per-locale documents for translatable entities, Markdown/custom tag stripping, Doctrine entity listener for real-time sync. `app:search:reindex` console command. Health check at `/health/ready`. ([#469](https://github.com/jbourdin/expandedDecks/pull/469))
- **Global search results page (F18.2)** — `GET /{_locale}/search?q=&type=` with results grouped by content type, filter tabs, highlighted excerpts, and empty state. `SearchService` queries all indexes with locale filtering and ranking score threshold (0.3) to filter low-relevance results. ([#469](https://github.com/jbourdin/expandedDecks/pull/469))
- **Quick-search autocomplete (F18.3)** — `GET /api/search/quick?q=` JSON API + `NavbarSearch` React island (Mantine Combobox, 300ms debounce, type-grouped dropdown, keyboard navigation, "See all results" link). Visible to both authenticated and anonymous users. ([#469](https://github.com/jbourdin/expandedDecks/pull/469))
- **Archetype variant indexing** — variants index includes card names from current deck version, enabling card-based search (e.g. "Regidrago VSTAR" finds the variant that plays it). Variant results link to archetype page with `#{shortTag}` anchor. ([#470](https://github.com/jbourdin/expandedDecks/pull/470))
- **Open Graph and Twitter Card meta tags (F18.28)** — Full OG/Twitter Card tags on all public pages via `_partials/opengraph.html.twig`. Page-specific images, `twitter:card` type selection, consolidated from partial implementations. ([#464](https://github.com/jbourdin/expandedDecks/pull/464))

### Infrastructure

- **MeiliSearch Docker integration** — `docker-compose.yml` service with health check; Dockerfile copies binary from official image; Supervisor program at priority 5; entrypoint waits for health then reindexes. ([#469](https://github.com/jbourdin/expandedDecks/pull/469))
- **`make search.reindex`** — waits for MeiliSearch health before running; `make fixtures` calls it automatically. ([#469](https://github.com/jbourdin/expandedDecks/pull/469))
- **`MEILI_URL` env var** — uses `MEILI_` prefix (not `MEILISEARCH_`) to avoid Symfony CLI Docker integration overriding with `tcp://`. ([#469](https://github.com/jbourdin/expandedDecks/pull/469))

### Testing & Quality

- **16 new search tests** — `SearchIndexerTest` (Markdown stripping, constants), `SearchReindexCommandTest` (success, graceful skip), `HealthControllerTest` (MeiliSearch check). ([#469](https://github.com/jbourdin/expandedDecks/pull/469))

### Documentation

- **Installation docs** — MeiliSearch env vars (`MEILI_URL`, `MEILI_MASTER_KEY`).

---

## [1.8.3] — 2026-04-25

Locale-prefixed URL routing, hreflang tags, and locale switcher for editorial content.

### Features

- **Locale-prefixed routing (F18.29)** — editorial routes (archetypes, CMS pages) now use `/{_locale}/` prefix so each language has a distinct URL (e.g. `/en/archetypes/iron-thorns` vs `/fr/archetypes/iron-thorns`). Homepage gains localized routes (`/en/`, `/fr/`) alongside the session-based `/`. Non-editorial routes (decks, events, auth, admin) remain session-based. `LocaleListener` updated: route-level `_locale` is now the highest priority in locale resolution. ([#466](https://github.com/jbourdin/expandedDecks/pull/466))
- **Hreflang tags (F18.26)** — `<link rel="alternate" hreflang="...">` on all locale-prefixed pages via `{% block hreflang %}` in base template. Archetype templates override for cross-channel URL targeting. `x-default` points to English. ([#466](https://github.com/jbourdin/expandedDecks/pull/466))
- **Locale switcher** — navbar `EN | FR` toggle visible when channel supports multiple locales. Swaps `_locale` directly in the URL on editorial routes; uses `LocaleSwitchController` for session-based routes. Also persists user's `preferredLocale` if authenticated. ([#466](https://github.com/jbourdin/expandedDecks/pull/466))
- **OG locale meta tags** — `og:locale` and `og:locale:alternate` on all pages for social sharing platforms. ([#466](https://github.com/jbourdin/expandedDecks/pull/466))
- **301 redirects** — all legacy unprefixed editorial URLs redirect to `/en/` equivalents for SEO continuity. ([#466](https://github.com/jbourdin/expandedDecks/pull/466))

### Infrastructure

- **Sitemap** — editorial entries (homepage, pages, archetypes) now generated for each supported locale. ([#466](https://github.com/jbourdin/expandedDecks/pull/466))
- **robots.txt** — allow/disallow rules updated for locale-prefixed paths on both channels. ([#466](https://github.com/jbourdin/expandedDecks/pull/466))
- **Homepage canonical** — now points to `/en/` instead of `/`. ([#466](https://github.com/jbourdin/expandedDecks/pull/466))
- **postcss vulnerability** — bumped postcss to ≥8.5.10 to resolve GHSA-qx2v-qp2m-jg93 (moderate XSS). ([#466](https://github.com/jbourdin/expandedDecks/pull/466))

### Testing & Quality

- **4 new LocaleListener tests** — route locale priority, user preference override, unsupported locale fallback, channel constraint. ([#466](https://github.com/jbourdin/expandedDecks/pull/466))
- **7 new LocaleSwitchController tests** — locale switch, redirect, open redirect prevention, authenticated user preference update, unsupported locale 404. ([#466](https://github.com/jbourdin/expandedDecks/pull/466))

### Documentation

- **F18.26 and F18.29** — marked as Done in `docs/features.md`.
- **Localization docs** — new locale-prefixed routing section in `docs/technicalities/localization.md`.

---

## [1.8.2] — 2026-04-24

Channel-aware SEO foundation: dynamic sitemaps, robots.txt, canonical URLs, and JSON-LD structured data.

### Features

- **Dynamic sitemap (F18.23)** — controller-driven XML sitemap at `/sitemap.xml`, channel-aware. Single combined sitemap under 50K entries, sitemap index with per-type sub-sitemaps above that. Content channel includes homepage, CMS pages, and archetypes; app channel includes homepage, CMS pages, decks, and events. `Cache-Control: public, max-age=3600`. ([#459](https://github.com/jbourdin/expandedDecks/pull/459))
- **Channel-aware robots.txt (F18.24)** — dynamic `GET /robots.txt` replacing the static file. App channel allows `/deck/` and `/event`, blocks `/archetypes` (canonical on content channel) and auth paths. Content channel allows `/archetypes` and `/pages/`. Both include `Crawl-delay: 1` and `Sitemap:` directive. ([#460](https://github.com/jbourdin/expandedDecks/pull/460))
- **Canonical URLs (F18.25)** — self-referencing `<link rel="canonical">` on all public pages via `{% block canonical %}`. Archetypes resolve to content channel, decks/events to app channel, CMS pages to their assigned channel. Two new Twig functions: `canonical_url()` and `self_canonical_url()`. ([#461](https://github.com/jbourdin/expandedDecks/pull/461))
- **JSON-LD structured data (F18.27)** — `StructuredDataBuilder` service generating schema.org blocks: `WebSite` (homepage), `WebPage` (CMS), `Article` with `hasPart` for archetype variants, `Event` with cancelled status, `CreativeWork` for decks. `CollectionPage` + `ItemList` on catalog pages. Translated genre, headline, and variant descriptions (en + fr). ([#462](https://github.com/jbourdin/expandedDecks/pull/462))

### Documentation

- **F18 feature group** — added F18 section to `docs/features.md` with F18.23–F18.28 and new F18.29 (locale-prefixed routing).
- **F6.13 status** — marked as Done in `docs/features.md`.

---

## [1.8.1] — 2026-04-23

Production deployment fix for TCGdex sync workers.

### Infrastructure

- **Supervisor config** — added `worker-tcgdex-sync` program consuming all 4 sync transports (`tcgdex_sync_series`, `tcgdex_sync_serie`, `tcgdex_sync_set`, `tcgdex_sync_card`). Without this, sync messages dispatched in production were never consumed. ([#457](https://github.com/jbourdin/expandedDecks/pull/457))

### Documentation

- **Installation docs** — added new sync env vars (`TCGDEX_SYNC_*`), transports, and consume commands to the production deployment guide. ([#457](https://github.com/jbourdin/expandedDecks/pull/457))

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
