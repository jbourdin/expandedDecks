# TCGdex Incremental Sync

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Operations

← Back to [Documentation](../docs.md) · Related: [Card Enrichment](enrichment.md) · [TCGdex Known Issues](tcgdex_known_issues.md)

---

## Overview

The incremental sync replaces the monolithic `app:tcgdex:import` (which clones the tcgdex/cards-database git repo) with an **API-based cascade** that detects new or changed data and pulls only what is missing. It runs fully async via Symfony Messenger with dedicated Doctrine transports.

Since F6.17 the sync is **multi-locale**: TCGdex publishes a set in English first and adds French (and other) translations over the following days, so the sync fetches the locale-independent data plus every configured locale, filling translation gaps as they become available.

**Feature IDs:** F6.13 (incremental sync) · F6.17 (multi-locale gap-fill + force update) · **Parent issue:** [#411](https://github.com/jbourdin/expandedDecks/issues/411)

---

## Architecture

### Message Cascade

```
SyncTcgdexSeriesMessage (root trigger, Sync mode)
  ├─ GET /v2/en/series → detect missing/existing series (exclude tcgp)
  ├─ Create missing TcgdexSerie entities (with logoUrl); refresh existing logos
  ├─ For each serie (sorted by releaseDate DESC → newest first):
  │     dispatch SyncTcgdexSerieMessage
  │     ├─ GET /v2/en/series/{id} → list sets
  │     ├─ Create missing TcgdexSet entities (with logoUrl + symbolUrl)
  │     ├─ For each set, new or existing (sorted by releaseDate DESC):
  │     │     dispatch SyncTcgdexSetMessage
  │     │     ├─ GET /v2/en/sets/{id} → list cards
  │     │     ├─ Update TcgdexSet metadata (releaseDate, ptcgCode, cardCount)
  │     │     └─ For each card:
  │     │           • new card → dispatch SyncTcgdexCardMessage
  │     │           • existing card missing a locale → dispatch SyncTcgdexCardMessage
  │     │           • existing card with every locale → skip (refresh image URL only)
  │     │           └─ SyncTcgdexCardMessage:
  │     │                 ├─ GET /v2/en/cards/{id}  → base locale + locale-independent fields
  │     │                 └─ GET /v2/fr/cards/{id}  → merge French text (404 = not published yet, skip)
  │     └─ ...
  └─ dispatch SyncTcgdexCompleteMessage (delayed 10 min)
        ├─ Rebuild set mappings (BuildSetMappingsMessage)
        └─ Re-enrich failed deck versions (EnrichDeckVersionMessage)
```

### Sync Modes

The `SyncMode` enum propagates through the cascade. There are two modes:

| Mode | Entry point | Series & Sets | Existing cards |
|------|-------------|---------------|----------------|
| **Sync** (default) | Series (catalogue-wide) | Create missing, refresh logos/metadata | Fetch only the locales the card still lacks; skip entirely (no HTTP) when every configured locale is present |
| **ForceUpdate** | Set (single set) | — | Re-fetch every card across **every** configured locale unconditionally, plus any card the set has gained |

**Sync** is the everyday mode — used by the CLI, the webhook (serverless cron), and the admin **Sync** button. It is idempotent and self-healing: late-arriving translations are picked up on the next run because the card still reports the locale as missing. Discovery (series/serie/set lists) always uses the base English locale; only the per-card fetch fans out across locales.

**ForceUpdate** is dispatched as a `SyncTcgdexSetMessage` straight to the set handler (it never enters at the series level). It backs the admin **Force update** set-picker form and is the tool for correcting data-quality issues or repulling a set after an upstream TCGdex change.

### Locales

The configured locales live in the container parameter `app.tcgdex.locales` (`['en', 'fr']` by default). The **first entry is the base discovery locale** — it carries the locale-independent fields (HP, types, rarity, image, legality, marketplace IDs) and is fetched as a probe even when only a later locale is missing. Adding a locale (e.g. German) is a one-line config change; no code or schema change is required because the multilingual columns are locale-keyed JSON.

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

**Exception: 404 on cards is locale-aware.** A 404 on the **base locale** (`/en/cards/{id}`) means the card genuinely doesn't exist in TCGdex (e.g. unreleased promo) — logged as a warning, not retried, and the card is abandoned. A 404 on a **translation locale** (`/fr/cards/{id}`) means that translation simply isn't published yet — logged at info level and skipped, leaving the card with the locales it already has. The gap is refilled automatically on a later sync once the translation lands. A transient (non-404) error on the base locale redispatches the whole card message for retry; a transient error on a translation locale is skipped (the next sync retries it).

---

## Change Detection

| Level | Detection | Action |
|-------|-----------|--------|
| Serie | Serie ID not in `tcgdex_serie` | Create entity, dispatch `SyncTcgdexSerieMessage` |
| Serie (existing) | Always synced | Refresh logo, dispatch `SyncTcgdexSerieMessage` |
| Set | Set ID not in `tcgdex_set` | Create entity, dispatch `SyncTcgdexSetMessage` |
| Set (existing) | Always synced | Dispatch `SyncTcgdexSetMessage` (the set list carries no per-card timestamps, so the set must be walked to find new cards and locale gaps) |
| Card (new) | Card ID not in `tcgdex_card` | Dispatch `SyncTcgdexCardMessage` |
| Card (existing, Sync) | `TcgdexCard::hasAllLocales(app.tcgdex.locales)` is false | Dispatch `SyncTcgdexCardMessage` to fetch the missing locales |
| Card (existing, ForceUpdate) | Always | Dispatch `SyncTcgdexCardMessage` to re-fetch every locale |

### Locale Gap Detection

`TcgdexCard::hasAllLocales()` reports whether the card's `name` JSON map carries a non-empty value for every configured locale. The `name` field is the freshness proxy — every card has a name, so a missing locale key there means the translation hasn't been fetched. The check runs twice for efficiency: the **set handler** uses it to decide whether to dispatch a per-card message at all (a cheap DB read, no HTTP), and the **card handler** re-checks before fetching (authoritative, and skips with no HTTP when complete).

The former card-count comparison (`cardCount.total` vs local `COUNT(*)`) was removed: it could not detect locale gaps (the count is unchanged when only a translation is missing), so existing sets are now always walked.

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
symfony console app:tcgdex:sync   # Gap-fill sync (the only catalogue-wide mode)
```

Reports current queue depth and last sync timestamp. Warns if a sync is already in progress. There is no CLI force-update: ForceUpdate is set-scoped and exposed through the admin form instead.

### Admin Dashboard

The technical admin dashboard (`/admin/technical`) has a "TCGdex Database Sync" card with:
- Last sync timestamp and queue depth badges
- Cooldown status indicator
- **"Sync"** button — catalogue-wide gap-fill (`SyncMode::Sync`)
- **"Force update"** set-picker form (`TcgdexForceUpdateFormType`) — re-fetches every card of the chosen set across all locales (`SyncMode::ForceUpdate`)
- Controls disabled when a sync is in progress

### Webhook

```
POST /webhook/tcgdex-sync
```

Anonymous endpoint protected by HMAC-SHA256 signature. Designed for periodic serverless cron jobs.

- Header: `X-Sync-Signature: sha256=<hex>`
- Secret: `TCGDEX_SYNC_WEBHOOK_SECRET` env var (empty = endpoint disabled, returns 404)
- Always dispatches a gap-fill sync (`SyncMode::Sync`)
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
- **Per-locale API responses** — Each `/v2/{locale}/cards/{id}` call returns that locale's flat strings for name, effect, evolveFrom, and ability/attack text. The hydrator folds each locale into the card's locale-keyed JSON columns (`{"en": "...", "fr": "..."}`) so `getLocalizedName('fr')` and the generated `name_fr` column work identically to NDJSON-sourced cards. Locale-independent fields (HP, types, rarity, image, legality, marketplace IDs) are taken from the base-locale response.
- **Abilities/attacks merge by position** — The per-locale endpoints return the same abilities/attacks in the same order, just translated, so the hydrator matches them by list index when merging a locale. Non-text fields (type, cost, damage) are refreshed from each response (they are identical across locales).
- **Translation lag is normal** — A set arrives in English, with French following days later. A card that is English-only is simply incomplete; the next sync fetches French once `/fr/cards/{id}` stops returning 404. No manual intervention is needed.
- **`tcgdex_updated_at` is a captured baseline** — The column stores the API's per-card `updated` timestamp but is **not yet** a skip-decision input; the active freshness signal is locale completeness. It exists so set-level diffing can switch to it once TCGdex exposes a set-level timestamp (set responses currently carry none).
- **Dotted set IDs** (e.g. `sm3.5`) — The computed image URL fails for these because TCGdex CDN strips the dot. The `imageBaseUrl` from the API provides the correct URL.
- **Promo sets grow over time** — Sets like `svp` (SV promos) gain new cards throughout a generation. Walking every existing set on each Sync run detects new cards automatically.
- **Card hydration sources** — `TcgdexCardHydrator` hydrates from two sources: `hydrateFromNdjsonRecord()` for the git-based import (multilingual, no image URL) and `hydrateFromApiResponse()` + `mergeLocaleFields()` for the API sync (per-locale, with `imageBaseUrl`). Both produce identical `TcgdexCard` entities.

---

## Key Files

| File | Purpose |
|------|---------|
| `src/Enum/SyncMode.php` | `Sync` / `ForceUpdate` mode enum |
| `src/Message/SyncTcgdex*.php` | 5 message classes for the cascade |
| `src/MessageHandler/SyncTcgdex*Handler.php` | 5 handler classes |
| `src/Service/Tcgdex/TcgdexApiThrottle.php` | Rate limiting with cooldown |
| `src/Service/Tcgdex/TcgdexCardHydrator.php` | Card entity hydration (NDJSON + per-locale API merge) |
| `src/Service/Tcgdex/TcgdexSyncStatusService.php` | Queue depth + last sync tracking |
| `src/Command/SyncTcgdexCommand.php` | CLI trigger (`app:tcgdex:sync`) |
| `src/Controller/WebhookTcgdexSyncController.php` | Signed webhook endpoint |
| `src/Controller/AdminTechnicalController.php` | Admin dashboard actions (Sync + Force update) |
| `src/Form/TcgdexForceUpdateFormType.php` | Set picker for the admin Force update form |
| `config/packages/messenger.yaml` | Transport + routing config |
| `config/services.yaml` | `app.tcgdex.host` + `app.tcgdex.locales` parameters and binds |
