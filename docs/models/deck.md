# Deck Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\Deck`

Represents a **physical** Pokemon TCG deck — the deck box with a label. A deck evolves over time: the owner may swap cards, shift archetypes, or change value. The card-level data lives on `DeckVersion`; `Deck` only tracks identity, ownership, and availability.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `name`             | `string(100)`      | No       | Owner-given name for this deck (e.g. "My Lugia VSTAR"). |
| `owner`            | `User`             | No       | The user who owns this physical deck. |
| `format`           | `string(30)`       | No       | Play format. Default: `"Expanded"`. |
| `status`           | `string(20)`       | No       | Current availability status. See Status enum below. Default: `"available"`. |
| `notes`            | `text`             | Yes      | Owner's private notes about the deck (e.g. sleeve color, missing cards, condition). |
| `currentVersion`   | `DeckVersion`      | Yes      | The latest/active version of this deck. Null only before the first list import. |
| `createdAt`        | `DateTimeImmutable` | No      | Deck registration timestamp. |
| `updatedAt`        | `DateTimeImmutable` | Yes     | Last modification timestamp. |

### Status Enum: `App\Enum\DeckStatus`

| Value       | Description |
|-------------|-------------|
| `available` | Deck is ready to be borrowed. Default state. |
| `reserved`  | A borrow request has been approved but the deck hasn't been handed off yet. |
| `lent`      | Deck is currently lent to a borrower (or held by event staff). |
| `retired`   | Owner has retired this deck. Not available for borrowing. |

### Constraints

- `name`: required, 2–100 characters
- `owner`: required, must be a verified user
- `status`: required, must be a valid `DeckStatus` value

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `owner`            | ManyToOne    | `User`         | User who owns this deck |
| `versions`         | OneToMany    | `DeckVersion`  | All versions of this deck (card list snapshots) |
| `currentVersion`   | ManyToOne    | `DeckVersion`  | Pointer to the active version |
| `borrows`          | OneToMany    | `Borrow`       | Borrow history for this deck |

---

## Entity: `App\Entity\DeckVersion`

A **card list snapshot** — one point-in-time version of a deck. Created when the owner first imports a deck list, and again each time they paste an updated list (F2.8). Previous versions are preserved for history and traceability.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `deck`             | `Deck`             | No       | The deck this version belongs to. |
| `versionNumber`    | `int`              | No       | Sequential version number (1, 2, 3…). Auto-incremented per deck. |
| `archetype`        | `Archetype`        | Yes      | Reference to the managed `Archetype` entity (F2.6). Selected by the owner from the archetype catalogue (with autocomplete). Null if no archetype assigned yet. |
| `languages`        | `json`             | No       | Array of ISO 639-1 language codes present in this version (e.g. `["en", "ja"]`). |
| `estimatedValueAmount`   | `int`        | Yes      | Owner-provided estimated monetary value in cents of the currency. Visible to the owner, organizers, and event staff. |
| `estimatedValueCurrency` | `string(3)`  | Yes      | ISO 4217 currency code (e.g. `"EUR"`, `"USD"`). Required when `estimatedValueAmount` is set. |
| `rawList`          | `text`             | Yes      | The original PTCG text format pasted by the owner. Preserved for reference and re-import. |
| `createdAt`        | `DateTimeImmutable` | No      | When this version was created. |

### Languages

The `languages` field is a JSON array of ISO 639-1 codes. Common values:

| Code | Language |
|------|----------|
| `en` | English |
| `ja` | Japanese |
| `fr` | French |
| `de` | German |
| `es` | Spanish |
| `it` | Italian |
| `pt` | Portuguese |
| `ko` | Korean |
| `zh` | Chinese |

A deck version can contain cards in multiple languages (e.g. `["en", "ja"]` for a mixed English/Japanese deck). This helps borrowers know if they'll be able to read the cards.

### Constraints

- Unique constraint on (`deck`, `versionNumber`) — no duplicate version numbers per deck
- `languages`: required, at least one language code
- `estimatedValueAmount` and `estimatedValueCurrency`: both null or both set. `estimatedValueAmount` >= 0 when provided.

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `deck`             | ManyToOne    | `Deck`         | Parent deck |
| `archetype`        | ManyToOne    | `Archetype`    | Archetype for this version (F2.6) |
| `cards`            | OneToMany    | `DeckCard`     | Cards in this version (the list) |

---

## Entity: `App\Entity\DeckCard`

A single card entry in a deck version's card list. Parsed from PTCG text format via `ptcgo-parser`, validated against TCGdex.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `deckVersion`      | `DeckVersion`      | No       | The deck version this card belongs to. |
| `cardName`         | `string(100)`      | No       | Card name (e.g. `"Lugia VSTAR"`). |
| `setCode`          | `string(20)`       | No       | Set abbreviation (e.g. `"SIT"`, `"BRS"`). |
| `cardNumber`       | `string(20)`       | No       | Card number within the set (e.g. `"186"`, `"TG1"`). |
| `quantity`         | `int`              | No       | Number of copies in the deck (1–4 for most cards, unlimited for basic energy). |
| `cardType`         | `string(20)`       | No       | Card category: `"pokemon"`, `"trainer"`, or `"energy"`. |
| `trainerSubtype`   | `string(20)`       | Yes      | Trainer subcategory: `"supporter"`, `"item"`, `"tool"`, or `"stadium"`. Null for non-trainer cards. |
| `tcgdexId`         | `string(30)`       | Yes      | TCGdex card identifier. Used for image retrieval and validation. |

### Constraints

- Unique constraint on (`deckVersion`, `setCode`, `cardNumber`) — no duplicate card entries per version
- `quantity`: required, >= 1
- `cardType`: required, one of `"pokemon"`, `"trainer"`, `"energy"`
- `trainerSubtype`: required when `cardType` is `"trainer"`, null otherwise

---

## Entity: `App\Entity\Archetype`

> **@see** docs/features.md F2.6 — Deck archetype management
> **@see** docs/features.md F2.10 — Archetype detail page

A managed archetype entry representing a deck strategy (e.g. "Lugia VSTAR", "Mew VMAX"). Translatable fields follow the same Knp Translatable pattern as CMS pages. Managed by users with `ROLE_ARCHETYPE_EDITOR` or `ROLE_ADMIN`.

### Fields

| Field          | Type               | Nullable | Description |
|----------------|--------------------|----------|-------------|
| `id`           | `int` (auto)       | No       | Primary key |
| `slug`         | `string(100)`      | No       | URL-friendly identifier (e.g. `"lugia-vstar"`). Used in the archetype page route. |
| `pokemonSlugs` | `json`            | No       | Array of Pokemon slug identifiers used to render sprite pictograms via a PokéSprite fork (F2.12). Each slug maps to a box sprite file (e.g. `["lugia"]` → `pokemon-gen8/regular/lugia.png`). Covers Gen 1–9 (including Scarlet/Violet and DLC Pokemon like Raging Bolt, Walking Wake). Supports multiple Pokemon for multi-Pokemon archetypes (e.g. `["mew", "genesect"]`). Order determines display order. |
| `isPublished`  | `bool`             | No       | Whether the archetype page is publicly visible. Default: `false`. Unpublished archetypes can still be selected for decks, but have no public page. |
| `createdAt`    | `DateTimeImmutable` | No      | Creation timestamp. |
| `updatedAt`    | `DateTimeImmutable` | No      | Last modification timestamp. |

### Constraints

- `slug`: required, unique, 1–100 characters, URL-friendly (`[a-z0-9-]+`)
- `pokemonSlugs`: required, non-empty JSON array. Each entry must be a valid PokéSprite slug (lowercase, matching a sprite file in the PokéSprite fork's `pokemon-gen8/regular/` directory). Covers Gen 1–9. Max 4 entries.

### Relations

| Relation       | Type      | Target                  | Description |
|----------------|-----------|-------------------------|-------------|
| `translations` | OneToMany | `ArchetypeTranslation`  | Localized name, description, and SEO |
| `deckVersions` | OneToMany | `DeckVersion`           | Deck versions using this archetype |

---

## Entity: `App\Entity\ArchetypeTranslation`

Localized fields for `Archetype`. One row per locale per archetype.

### Fields

| Field             | Type          | Nullable | Description |
|-------------------|---------------|----------|-------------|
| `id`              | `int` (auto)  | No       | Primary key |
| `locale`          | `string(5)`   | No       | ISO 639-1 locale code (e.g. `"en"`, `"fr"`). |
| `name`            | `string(100)` | No       | Translated archetype name (e.g. `"Lugia VSTAR"`, `"Lugia VSTAR"`). |
| `description`     | `text`        | Yes      | Archetype presentation in **Markdown** format. Rendered to HTML on the archetype detail page (F2.10). Null = no description available for this locale. |
| `metaDescription` | `string(160)` | Yes      | SEO `<meta name="description">` for the archetype page. Max 160 chars. |

### Constraints

- Unique constraint on (`translatable_id`, `locale`) — one translation per locale per archetype
- `name`: required, 1–100 characters
- `metaDescription`: max 160 characters when provided

### Locale Fallback

Same chain as CMS pages (F11.3): user locale → `en` → 404.

### URL Routing

Archetype pages are served at:

```
/{locale}/archetypes/{slug}
```

Example:
- `/en/archetypes/lugia-vstar`
- `/fr/archetypes/lugia-vstar` (slug is not localized — archetype names are typically the same across languages)

---

## Entity: `App\Entity\EventDeckEntry`

Records which deck version a player played at a specific event. Separate from borrowing — this tracks tournament deck registration regardless of whether the deck was borrowed or owner-played.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `event`            | `Event`            | No       | The event/tournament. |
| `player`           | `User`             | No       | The player who played this deck. |
| `deckVersion`      | `DeckVersion`      | No       | The specific version that was played. |
| `placement`        | `int`              | Yes      | Final standing / ranking (1 = winner, 2 = runner-up, etc.). Set by organizer or staff after the event. Null until results are entered. |
| `wins`             | `int`              | Yes      | Number of match wins in the tournament. |
| `losses`           | `int`              | Yes      | Number of match losses in the tournament. |
| `ties`             | `int`              | Yes      | Number of match ties in the tournament. |
| `createdAt`        | `DateTimeImmutable` | No      | When this entry was recorded. |

### Constraints

- Unique constraint on (`event`, `player`, `deckVersion`) — a player can't register the same version twice for the same event
- `player` must be a participant of the event (F3.4)
- `placement`: optional, >= 1 when provided
- `wins`, `losses`, `ties`: all null or all set. Each >= 0 when provided.

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `event`            | ManyToOne    | `Event`        | The event this entry is for |
| `player`           | ManyToOne    | `User`         | The player who played the deck |
| `deckVersion`      | ManyToOne    | `DeckVersion`  | The version that was played |

---

## Deck List Import & Display

### Input: Copy-Paste Only

There is **no deck editor** in the application. Users paste a deck list in standard PTCG text format:

```
* 2 Lugia VSTAR SIT 186
* 4 Lumineon V BRS 40
* 4 Professor's Research BRS 147
* 4 Capture Energy DAA 201
```

The system:
1. **Parses** the text using `ptcgo-parser` (npm) → structured card objects
2. **Validates** each card against TCGdex → confirms card exists, resolves card type and trainer subtype
3. **Validates Expanded legality** → checks that all cards are from Black & White series onward and not on the banned list
4. **Creates a new `DeckVersion`** with the parsed cards as `DeckCard` entities + preserves the raw text in `DeckVersion.rawList`
5. **Updates `Deck.currentVersion`** to point to the newly created version

On first import, this creates version 1. On subsequent imports (F2.8), a new version is created and `currentVersion` is updated — previous versions are preserved.

### Display: Categorized List

Deck lists are displayed grouped and sorted as follows:

```
Pokemon (12)
  2 Lugia VSTAR          SIT 186
  4 Lumineon V            BRS 40
  ...

Trainer (36)
  Supporter (10)
    4 Professor's Research BRS 147
    2 Boss's Orders        BRS 132
    ...
  Item (18)
    4 Ultra Ball           SUM 135
    4 Nest Ball            SUM 123
    ...
  Tool (4)
    2 Choice Belt          BRS 135
    ...
  Stadium (4)
    2 Path to the Peak     CRE 148
    ...

Energy (12)
  4 Capture Energy         DAA 201
  4 Double Turbo Energy    BRS 151
  ...
```

Within each subcategory, cards are sorted by quantity (descending), then alphabetically by name.

**Mouse over** a card name → displays the card image (fetched from TCGdex, cached client-side).

### Expanded Format Validation

The custom validator checks:
- **Set legality:** all cards must be from Black & White (BLW) series onward
- **Banned cards:** maintained as a configurable list (updated when Pokemon announces bans)
- **Card count rules:** 60 cards total, max 4 copies of any card (except basic energy)
- **Card existence:** every card must be found in TCGdex

Validation errors are shown inline next to the offending card after paste.

### Technology Stack

| Layer | Tool | Role |
|-------|------|------|
| Parse | `ptcgo-parser` (npm) | Converts PTCG text → structured JS objects |
| Card data | TCGdex (`@tcgdex/sdk`) | Card metadata, types, subtypes, images (multilingual) |
| Validation | Custom service | Expanded legality: set range, banned list, card counts |
| Display | Custom React component | Categorized list with image hover |
