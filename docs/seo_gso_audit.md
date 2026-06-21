# SEO, GSO & Content Discovery Audit

> **Audience:** Developer, CTO · **Scope:** Audit / Point-in-time

← Back to [Documentation](docs.md) | [Issue #296 (F19.3)](https://github.com/jbourdin/expandedDecks/issues/296)

> **Versions:** v1 2026-06-20 (initial) · v2 2026-06-20 (deep pass — C1 fixed, live re-probe post-fix)
> **Companion briefs:** [bunny_cdn_host_cache_brief.md](bunny_cdn_host_cache_brief.md) · [google_search_console_brief.md](google_search_console_brief.md)

This document audits the search-engine optimization (SEO), generative-engine optimization
(GSO — visibility in AI answer engines such as Google AI Overviews, ChatGPT Search,
Perplexity, Gemini), and overall content discoverability of the Expanded Decks platform. It
combines a read of the production code with live inspection of both production deployments.

**v2 was produced after the critical robots.txt issue (C1) was fixed**, and goes deeper:
per-page rendered-head inspection, locale/sitemap consistency, JSON-LD validation, delivery
performance (HTTP version, compression, caching), security headers, 404 behaviour, heading
structure, image alt coverage, and the internal-link/crawl graph.

---

## 1. Architecture context: two channels, two domains

One Symfony codebase serves two **channels**, resolved per request from the `Host` header
(`App\Service\Channel\ChannelContext`):

| Channel | Domain | Primary content | Flags |
|---------|--------|-----------------|-------|
| **App channel** | `expandeddecks.app` | Deck library, event borrowing/lending | `enableArchetypes=false` |
| **Content channel** | `dowsingmachine.com` | Archetype catalogue, news/guides (CMS), getting-started | `enableArchetypes=true`, `locales=['en']` |

`dowsingmachine.com` is the discovery-critical surface. Content is authored by Stéphane
Ivanoff ("Luby"), a two-time Pokémon TCG International Champion — an authority asset that
matters for E-E-A-T (SEO) and citability (GSO), referenced throughout.

---

## 2. Executive summary

The platform has a **strong, modern SEO foundation** and **good delivery performance**.
Measured on production (2026-06-20, post-fix): HTTP/2, Brotli compression, TTFB ~260 ms,
OG images a clean 1200×630, static assets cached 30 days, valid JSON-LD, correct 404s, and
301 redirects for legacy URLs.

The **critical blocker from v1 (C1) is resolved**: `dowsingmachine.com` now serves its own
channel's `robots.txt` (allows `/archetypes`, advertises its own sitemap). See §3.

The remaining work is concentrated in **markup honesty and authority signalling** — the
levers that drive both rich SERP presentation and answer-engine citation:

1. **Locale/sitemap dishonesty (H1):** ✅ **resolved in v1.14.5 (F19.4)** — the sitemap,
   `og:locale:alternate`, and robots now derive from `Channel::getLocales()`, so the
   English-only content channel no longer advertises the ~42 `/fr/` duplicate URLs.
2. **Missing meta descriptions (H2):** only archetype pages emit `<meta name="description">`
   (and only conditionally). Homepage, CMS pages, decks, events, and all list pages have none.
3. **Author authority invisible to machines (M1):** the championship-level human authorship is
   prose-only; structured data attributes content to a generic `Organization`.
4. Plus the open #296 items: pagination `rel`/unpaginated growing lists (M2), HTML edge
   caching (M3), soft-delete 404s (M5); and new this pass: **no security/trust headers (M6)**
   and **flat heading hierarchy on content pages (M7)**.

---

## 3. RESOLVED since v1

### ✅ C1 — robots.txt cross-domain CDN cache contamination

**Was:** `dowsingmachine.com/robots.txt` served the App channel's ruleset
(`Disallow: /archetypes`, wrong-domain `Sitemap:`) because a single Bunny pull zone cached
`robots.txt` with a host-blind cache key. Fixed via OpenTofu (hostname-varied cache key).

**Verified 2026-06-20 (post-fix):**
- `dowsingmachine.com/robots.txt` → `Allow: /en/archetypes`, `Allow: /fr/archetypes`,
  `Sitemap: https://dowsingmachine.com/sitemap.xml`. ✅
- `expandeddecks.app/robots.txt` → retains its `Disallow: /archetypes` ruleset and its own
  sitemap. ✅
- The two responses are now **distinct cache objects** (different `cdn-edgestorageid`:
  1220 vs 1074; different bodies). ✅

**Follow-up (not code):** submit each sitemap in its own Search Console property and request
indexing of archetype/news URLs that were crawl-blocked while the bug was live — see
[google_search_console_brief.md](google_search_console_brief.md).

---

## 4. Methodology (v2)

- **Code review** of sitemap/robots/structured-data services and the public Twig templates
  (`base.html.twig`, `_partials/opengraph.html.twig`, per-page head blocks), plus the public
  controllers and the repository queries behind listing pages.
- **Live production inspection** (2026-06-20, post-fix) via `curl`: `robots.txt`,
  `sitemap.xml`, response headers, and rendered `<head>`/`<body>` of the homepage, archetype
  list + multiple archetype detail pages (`/en/` and `/fr/`), and CMS pages. JSON-LD blocks
  extracted and parsed. OG image downloaded and dimension-checked. Performance, compression,
  caching, security headers, and 404 behaviour measured directly.

---

## 5. Findings (severity-ranked)

### 🟠 HIGH

#### H1 — English-only content advertised as bilingual (sitemap ~50 % duplicate URLs)

> ✅ **RESOLVED** in v1.14.5 (F19.4 — issue [#695](https://github.com/jbourdin/expandedDecks/issues/695), PR [#706](https://github.com/jbourdin/expandedDecks/pull/706)): the sitemap,
> `og:locale:alternate`, and robots.txt locale rules now derive from `Channel::getLocales()`. The content
> channel is English-only, so the `/fr/` duplicates are gone; adding `fr` later reactivates the French signals
> with no code change. The finding below is retained for context.

**Evidence (production):**
- `/fr/archetypes/adp` returns **HTTP 200 with byte-identical content** to `/en/archetypes/adp`
  (both 71 169 bytes, identical English `<title>` and body), with `<html lang="en">` and
  `<link rel="canonical" href="…/en/archetypes/adp">`. Same for `/fr/pages/*`.
- The sitemap lists **86 URLs: 34 `/en/archetypes/` + 34 `/fr/archetypes/` + 8 `/en/pages/` +
  8 `/fr/pages/` + 2 homepages.** The 42 `/fr/` URLs are duplicate English content
  canonicalizing to `/en/`.
- `og:locale:alternate` is hardcoded to `fr_FR` regardless of channel locale
  (`_partials/opengraph.html.twig`).

**Root cause:** `SitemapGenerator` hardcodes `['en','fr']` while the content channel's
`Channel.locales` is correctly `['en']` (default; never overridden for this channel). The
sitemap and OG locale signals ignore the channel's real locale set. The hreflang block, by
contrast, *does* read `current_channel().locales` — which is why archetype pages correctly
emit only `hreflang="en"` + `x-default`, and CMS pages (inheriting `base.html.twig`'s
`locales|length > 1` guard) emit none. So hreflang is *correct*; the sitemap/OG signals are
the liars.

**Why it matters:** No duplicate-content penalty (canonical handles it), but every crawler —
including answer-engine crawlers — spends ~half its budget on `/fr/` URLs that resolve to
nothing new, and the contradictory "French exists" signals muddy locale targeting. Sitemaps
should contain only canonical URLs.

**Fix:** Drive the sitemap's locale loop and `og:locale:alternate` from
`current_channel().locales` instead of a hardcoded list. The content channel then emits only
`/en/` URLs (sitemap drops to ~44 canonical URLs) and no false French alternate. The day real
French translations are authored and `fr` is added to the channel, both light up
automatically. Optionally also 301 `/fr/*` → `/en/*` on English-only channels rather than
serving a 200 duplicate.

#### H2 — No `<meta name="description">` on most pages

**Evidence (code + production):** Only `archetype/show.html.twig` emits a description, and only
when `archetype.localizedMetaDescription(locale)` is set. `home/index`, `page/show` (sets only
`robots noindex` when applicable), `deck/show`, `event/show`, and all list templates have no
`{% block meta %}` description; `base.html.twig`'s block is empty — **no fallback**. Confirmed
live: homepage and CMS pages render zero description tags; the sampled archetype rendered none
either (no localized description set).

**Why it matters:** Search engines synthesise snippets from body text when the tag is missing
— lower-quality, less clickable SERP entries — and answer engines lose a curated one-line
summary anchor.

**Fix:** Emit `<meta name="description">` on every indexable page from a fallback chain
(explicit field → `OgMetaResolver` description → trimmed body excerpt → channel default),
centralised in `base.html.twig` so nothing ships without one.

---

### 🟡 MEDIUM

#### M1 — Author / E-E-A-T authority invisible to machines (key GSO gap)

> ✅ **RESOLVED** by F19.8 ([#699](https://github.com/jbourdin/expandedDecks/issues/699)): content now
> carries an `author` (and per-locale `translator`); `Article`/`WebPage` JSON-LD emit a `Person` author
> with `url`/`sameAs`/credential plus an `Organization` publisher with `logo`+`sameAs`, the RSS
> `dc:creator` is dynamic, and a visible byline renders on archetype/news pages — all via a projection
> that never exposes the login email or legal name. The finding below is retained for context.

**Evidence (live JSON-LD, archetype page):** valid `Article` with `headline`, `about` (Game),
`datePublished`/`dateModified` (good freshness), `hasPart` (variants) — but
`author = { @type: Organization, name: "Dowsing Machine" }` and the same as `publisher`. No
`Person`, no credentials, no `sameAs`. The archetype RSS `dc:creator` is the hardcoded string
`"Luby"`. The footer links Bluesky/Discord/GitHub, but none are in the entity graph.

**Why it matters:** A two-time International Champion authoring the content is precisely the
E-E-A-T signal Google's helpful-content systems and answer engines reward — but only if it's
machine-readable. As-is, engines see "some site," not a nameable expert source to cite.

**Fix:** Emit `author = { @type: Person, name, url, sameAs: [bsky, discord, …],
description: "2× Pokémon TCG International Champion" }`; add an `Organization` (with `logo`,
`sameAs`) as `publisher`; surface a visible byline + credential line on archetype/news pages.

#### M2 — Pagination: no `rel`, and growing lists are unpaginated (#296 AC)

- **0/4 listing pages emit `<link rel="prev">`/`<link rel="next">`.**
- `DeckCatalogController` (PER_PAGE 12) and `PageController::category` (PER_PAGE 10) paginate
  via `?page=N`. The deck list correctly self-canonicalizes without the page param;
  `page/category.html.twig` has **no canonical block at all**.
- **`ArchetypeCatalogController` and `EventListController` do not paginate** — they render
  *all* results. For the archetype catalogue (the flagship list) this compounds with the N+1
  risk below and will degrade as the catalogue grows.

**Fix:** Add `rel=prev/next` + per-page self-canonical to paginated lists; introduce
pagination on the archetype and event lists before they grow further.

#### M3 — Public HTML never edge-cached (#296 AC)

Every HTML response on both domains is `cache-control: no-cache` / `cdn-cache: MISS` — every
crawl and first-time visit hits PHP origin. TTFB is fine today (§6), but session-less public
pages (homepage, archetype detail, CMS pages) are good candidates for a short host-keyed
`s-maxage` (300–600 s) + `stale-while-revalidate` + `ETag`, never caching authenticated
responses. (The hostname-varied cache key from the C1 fix already makes this safe to do.)

#### M4 — GSO provisions and homepage entity graph are thin

- No `llms.txt` / `llms-full.txt` / `/.well-known/ai.txt` (all 404). For a curated
  niche-format knowledge base, an `llms.txt` summarising Expanded and linking the canonical
  guides/archetypes is high-leverage, low-effort.
- Homepage JSON-LD is `WebSite { name, url }` only — **no `potentialAction` (SearchAction /
  sitelinks search box)** and **no `publisher`/`Organization` with `logo`**.
- **AI-crawler policy is implicit:** `robots.txt` is `User-agent: *` only, so GPTBot /
  ClaudeBot / PerplexityBot / Google-Extended are currently allowed (good for GSO). This
  should be an *explicit* decision — see §9.

#### M5 — Soft-deleted detail routes don't all 404 (#296 AC)

- Archetype (`ArchetypeDetailController:48` checks `getDeletedAt()`) and Page (filtered in
  `PageRepository::findBySlug`) correctly 404. ✅ Verified live: unknown slugs → 404; legacy
  `/archetypes` → 301 → `/en/archetypes`. ✅
- **Deck** (`DeckShowController`) and **Event** (`EventController::show`) have **no explicit
  `deletedAt` guard** — they use `MapEntity` binding and only gate on visibility. A
  soft-deleted deck/event reachable by direct URL may render (200) instead of 404.

**Fix:** Add `deletedAt`-is-null → 404 guards to deck and event detail; add functional tests
across all four public detail routes.

#### M6 — No security / trust response headers (new this pass)

Homepage returns **none** of: `Strict-Transport-Security`, `X-Content-Type-Options`,
`X-Frame-Options`, `Content-Security-Policy`, `Referrer-Policy`, `Permissions-Policy`. These
aren't direct ranking factors, but HSTS and `X-Content-Type-Options: nosniff` are baseline
hygiene/trust signals (and a CTO-level security gap worth closing independently of SEO). Cheap
to add at the Bunny edge or in Symfony. *(Flagging proactively per the security/legal remit.)*

#### M7 — Flat heading hierarchy on content pages (new this pass)

The sampled archetype page has **exactly one `<h1>` and zero `<h2>`/`<h3>`** — a flat outline
despite rich sectioned content (overview, multiple variants, card discussion). A clear
`h2`/`h3` structure materially helps both accessibility and content extraction by answer
engines (which chunk by heading). Add semantic subheadings to archetype/news body content.

---

### 🟢 LOW / polish

- **L1 — Inconsistent `<article>` wrapping** — present on archetype/CMS/banned/staple, absent
  on deck/event detail.
- **L2 — OG image weight** — correct 1200×630 PNG, but ~1.16 MB; consider WebP/optimization
  (cosmetic; dimensions already meet the #296 AC).
- **L3 — Image alt coverage** — archetype page: 9 images, 7 with alt, 1 empty, 1 missing.
  Minor; ensure meaningful alts on content imagery.
- **L4 — `twitter:site`/`twitter:creator` absent** — card renders fine; adding a handle
  improves attribution (note: site uses Bluesky, so this may be N/A).
- **L5 — Staple-cards list** lacks a `structured_data` block (other lists emit
  `CollectionPage`+`ItemList`).
- **L6 — Social validation pending (#296 AC)** — markup is correct; run key pages through the
  Facebook / X / LinkedIn / Discord validators to prime caches and confirm real-world unfurls.

---

## 6. What is strong (measured, keep)

| Area | Status | Evidence (2026-06-20) |
|------|--------|------------------------|
| **robots.txt** | ✅ Channel-correct post-fix | Per-domain rulesets + own sitemap |
| **Sitemaps** | ✅ Per-channel, `lastmod` present | 84/86 URLs carry `lastmod` (homepages excepted) — but see H1 on `/fr` duplication |
| **Canonical** | ✅ Correct, incl. `/fr`→`/en` consolidation | `canonical_url`/`self_canonical_url` |
| **hreflang** | ✅ Honest (en + x-default; none where single-locale) | Reads `channel.locales` |
| **JSON-LD** | ✅ Valid, parses; good freshness dates | `Article` w/ `datePublished`/`dateModified`/`hasPart` |
| **Open Graph / Twitter** | ✅ Complete; OG image 1200×630 | `summary_large_image` |
| **404 / redirects** | ✅ | Unknown slugs→404; legacy→301 |
| **Performance** | ✅ | HTTP/2, Brotli, TTFB ~260 ms, assets cached 30 d |
| **RSS feeds** | ✅ | Page-category + archetype-variant feeds |
| **URL design** | ✅ | Human-readable archetype/page slugs |

---

## 7. Performance & crawl-efficiency (measured 2026-06-20, post-fix)

| Signal | Result |
|--------|--------|
| HTTP version | HTTP/2 ✅ |
| Compression | Brotli (`content-encoding: br`) ✅ |
| Homepage TTFB / total | ~0.26 s / ~0.26 s ✅ (target <500 ms) |
| Archetype detail TTFB | ~0.30 s (origin upstream ~250 ms) ✅ |
| Static assets (`/build/*`) | `cache-control: public, max-age=2592000` (30 d) ✅ |
| Public HTML edge cache | `no-cache` / `cdn-cache: MISS` — uncached (M3 opportunity) ⚠️ |
| OG image | 1200×630 PNG ✅ (≈1.16 MB — L2) |

**Not yet performed:** Symfony-profiler N+1 confirmation on listing pages. Code review flags
two real risks: the **archetype list** (`localizedName()` over the `translations` collection +
`playstyleTags` per row, on an *unpaginated* query) and **archetype detail variant building**
(`latestSet` / `pokemonSlugs` per variant, not eager-joined). Recommend a profiler pass on
`/en/archetypes` and an archetype detail page; add `addSelect` fetch joins where confirmed.

---

## 8. Issue #296 (F19.3) acceptance-criteria status

| Acceptance criterion | Status | Reference |
|----------------------|--------|-----------|
| Paginated listings include `rel="prev"`/`rel="next"` | ❌ 0/4; 2 lists unpaginated | M2 |
| Soft-deleted content 404s across all public detail routes | ⚠️ archetype/page ✅; deck/event ✗ | M5 |
| Acceptable TTFB (<500 ms) and no N+1 on listings | ⚠️ TTFB ✅ (~260 ms); N+1 risks flagged, not profiled | §6, §7 |
| HTTP cache headers reviewed/added on public HTML | ⚠️ Reviewed — `no-cache`; edge caching recommended | M3 |
| Social previews render on major platforms | ⚠️ Markup ✅, OG 1200×630 ✅; platform validation pending | L6 |

**Beyond #296 scope** (surfaced by this audit): C1 (fixed), H1 (locale/sitemap honesty), H2
(meta descriptions), M1 (E-E-A-T entity graph), M4 (GSO provisions / AI-crawler policy), M6
(security headers), M7 (heading hierarchy).

---

## 9. Recommended action plan (priority order)

1. **Search Console** — verify both properties, submit each sitemap, confirm the C1 fix
   restored archetype indexing, monitor coverage. See companion brief.
2. ~~**H1 — locale honesty**~~ — ✅ **done in v1.14.5 (F19.4)**: sitemap, `og:locale:alternate`,
   and robots now derive from `channel.locales`; the content channel's ~42 `/fr/` duplicate URLs
   are gone.
3. **H2 — meta descriptions** — centralised fallback chain in `base.html.twig`.
4. **#296 core** — `rel=prev/next` + per-page self-canonical (M2), paginate the archetype &
   event lists, deck/event soft-delete 404 guards (M5), profiler N+1 pass (§7).
5. **M1 / M4 — authority & GSO** — `Person`/`Organization` + `sameAs` markup and visible
   bylines; `llms.txt`; homepage `SearchAction` + `publisher`; decide AI-crawler policy.
6. **M3 — edge caching** — host-keyed `s-maxage` + `stale-while-revalidate` + `ETag` on
   session-less public HTML.
7. **M6 — security headers** — HSTS, `nosniff`, `X-Frame-Options`, `Referrer-Policy` (+ CSP if
   feasible).
8. **Polish (M7, L1–L6)** — heading hierarchy, `<article>` wrapping, OG image weight, alt
   coverage, staple structured data, social validation.

---

## 10. Open questions for the user

- **AI-crawler policy (M4):** allow all answer-engine crawlers for maximum GSO visibility, or
  restrict training-oriented crawlers (`Google-Extended`, `GPTBot`)? Product/legal choice.
- **French (H1):** confirm the site is intentionally English-only for now. If so, stop
  advertising `fr` (fix H1); if French content is planned, prioritise authoring it and add
  `fr` to the channel so the existing infrastructure activates.
- **Authorship model (M1):** single configured `Person` (Luby / Stéphane Ivanoff) now, or a
  multi-author model? The interim hardcoded `dc:creator = "Luby"` suggests the former
  short-term.
- **GDPR note:** exposing author identity + public social profiles in structured data is fine;
  a future multi-author model storing contributor PII would warrant a quick processing review.
