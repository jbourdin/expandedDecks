# Card-Fan OG Image Builder

> **Audience:** Developer, AI Agent · **Scope:** Technical Deep-Dive

← Back to [Documentation](../docs.md) | [Feature F18.32](../features.md)

---

## Overview

The OG image builder is a **standalone admin tool** that composites a few card images into a social-preview PNG. Editors paste 2–6 card codes, the server renders the cards as a flat overlapping spread ("held hand" look) on a transparent 1200×630 canvas, stores the PNG, and returns a stable URL the editor copies into the `ogImage` field of a deck, archetype, or CMS page (F18.30 / F18.31).

**Feature reference:** `@see docs/features.md F18.32 — Card-fan OG image builder`

**Why it exists:** the natural OG fallbacks — a lone card image or the 60-card mosaic — read poorly as social previews. A lone card makes the page look like it is *about that card*; the mosaic is unreadable at preview size. A 3–4 card fan communicates "deck" at a glance.

---

## Pipeline

```
card codes (e.g. "SV08-128", one per line)
  → CardCodeResolver::resolve()           parse + TCGdex lookup → CardPrinting
  → CardFanImageGenerator::generate()     GD composite → PNG bytes (1200×630)
  → editor_upload.storage                 Flysystem write as {uuid}.png
  → app_editor_image_show                 existing serving route (30-day immutable cache)
```

### Code resolution — `App\Service\CardIdentity\CardCodeResolver`

- `parseCode()` holds the canonical code-parsing regex (`SET-NUMBER` with `-`, `_` or whitespace separators), extracted from the staple-card admin which now delegates to it.
- `resolve()` chains `TcgdexApiClient::findCard($setCode, $cardNumber)` → `CardIdentityResolver::resolveFromTcgdexCard()`. Both misses return `null` (no exceptions); resolution persists new `CardIdentity` / `CardPrinting` rows as a side effect, enriching the card database.
- Codes use **PTCG set codes** (`LOR`, `SV08`, `MEW`…), not TCGdex set IDs (`swsh11`, `sv03.5`) — `findCard()` translates via the set-mapping table.

### Compositing — `App\Service\OgImage\CardFanImageGenerator`

- Canvas: 1200×630 (the 1.91:1 ratio recommended for `og:image`), fully transparent — same GD alpha pattern as `MosaicGenerator::createCanvas()`. The transparency is deliberate: platforms composite OG images on their own backdrop, so the floating-card look adapts per platform. The background is a code constant, trivial to switch to solid/gradient if it reads poorly.
- Cards render at a fixed ~560px height (35px vertical margins), width derived from the mosaic tile ratio (245×342).
- Horizontal step is **adaptive**: `min(0.45 × cardWidth, (1100 − cardWidth) / (N − 1))`, so 2–6 cards always fit within an 1100px spread, centered. Cards draw left→right, so the rightmost card is fully visible on top.
- Image bytes come from `CardImageResolver::downloadImage($printing, 'high')` (full CDN fallback chain); on failure a neutral grey placeholder with the card name keeps positions stable.

### Storage & serving

No new infrastructure: the PNG is written as `{uuidv4}.png` to the `editor_upload.storage` Flysystem service (local or Scaleway S3 per env) and served by the existing `app_editor_image_show` route, whose filename regex (`[a-f0-9-]+\.(jpg|png|gif|webp)`) already accepts the UUID-based name. Files are immutable and cached for 30 days.

---

## Endpoints

| Route | Method | Purpose |
|-------|--------|---------|
| `/admin/og-image-builder` (`app_admin_og_image_builder`) | GET | Admin page hosting the React island |
| `/admin/og-image-builder/generate` (`app_admin_og_image_builder_generate`) | POST | JSON `{codes: string[]}` → `{url, cards: [{code, status, name}]}` |

Both gated `ROLE_CMS_EDITOR` **or** `ROLE_ARCHETYPE_EDITOR` (the editor-upload audience). The POST clamps to 2–6 codes (422 `invalid_card_count`), reports per-code resolution status, generates from the resolved subset when at least one code resolves, and returns 422 `no_card_resolved` when none do. Error payloads carry machine-readable keys; the frontend maps them to translated labels.

---

## CLI generation & dev fixture

`app:og-image:card-fan <codes...> [--deck=NAME]` (`GenerateCardFanOgImageCommand`) runs the same resolve → composite → store pipeline from the console and optionally assigns the resulting URL as a deck's `ogImage`. Unlike the endpoint, the filename is **deterministic** (md5 of the joined codes), so repeated runs overwrite the same file instead of accumulating orphans.

The `make fixtures` pipeline calls it after `tcgdex.import` (codes resolve from the local TCGdex database; only the card image downloads hit the CDN):

```
symfony console app:og-image:card-fan SIT-136 UPR-100 SFA-47 --deck=Regidrago
```

This gives the canonical **Regidrago** archetype variant a realistic card fan (Regidrago VSTAR, Dialga GX, Kyurem) in dev, visible in the archetype page's `og:image` and the variants feed's `media:content`. It is deliberately **not** part of `DevFixtures` — functional tests load those fixtures and must not hit the network.

---

## Frontend

`assets/components/OgImageBuilder.tsx` (Mantine island, entry `og_image_builder`): textarea (one code per line) → Generate → per-code status `Badge` chips, preview `<img>` on a CSS checkerboard (makes the transparency visible), and a `CopyButton` that copies the **absolute** URL (`new URL(url, window.location.origin)`). Labels arrive via `data-*` attributes from `templates/admin/og_image_builder/index.html.twig` (`app.admin.og_image_builder.*` keys) — same pattern as `_image_url_field.html.twig`, no react-i18next involvement.

---

## RSS feed integration

The OG images surface beyond social crawlers: both RSS feeds emit each item's OG image as `<media:content medium="image">` (Media RSS namespace — chosen over RSS core `<enclosure>` which requires a byte-size `length` attribute we cannot know for arbitrary URLs):

- **Archetype variants feed** (F21.2): only an `ogImage` set on the variant **itself** — deliberately no `OgMetaResolver` fallback chain here. The mosaic is too large and irrelevant as a feed thumbnail, and the archetype-level image would repeat on every variant; the element is simply omitted until an editor sets a fan on the variant.
- **Page category feeds** (F21.1): `OgMetaResolver::resolveForPage()` — `PageTranslation.ogImage` → `Page.ogImage`; the element is omitted when neither is set.

URLs are absolutized with `channel_absolute_url()` (feed readers, like social crawlers, don't resolve relative URLs).
