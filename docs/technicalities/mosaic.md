# Deck Mosaic Image Generation

> **Audience:** Developer, AI Agent · **Scope:** Technical Deep-Dive

← Back to [Documentation](../docs.md) | [Feature F6.6](../features.md)

---

## Overview

The mosaic is a **server-generated composite image** of a deck's card list, following the layout conventions used on Pokemon community websites. It is rendered in PHP (GD extension), stored via Flysystem, and served as a static image suitable for copy-paste and embedding.

**Feature reference:** `@see docs/features.md F6.6 — Visual deck list (card mosaic)`

---

## Layout

- **Grid:** fixed-width, 8 cards per row
- **Card order:** Pokemon → Trainer → Energy (no section headers, continuous flow); within each type: quantity descending, then name ascending
- **Background:** transparent (PNG alpha channel)
- **Quantity badge:** red hexagonal badge centered at the bottom of each tile, white text
- **Placeholder:** cards without an `imageUrl` render as a grey tile with the card name

---

## Generation Pipeline

```
DeckVersion created (F2.2 / F2.8)
    ↓
EnrichDeckVersionMessage dispatched
    ↓
CardEnricher fetches TCGdex images (async, deck_enrichment transport)
    ↓
All cards enriched → 2 messages dispatched:
    ├── GenerateDeckMosaicMessage    → original mosaic (uses DeckCard.imageUrl)
    └── GenerateMinifiedListMessage  → minified PTCGL text + pre-computed card views JSON
                                         ↓ (after CardPrinting rows populated)
                                      GenerateMinifiedMosaicMessage → minified mosaic
    ↓
MosaicGenerator renders composite image (GD)
    ↓
Image stored via Flysystem → shortTag-based URL saved on DeckVersion
    (e.g. /mosaic/AB3K7N/5.png, /mosaic/AB3K7N/5_minified.png)
```

The minified mosaic is **chained after** the minified list handler (not dispatched in parallel) because it depends on `CardPrinting` rows populated by `expandPrintings()` during minified list generation. Without this ordering, the minified mosaic may render with missing images.

### Re-dispatch (admin)

A `MosaicRedispatchService` finds deck versions that are fully enriched but have no `mosaicImageUrl`, and dispatches `GenerateDeckMosaicMessage` for each. Exposed in the technical admin (F7 area) as a dashboard card with count and "Generate" action button.

---

## Image Rendering (GD)

- **Input:** ordered list of `DeckCard` entities with `imageUrl` and `quantity`
- **Process:**
  1. Download card images from TCGdex (webp format, `/high.webp` URLs)
  2. Create canvas with transparent background
  3. Place card images in grid cells
  4. Overlay quantity badges (red hexagon, white text, centered bottom)
  5. Export as PNG or WebP
- **PHP extension:** GD (with webp, jpeg, png, freetype support)

---

## Storage (Flysystem)

Two adapters, selected via the `MOSAIC_STORAGE_ADAPTER` env var:

### Local (development)

- Adapter: `league/flysystem-local`
- Directory: `var/storage/mosaic/` (relative to project root, gitignored)
- Served via Symfony controller or web server alias in dev

### S3 (production)

- Adapter: `league/flysystem-aws-s3-v3`
- Provider: Scaleway Object Storage (S3-compatible)
- Credentials via env vars: `SCALEWAY_S3_BUCKET`, `SCALEWAY_S3_REGION`, `SCALEWAY_S3_ENDPOINT`, `SCALEWAY_S3_ACCESS_KEY`, `SCALEWAY_S3_SECRET_KEY`
- Public read access via `MOSAIC_PUBLIC_URL` (CDN-friendly base URL)

### File naming

**Storage path:** `mosaic/{deckId}/{versionId}.png` (numeric IDs, internal to Flysystem)
**Minified variant:** `mosaic/{deckId}/{versionId}_minified.png`

**Public URL:** `/mosaic/{shortTag}/{versionId}.png` — uses the deck's 6-character shortTag for human-readable, shareable URLs. The `MosaicController` resolves the shortTag to the deck ID for the Flysystem lookup. `MosaicUrlResolver::resolveForVersion()` generates these URLs from a `DeckVersion` entity.

---

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `MOSAIC_STORAGE_ADAPTER` | `local` | Storage adapter: `local` or `s3` |
| `MOSAIC_STORAGE_LOCAL_DIR` | `var/storage/mosaic` | Local storage directory (dev) |
| `SCALEWAY_S3_BUCKET` | — | S3 bucket name (prod) |
| `SCALEWAY_S3_REGION` | — | S3 region (e.g. `fr-par`) |
| `SCALEWAY_S3_ENDPOINT` | — | S3 endpoint URL (e.g. `https://s3.fr-par.scw.cloud`) |
| `SCALEWAY_S3_ACCESS_KEY` | — | S3 access key |
| `SCALEWAY_S3_SECRET_KEY` | — | S3 secret key |
| `MOSAIC_PUBLIC_URL` | — | Public base URL for mosaic images |

See also: [Production Installation](../installation.md) for the full env var reference.

---

## Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `ext-gd` | PHP 8.5 built-in | Image creation and manipulation |
| `league/flysystem` | ^3.32 | Filesystem abstraction |
| `league/flysystem-local` | ^3.31 | Local filesystem adapter |
| `league/flysystem-aws-s3-v3` | ^3.32 | S3-compatible storage adapter |
| `aws/aws-sdk-php` | ^3.373 | AWS/Scaleway S3 API client |

The GD extension is installed locally (macOS/Homebrew) and added to the production Dockerfile via `install-php-extensions gd`.
