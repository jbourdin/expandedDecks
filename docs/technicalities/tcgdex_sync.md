# TCGdex Incremental Sync

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Operations

← Back to [Documentation](../docs.md) · Related: [Card Enrichment](enrichment.md) · [TCGdex Known Issues](tcgdex_known_issues.md)

---

## Overview

The incremental sync replaces the monolithic `app:tcgdex:import` (which clones the tcgdex/cards-database git repo) with an **API-based cascade** that detects new or changed data and pulls only what is missing. It runs fully async via Symfony Messenger with dedicated Doctrine transports.

**Feature ID:** F6.13 · **Parent issue:** [#411](https://github.com/jbourdin/expandedDecks/issues/411)

---

## Architecture

### Message Cascade

```
SyncTcgdexSeriesMessage (root trigger)
  ├─ GET /v2/en/series → detect missing/existing series (exclude tcgp)
  ├─ Create missing TcgdexSerie entities (with logoUrl)
  ├─ For each serie (sorted by releaseDate DESC → newest first):
  │     dispatch SyncTcgdexSerieMessage
  │     ├─ GET /v2/en/series/{id} → detect missing sets, changed card counts
  │     ├─ Create missing TcgdexSet entities (with logoUrl + symbolUrl)
  │     ├─ For each new/changed set (sorted by releaseDate DESC):
  │     │     dispatch SyncTcgdexSetMessage
  │     │     ├─ GET /v2/en/sets/{id} → detect missing cards
  │     │     ├─ Update TcgdexSet metadata (releaseDate, ptcgCode, cardCount)
  │     │     └─ For each missing card: dispatch SyncTcgdexCardMessage
  │     │           └─ GET /v2/en/cards/{id} → persist full TcgdexCard (with imageBaseUrl)
  │     └─ ...
  └─ dispatch SyncTcgdexCompleteMessage (delayed 10 min)
        ├─ Rebuild set mappings (BuildSetMappingsMessage)
        └─ Re-enrich failed deck versions (EnrichDeckVersionMessage)
```

### Sync Modes

The `SyncMode` enum propagates through all messages in the cascade:

| Mode | Series & Sets | Existing set metadata | Existing card image URLs | Existing card data |
|------|--------------|----------------------|--------------------------|-------------------|
| **Insert** (default) | Create missing only | Skip | Skip | Skip |
| **Update** | Create missing + update logos | Refresh (releaseDate, ptcgCode, etc.) | Update from `/sets/{id}` response (no per-card API call) | Skip |
| **Full** | Create missing + update logos | Refresh | Re-fetch via `/cards/{id}` | Re-fetch and overwrite all fields |

**Insert** is designed for periodic automated runs (serverless cron) — lightweight, only fetches genuinely new data.

**Update** is the sweet spot for backfilling image URLs and refreshing metadata. The `/sets/{id}` response includes `image` for every card in the set, so updating 20,000 card image URLs costs ~420 API calls (1 series + 20 series details + ~400 set details) instead of 20,000.

**Full** re-fetches every card individually and should only be run from the CLI with `--force`. It is useful for correcting data quality issues or after a TCGdex API schema change.

### Ordering: Newest First

At every level of the cascade, entries are sorted by **release date descending** so the most recent content is synced first. This ensures users benefit from new set releases as quickly as possible, even during a long full sync.

### Transports

Each cascade level has its own Doctrine transport for independent monitoring:

| Transport | Queue name | Messages |
|-----------|-----------|----------|
| `tcgdex_sync_series` | `tcgdex_sync_series` | `SyncTcgdexSeriesMessage` |
| `tcgdex_sync_serie` | `tcgdex_sync_serie` | `SyncTcgdexSerieMessage` |
| `tcgdex_sync_set` | `tcgdex_sync_set` | `SyncTcgdexSetMessage` |
| `tcgdex_sync_card` | `tcgdex_sync_card` | `SyncTcgdexCardMessage` |

`SyncTcgdexCompleteMessage` routes to `deck_enrichment` (not the sync transports) since it triggers downstream enrichment.

All sync transports use `max_retries: 0` — retry is handled by the handlers themselves via the throttle + redispatch pattern (see below).

Run all sync workers: `make worker.sync`

Monitor queue depths: `symfony console messenger:stats`

---

## Rate Limiting & Cooldown

`TcgdexApiThrottle` (filesystem-backed cache, shared across workers) enforces:

- **Minimum delay** between API calls (default: 200ms, configurable via `TCGDEX_SYNC_DELAY_MS`)
- **Consecutive failure tracking**: after N failures in a row (default: 3, via `TCGDEX_SYNC_FAILURE_THRESHOLD`), enter cooldown
- **Cooldown**: pause all API calls for N seconds (default: 300, via `TCGDEX_SYNC_COOLDOWN_SECONDS`)

### Throttle + Redispatch Pattern

Every API-calling handler follows this pattern:

```php
$this->throttle->waitIfNeeded();  // Sleeps if in cooldown or within delay window

try {
    $response = $this->tcgdexClient->request('GET', $url);
} catch (\Throwable $exception) {
    $this->throttle->reportFailure();
    $this->messageBus->dispatch($message, [new DelayStamp(60000)]);  // Retry in 60s
    return;
}

$this->throttle->reportSuccess();  // Reset failure counter
```

On HTTP error: the handler logs the error, reports a failure to the throttle, and **redispatches the same message** with a 60-second delay. The message re-enters the queue with a future `available_at` timestamp. No messages reach the dead-letter queue.

**Exception: 404 on cards.** A 404 from `/cards/{id}` means the card genuinely doesn't exist in TCGdex (e.g. unreleased promo). These are logged as warnings but not retried.

---

## Change Detection

| Level | Detection | Action |
|-------|-----------|--------|
| Serie | Serie ID not in `tcgdex_serie` | Create entity, dispatch `SyncTcgdexSerieMessage` |
| Serie (existing) | Always synced | Dispatch `SyncTcgdexSerieMessage` (sets may have changed) |
| Set | Set ID not in `tcgdex_set` | Create entity, dispatch `SyncTcgdexSetMessage` |
| Set (existing, insert mode) | `cardCount.total` from API differs from `TcgdexCardRepository::countBySetId()` | Dispatch `SyncTcgdexSetMessage` (new cards) |
| Set (existing, update/full mode) | Always synced | Dispatch `SyncTcgdexSetMessage` |
| Card | Card ID not in `tcgdex_card` | Dispatch `SyncTcgdexCardMessage` |

### Card Count Comparison

The serie API response returns `cardCount` as a nested object: `{"official": 162, "total": 218}`. The `total` field includes secret rares and promos. The handler compares `cardCount.total` against the local `COUNT(*)` for the set. A mismatch means new cards have been added (common for promo sets that grow over time).

---

## Asset Images

Each entity level stores image/logo URLs from the API:

| Entity | Fields | Source |
|--------|--------|--------|
| `TcgdexSerie` | `logoUrl` | `logo` from `/series` and `/series/{id}` |
| `TcgdexSet` | `logoUrl`, `symbolUrl` | `logo`, `symbol` from `/sets/{id}` |
| `TcgdexCard` | `imageBaseUrl` | `image` from `/cards/{id}` or `/sets/{id}` card list |

`TcgdexCard::getImageUrl()` prefers `imageBaseUrl` when set, falling back to a computed URL from `serie/set/localId`. The `imageBaseUrl` is the authoritative source from TCGdex — the computed fallback exists for legacy data imported before the sync feature.

During enrichment, `CardEnricher::resolveImageUrl()` and `CardIdentityResolver::resolveFromTcgdexCard()` both prefer the API-sourced image URL over computed/fallback URLs, avoiding expensive HTTP reachability checks.

---

## Triggers

### CLI Command

```bash
symfony console app:tcgdex:sync                    # Insert mode (default)
symfony console app:tcgdex:sync --mode=update       # Update metadata + image URLs
symfony console app:tcgdex:sync --mode=full --force # Re-fetch everything (dangerous)
```

Reports current queue depth and last sync timestamp. Warns if a sync is already in progress.

### Admin Dashboard

The technical admin dashboard (`/admin/technical`) has a "TCGdex Database Sync" card with:
- Last sync timestamp and queue depth badges
- Cooldown status indicator
- **"Sync new data"** button (insert mode)
- **"Sync & update metadata"** button (update mode)
- Buttons disabled when a sync is in progress

### Webhook

```
POST /webhook/tcgdex-sync
```

Anonymous endpoint protected by HMAC-SHA256 signature. Designed for periodic serverless cron jobs.

- Header: `X-Sync-Signature: sha256=<hex>`
- Secret: `TCGDEX_SYNC_WEBHOOK_SECRET` env var (empty = endpoint disabled, returns 404)
- Always dispatches insert mode
- Idempotent: returns 200 if sync already in progress
- Returns: 202 (dispatched), 403 (invalid signature), 404 (not configured), 200 (already running)

Example:
```bash
BODY='{"trigger":"cron"}'
SIG=$(echo -n "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')
curl -X POST https://app.example.com/webhook/tcgdex-sync \
  -H "Content-Type: application/json" \
  -H "X-Sync-Signature: sha256=$SIG" \
  -d "$BODY"
```

---

## Post-Sync Actions

`SyncTcgdexCompleteMessage` is dispatched by the series handler with a 10-minute delay (to allow the cascade to finish). The handler:

1. Records the sync completion timestamp (filesystem cache)
2. Dispatches `BuildSetMappingsMessage` — rebuilds the PTCG code ↔ TCGdex set ID mapping table
3. Finds all `DeckVersion` entities with `pending` or `failed` enrichment status and dispatches `EnrichDeckVersionMessage` for each

This ensures that newly synced cards are immediately available for deck enrichment.

---

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `MESSENGER_TRANSPORT_TCGDEX_SYNC_DSN` | `doctrine://default?auto_setup=0` | Doctrine transport DSN for sync queues |
| `TCGDEX_SYNC_DELAY_MS` | `200` | Minimum milliseconds between API calls |
| `TCGDEX_SYNC_FAILURE_THRESHOLD` | `3` | Consecutive failures before cooldown |
| `TCGDEX_SYNC_COOLDOWN_SECONDS` | `300` | Cooldown duration in seconds |
| `TCGDEX_SYNC_WEBHOOK_SECRET` | `dev-tcgdex-sync-secret` | HMAC secret for webhook (empty = disabled) |

---

## Edge Cases

- **`tcgp` serie excluded** — Pokemon TCG Pocket is a separate game; its cards are filtered out at the series level.
- **English-only data** — The REST API returns English-only card names, abilities, and attacks. The hydrator wraps these into multilingual format (`["en" => "..."]`) so `getLocalizedName('en')` and the generated `name_en` column work identically to NDJSON-sourced cards. French translations are not available from the API.
- **Dotted set IDs** (e.g. `sm3.5`) — The computed image URL fails for these because TCGdex CDN strips the dot. The `imageBaseUrl` from the API provides the correct URL.
- **Promo sets grow over time** — Sets like `svp` (SV promos) gain new cards throughout a generation. The card count comparison in insert mode detects this automatically.
- **Card hydration sources** — `TcgdexCardHydrator` has two methods: `hydrateFromNdjsonRecord()` for the git-based import (multilingual, no image URL) and `hydrateFromApiResponse()` for the API sync (English-only, with `imageBaseUrl`). Both produce identical `TcgdexCard` entities.

---

## Key Files

| File | Purpose |
|------|---------|
| `src/Enum/SyncMode.php` | Insert/Update/Full mode enum |
| `src/Message/SyncTcgdex*.php` | 5 message classes for the cascade |
| `src/MessageHandler/SyncTcgdex*Handler.php` | 5 handler classes |
| `src/Service/Tcgdex/TcgdexApiThrottle.php` | Rate limiting with cooldown |
| `src/Service/Tcgdex/TcgdexCardHydrator.php` | Card entity hydration (NDJSON + API) |
| `src/Service/Tcgdex/TcgdexSyncStatusService.php` | Queue depth + last sync tracking |
| `src/Command/SyncTcgdexCommand.php` | CLI trigger (`app:tcgdex:sync`) |
| `src/Controller/WebhookTcgdexSyncController.php` | Signed webhook endpoint |
| `src/Controller/AdminTechnicalController.php` | Admin dashboard actions |
| `config/packages/messenger.yaml` | Transport + routing config |
