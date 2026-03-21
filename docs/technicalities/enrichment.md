# Card Enrichment Pipeline

> **Audience:** Developer, AI Agent · **Scope:** Technical Deep-Dive

← Back to [Documentation](../docs.md) | [Feature F6.2](../features.md)

---

## Overview

Enrichment is the process of augmenting raw deck card data (imported via PTCG text paste) with metadata from the TCGdex API. A deck list as imported only contains card names, set codes, and card numbers — and optionally card types (from section headers). Enrichment resolves each card against TCGdex to populate image URLs, trainer subtypes, card types (when unknown), legality status, rarity, pricing, and the card identity/printing model. This data powers the visual mosaic, minified export, and budget optimization features.

**Feature references:**
- `@see docs/features.md F6.2` — TCGdex card data enrichment
- `@see docs/features.md F6.8` — Minified deck list export
- `@see docs/features.md F6.9` — Improved energy card enrichment
- `@see docs/features.md F6.10` — Card identity and printing model

---

## Enrichment Pipeline

The enrichment chain is fully asynchronous, driven by Symfony Messenger on the `deck_enrichment` transport:

```
DeckVersion created (paste import or version update)
    │
    ▼
EnrichDeckVersionMessage dispatched
    │
    ▼
EnrichDeckVersionHandler
    ├── Sets DeckVersion.enrichmentStatus = 'enriching'
    ├── Iterates every DeckCard in the version
    │   ├── Basic energy? → enrichBasicEnergy() + resolve cardType to 'energy'
    │   └── Regular card? → TcgdexApiClient.findCard()
    │       ├── Found → populate tcgdexId, imageUrl, trainerSubtype, CardPrinting + resolve cardType from TCGdex category
    │       └── Not found → fallback to findFirstPrintingByName() (full TcgdexCard with CardIdentity) + resolve cardType
    ├── Sets DeckVersion.enrichmentStatus = 'done' (or 'failed' on exception)
    │
    ▼  (if status = 'done')
Three downstream messages dispatched in parallel:
    ├── GenerateDeckMosaicMessage     → renders the card mosaic image (see mosaic.md)
    ├── GenerateMinifiedListMessage   → generates the budget PTCGL text export
    └── GenerateMinifiedMosaicMessage → renders the minified (budget) mosaic image
```

### Enrichment Status

The `DeckVersion.enrichmentStatus` field tracks progress:

| Status       | Meaning                                     |
|--------------|---------------------------------------------|
| `pending`    | Awaiting enrichment (initial state)         |
| `enriching`  | Enrichment in progress                      |
| `done`       | Successfully enriched                       |
| `failed`     | Exception occurred during enrichment        |

### Enrichment Report

`CardEnricher.enrichVersion()` returns a `CardEnrichmentReport` containing:
- `enrichedCount` — number of cards successfully enriched
- `notFoundCount` — number of cards not found in TCGdex
- `notFoundCards` — list of card descriptors (`"Name (SET NUM)"`) for logging
- `legalityWarnings` — list of warnings (not expanded-legal, name-only match)

**Key files:**
- `src/MessageHandler/EnrichDeckVersionHandler.php`
- `src/Service/Tcgdex/CardEnricher.php`

### Card Type Resolution

Section headers (`Pokémon:`, `Trainer:`, `Energy:`) in the imported deck list are optional. When headers are absent, the parser assigns `unknown` as the card type. During enrichment, the `CardEnricher` resolves unknown card types from the TCGdex `category` field (`Pokemon` → `pokemon`, `Trainer` → `trainer`, `Energy` → `energy`). Cards that already have a known type (from section headers) are not overwritten. Cards that cannot be found in TCGdex retain the `unknown` type.

---

## TCGdex API Client

`TcgdexApiClient` wraps the TCGdex REST API v2 (`https://api.tcgdex.net/v2/en`).

### Set Mapping

PTCG deck lists use set codes (e.g. `ASR`, `SVI`) that differ from TCGdex's internal set IDs. The client builds a bidirectional mapping:

1. **Forward mapping** (`getSetMapping()`): PTCG code → TCGdex set ID
   - Fetches all sets from `/sets`, then each set's detail concurrently to read `tcgOnline` and `abbreviation.official`
   - `tcgOnline` takes precedence over `abbreviation.official` when both exist
   - Cached for 24 hours

2. **Reverse mapping** (`getReverseSetMapping()`): TCGdex set ID → PTCG code
   - Built separately (not `array_flip`) because multiple PTCG codes can map to the same TCGdex set ID (e.g. both `NXD` and `NEX` → `bw4`)
   - **Prefers `tcgOnline` codes** (PTCGL-compatible, e.g. `NXD`) over `abbreviation.official` (e.g. `NEX`): official is set first as fallback, then `tcgOnline` overwrites
   - This ensures the minified export outputs set codes accepted by PTCGL and Limitless
   - Cached for 24 hours (cache key `tcgdex.reverse_set_mapping.v2`)

### Static Overrides

Some PTCG codes have no match in TCGdex metadata and are hardcoded:

| PTCG Code | TCGdex ID | Reason                                      |
|-----------|-----------|---------------------------------------------|
| `PR-SV`   | `svp`     | Promo sets use `PR-XX` pattern in PTCG      |
| `PR-SW`   | `swshp`   | Same                                        |
| `PR-SM`   | `smp`     | Same                                        |
| `PR-XY`   | `xyp`     | Same                                        |
| `PR-BW`   | `bwp`     | Same                                        |
| `SVI`     | `sv01`    | PTCG Live uses `SVI`, TCGdex uses `sv01`    |

### Promo Card Number Prefixes

TCGdex prefixes card numbers in promo sets with an era tag. PTCG lists `Karen XYP 177`, but TCGdex stores it as `xyp-XY177`. SV promos use plain numbers (`svp-001`) and need no prefix.

| TCGdex Set ID | Number Prefix |
|---------------|---------------|
| `swshp`       | `SWSH`        |
| `smp`         | `SM`          |
| `xyp`         | `XY`          |
| `bwp`         | `BW`          |

### Card Lookup: `findCard()`

Resolves a card by PTCG set code and card number:

1. **Normalize set code** — uppercase
2. **Trainer Gallery** — if set code ends with `-TG` (e.g. `ASR-TG`), strip suffix and prefix the card number with `TG` (e.g. `30` → `TG30`)
3. **Letter suffixes** — strip trailing letters from card numbers (e.g. `113a` → `113`) via regex `[a-z]+$`
4. **Resolve TCGdex set ID** — via set mapping; return `null` if unmapped (Japanese set codes like `S6K`, `SM8` typically fail here)
5. **Apply promo prefix** — prepend era tag for promo sets
6. **Fetch card** — `GET /cards/{setId}-{cardNumber}`
7. **Zero-padding fallback** — if not found and the card number is less than 3 digits, retry with zero-padded number (e.g. `1` → `001`)

### Name Fallback: `findFirstPrintingByName()`

When `findCard()` returns `null` (e.g. Japanese set codes like `S6K`, `SM8`), the enricher calls `findFirstPrintingByName()` which uses `findAllPrintingsByName()` to fetch all printings of the card name. It returns the first exact-name match with an image, as a full `TcgdexCard` DTO — not just an image URL. This allows the enricher to:

1. Set `tcgdexId` and `imageUrl` on the `DeckCard`
2. Create a proper `CardIdentity` + `CardPrinting` via `CardIdentityResolver`
3. Link the `CardPrinting` to the `DeckCard`

This means name-fallback cards participate fully in the minified export pipeline — the minified list/mosaic can resolve an international printing (e.g. `S6K 36` → `CRE 74`) instead of returning the original invalid set code.

A legacy `findImageByName()` method also exists for simpler fallback cases (returns only an image URL string). TCGdex name search is a contains-match, so both methods filter results to exact name equality.

### Card Lookup: `findAllPrintingsByName()`

Used by the minified export pipeline to discover all printings of a card. Searches by name, then fetches full card details for each result via `fetchCardById()`. Returns a `list<TcgdexCard>`.

### Set Release Date

If the card detail response does not include a set release date, the client fetches it separately from `GET /sets/{setId}`. Release dates are cached.

### Pricing

Extracted from the card detail's `pricing` field:
1. **Cardmarket** (EUR) — `pricing.cardmarket.avg`, converted to euro cents
2. **TCGPlayer** (USD, approximate fallback) — `pricing.tcgplayer.normal.midPrice`, converted to cents

Returns `null` if no pricing data is available.

**Key file:** `src/Service/Tcgdex/TcgdexApiClient.php`

**DTO:** `src/Service/Tcgdex/TcgdexCard.php` — readonly value object carrying all parsed card data (id, name, category, trainerType, imageUrl, isExpandedLegal, hp, attacks, rarity, setReleaseDate, setCode, cardNumber, priceInCents, cardmarketProductId, tcgplayerProductId).

---

## Card Identity Model

The enrichment pipeline builds a normalized card model that separates the concept of a "functional card" from its individual set printings.

### CardIdentity

Represents a unique functional card across all sets. Two cards are the "same" if they have the same:
- **Name** — exact string match
- **Category** — `pokemon`, `trainer`, or `energy`
- **HP** — for Pokemon only (0 for Trainer/Energy as a sentinel)
- **Attack signature** — sorted comma-joined attack names, e.g. `"Bite,Flare Blitz"` (empty string for Trainer/Energy)

**Unique constraint:** `(name, category, hp, attack_signature)`

This means two Pokemon with the same name but different HP or attacks (e.g. different evolutions or card versions) are separate identities. All printings of "Professor's Research" (a Trainer) share one identity regardless of set.

### CardPrinting

Represents a specific physical printing of a card in a particular set:

| Field              | Description                                             |
|--------------------|---------------------------------------------------------|
| `tcgdexId`         | TCGdex unique ID (e.g. `sv01-001`), unique constraint   |
| `setCode`          | PTCG set code (e.g. `SVI`)                              |
| `cardNumber`       | Card number within the set                              |
| `rarity`           | TCGdex rarity string (e.g. `"Rare Holo"`)               |
| `rarityTier`       | Integer 1–7 (see Rarity Tier Mapping)                   |
| `imageUrl`         | High-res card image URL                                 |
| `setReleaseDate`   | Set release date (for era filtering and sorting)        |
| `priceInCents`     | Average price in euro cents from Cardmarket              |
| `isExpandedLegal`  | Whether TCGdex marks this printing as Expanded-legal    |
| `cardmarketProductId` | Cardmarket product ID for direct linking (nullable)  |
| `tcgplayerProductId`  | TCGPlayer product ID for direct linking (nullable)   |

### Identity Resolution

`CardIdentityResolver.resolveFromTcgdexCard()`:
1. Check if a `CardPrinting` already exists for this `tcgdexId` — return it if so
2. Find or create a `CardIdentity` by signature lookup
3. Create and persist a new `CardPrinting` linked to the identity

### Lazy Printing Expansion

`CardIdentityResolver.expandPrintings()` is called lazily by the minified export when an identity has only one printing. It fetches all printings from TCGdex by name, filters to exact name matches, and for Pokemon cards additionally verifies that HP and attack signature match before creating new `CardPrinting` records. This populates the full printing catalog so the minified export can pick the cheapest.

**Key files:**
- `src/Entity/CardIdentity.php`
- `src/Entity/CardPrinting.php`
- `src/Service/CardIdentity/CardIdentityResolver.php`

---

## Rarity Tier Mapping

`RarityTierMapper` converts TCGdex rarity strings into a 7-tier integer scale for cost-based sorting. Lower tier = cheaper/more common.

### Tier Definitions

| Tier | Rarity Strings                                                                                       |
|------|------------------------------------------------------------------------------------------------------|
| 1    | Common, None                                                                                         |
| 2    | Uncommon, One Diamond                                                                                |
| 3    | Rare, Rare Holo, Holo Rare, Two Diamond, Black White Rare                                            |
| 4    | Holo Rare V/VMAX/VSTAR, Three Diamond, Four Diamond, ACE SPEC Rare, Double rare                      |
| 5    | Ultra Rare, Full Art Trainer, Rare Holo LV.X, Rare PRIME, Amazing Rare, Radiant Rare, One Shiny, One Star |
| 6    | Secret Rare, Hyper rare, Special/Illustration/Shiny rare variants, Crown, Classic Collection, LEGEND, Mega Hyper Rare, Two Shiny, Two Star, Three Star |
| 7    | Unknown / unmapped rarity (default)                                                                  |

### Unknown Rarity Default

Unknown or unmapped rarities default to tier 7 (higher than any mapped tier). This is intentional: treating unknowns as the rarest tier prevents them from being falsely selected as "budget" picks in the minified export.

### Unreliable Rarity Set Blacklist

Certain sets have rarity data in TCGdex that does not reflect actual card value. Cards from these sets always return tier 7 regardless of their stated rarity:

- **Shiny Vault / Yellow A** (`sma`, `xya`) — every card is marked "Common" despite containing full-art and shiny rares
- **Promo sets** (`svp`, `swshp`, `smp`, `xyp`, `bwp`, `dpp`, `hgssp`, `np`, `basep`, `wp`, `mep`, `P-A`) — rarity is inconsistent or meaningless
- **Trainer kits** (`tk-*`) — not standard booster product
- **McDonald's promos** (`2011bw`, `2012bw`, etc.) — special promotional sets
- **Other special sets** (`cel25`, `det1`, `fut2020`, `rc`, `exu`, `lc`) — non-standard product
- **POP series** (`pop1`–`pop9`) — promotional league sets

**Key file:** `src/Service/CardIdentity/RarityTierMapper.php`

---

## Minified Export

The minified export feature generates a budget-optimized deck list by replacing each card with its cheapest available printing. This produces three outputs: PTCGL text, a table view, and a budget mosaic image.

### Lowest-Rarity Printing Selection

For each non-energy card, `CardPrintingRepository.findLowestRarityForIdentity()` selects the best printing using this sort order:

1. **Rarity tier** ascending (lowest rarity first — Common before Rare)
2. **Set release date** descending (most recent reprint within the same rarity)
3. **Price in cents** ascending (cheapest as final tiebreaker)

Additional filters applied:
- Must be marked `isExpandedLegal = true`
- Must have a non-null `imageUrl`
- Must be from the **Expanded era** (set release date >= 2011-04-25, or null release date allowed)

### Basic Energy: Static Default Printings

Basic energy cards use a different strategy: instead of querying the database for the best printing, they use a **static default** from `DeckListParser::DEFAULT_BASIC_ENERGY_PRINTINGS`. This maps each energy type directly to MEE (Mega Evolution Energy) for the 8 standard types, or SUM (Sun & Moon) for Fairy Energy. This ensures the minified export always uses the cleanest, most modern plain basic energy image — regardless of what TCGdex has indexed.

See [`data/basic_energies.json`](/data/basic_energies.json) for the full catalogue and [`basic_energy_images.md`](basic_energy_images.md) for CDN source details.

### Card Merging

When multiple deck entries resolve to the same minified printing (same name + set code + card number), their quantities are summed into a single line. This happens when a deck contains multiple copies of a card from different sets that all resolve to the same cheapest printing.

### MinifiedListGenerator

Produces PTCGL-format text output. Cards are sorted by type (Pokemon, then Trainer subdivided by supporter/item/tool/stadium, then Energy), then by quantity descending, then by name ascending. Each line follows the format: `{quantity} {name} {setCode} {cardNumber}`.

### MinifiedCardViewBuilder

Produces structured `MinifiedCardView` objects grouped by card type (`pokemon`, `trainer`, `energy`) for the deck detail table view. Uses the same merging and sorting logic as the list generator but includes image URLs for display.

### Lazy Expansion Trigger

Both the list generator and view builder trigger `expandPrintings()` lazily: if a card identity has only one known printing (the one from the original deck), all other printings are fetched from TCGdex before selecting the cheapest. This avoids fetching printings for cards that are never minified.

**Key files:**
- `src/Service/DeckList/MinifiedListGenerator.php`
- `src/Service/DeckList/MinifiedCardViewBuilder.php`
- `src/Repository/CardPrintingRepository.php`

---

## Basic Energy Handling

Basic energy cards receive special treatment throughout the enrichment pipeline because energy set codes (SVE, SME, XYE, BWE, MEE) do not exist in TCGdex, and energy cards are functionally interchangeable across all sets.

A comprehensive reference of all known basic energy printings, image sources, and minified defaults is maintained in [`data/basic_energies.json`](/data/basic_energies.json) — see [basic_energy_images.md](basic_energy_images.md) for details.

### Detection

A card is classified as basic energy if:
1. Its name matches one of the 9 basic energy names (Grass, Fire, Water, Lightning, Psychic, Fighting, Darkness, Metal, Fairy), **or**
2. Its set code is one of the energy-specific codes: `SVE`, `SME`, `XYE`, `BWE`

Name-based detection is primary, covering energy cards that appear in non-energy sets (e.g. `SVI`). The parser also detects basic energies at parse time and assigns `energy` card type even without section headers.

### Enrichment Lookup Chain

For energy cards from non-energy sets (e.g. `SVI 257`):
1. **Set + number lookup** — `findCard()` with the original set code and number
2. If found, enrich normally (tcgdexId, imageUrl, CardPrinting)

For energy cards from energy sets (`SVE`, `SME`, etc.) or when set+number fails:
1. **Simplest printing by name** — `findSimplestBasicEnergyByName()` searches all TCGdex printings and selects the Common-rarity one with the most recent release date, creating a proper `CardIdentity`/`CardPrinting` link
2. **Static fallback** — hardcoded image URLs from `BASIC_ENERGY_IMAGES`, used only when TCGdex returns no results

Energy cards matched by name only do not generate "not found" warnings, unlike regular cards.

---

## Admin Tools

The technical admin dashboard (`/admin/technical`) exposes three enrichment-related actions:

### Enrich Retry

Finds all `DeckVersion` records with `enrichmentStatus` of `pending` or `failed`, and dispatches a new `EnrichDeckVersionMessage` for each. Used to recover from transient TCGdex API failures or to process versions that were created while the worker was down.

**Route:** `POST /admin/technical/enrich-retry`

### Mosaic Generate

Redispatches `GenerateDeckMosaicMessage` for fully enriched versions that are missing a mosaic image. See [mosaic.md](mosaic.md) for details on the `MosaicRedispatchService`.

### Flush Enrichment

Nuclear reset that clears all enrichment-derived data:
1. Nullifies `DeckCard` fields: `tcgdexId`, `imageUrl`, `trainerSubtype`, `cardPrinting` FK
2. Resets all `DeckVersion` records: `enrichmentStatus` → `'pending'`, nullifies `mosaicImageUrl`, `minifiedList`, `minifiedMosaicImageUrl`
3. Deletes all `CardPrinting` records
4. Deletes all `CardIdentity` records
5. Clears all mosaic image files from Flysystem storage

After flushing, an enrich retry re-populates everything from scratch.

**Route:** `POST /admin/technical/flush-enrichment`

**Key files:**
- `src/Controller/AdminTechnicalController.php`
- `src/Service/EnrichmentFlushService.php`

---

## Known Limitations

- **TCGdex name search is contains-match** — searching for "Pikachu" also returns "Pikachu V", "Flying Pikachu", etc. The code filters results to exact name equality, but this means every result in the response is fetched and compared.

- **Japanese set codes** — sets like `S6K` and `SM8` have no card data in TCGdex (which covers English-language sets). Cards from these sets fall back to name-based matching via `findFirstPrintingByName()`, which creates a proper `CardIdentity`/`CardPrinting` link. The minified export can then resolve an international printing, but the original deck view still shows the Japanese set code. A warning banner is displayed asking the user to re-import with international set codes.

- **Unreliable rarity in some sets** — Hidden Fates Shiny Vault (`sma`), Yellow A Alternate (`xya`), and other sets have all cards marked as "Common" in TCGdex. These sets are blacklisted and their cards default to tier 7, which means they are never selected as budget picks even if they would be cheaper.

- **Pricing data gaps** — not all cards in TCGdex have Cardmarket or TCGPlayer pricing. Cards without pricing sort after priced cards at the same rarity tier (nulls sort after non-nulls in ascending order).

- **Same-name different-artwork at same rarity** — when multiple printings of a card exist at the same rarity tier and price (or with no price data), the selection falls back to most recent release date. There is no artwork distinction in TCGdex, so the system cannot prefer a specific artwork variant.

- **Letter suffix stripping** — card numbers like `113a` are stripped to `113` for lookup. If TCGdex stores the card under the suffixed number, the lookup fails and falls back to name search.

- **Concurrent set detail fetching** — building the set mapping requires one HTTP request per set (200+ requests). These are fired concurrently via Symfony HttpClient, but the initial cold-cache build can take several seconds. The result is cached for 24 hours.
