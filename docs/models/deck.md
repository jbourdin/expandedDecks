# Deck Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\Deck`

Represents a **physical** Pokemon TCG deck — the deck box with a label. A deck evolves over time: the owner may swap cards, shift archetypes, or change value. The card-level data lives on `DeckVersion`; `Deck` only tracks identity, ownership, and availability.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `shortTag`         | `string(6)`        | No       | Auto-generated 6-character unique code for physical identification. Charset: `A-H, J-N, P-Z, 0-9` (34 characters — excludes `I` and `O` to prevent confusion with `1` and `0`). Generated on deck creation, immutable thereafter. Used as a verbal identifier at events, a searchable field in the app, and printed on the deck box label (F5.x). Unique DB index. See F2.1. |
| `name`             | `string(100)`      | No       | Owner-given name for this deck (e.g. "My Lugia VSTAR"). |
| `owner`            | `User`             | No       | The user who owns this physical deck. |
| `format`           | `string(30)`       | No       | Play format. Default: `"Expanded"`. |
| `archetype`        | `Archetype`        | Yes      | Reference to the managed `Archetype` entity (F2.6). Selected by the owner from the archetype catalogue (with autocomplete). Null if no archetype assigned yet. |
| `languages`        | `json`             | No       | Array of ISO 639-1 language codes present in this deck (e.g. `["en", "ja"]`). Default: `[]`. |
| `status`           | `string(20)`       | No       | Current availability status. See Status enum below. Default: `"available"`. |
| `notes`            | `text`             | Yes      | Owner's private notes about the deck (e.g. sleeve color, missing cards, condition). |
| `public`           | `bool`             | No       | Whether the deck is visible in the public catalog and accessible via its shortTag URL to anonymous users. Default: `false`. Cannot be unpublished while the deck has active event registrations. |
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

- `shortTag`: required, exactly 6 characters, unique, immutable after creation. Characters drawn from `ABCDEFGHJKLMNPQRSTUVWXYZ0123456789` (34-char alphabet). Auto-generated on deck creation using a cryptographically random selection with uniqueness retry. 34^6 ≈ 1.54 billion possible codes — collision probability negligible at any realistic deck count
- `name`: required, 2–100 characters
- `owner`: required, must be a verified user
- `status`: required, must be a valid `DeckStatus` value

### Public Access Control

When `public` is `true`, the deck is listed in the public catalog (F2.4) and its detail page (F2.3) is accessible to anonymous visitors via the shortTag URL (`/deck/{shortTag}`). When `public` is `false`, only the deck owner, admins, and organizers/staff of events where the deck is registered can view the detail page — anonymous visitors receive a 403.

A public deck cannot be unpublished while it has active `EventDeckRegistration` entries to prevent breaking event workflows.

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `owner`            | ManyToOne    | `User`         | User who owns this deck |
| `archetype`        | ManyToOne    | `Archetype`    | Archetype for this deck (F2.6) |
| `versions`         | OneToMany    | `DeckVersion`  | All versions of this deck (card list snapshots) |
| `currentVersion`   | ManyToOne    | `DeckVersion`  | Pointer to the active version |
| `borrows`          | OneToMany    | `Borrow`       | Borrow history for this deck |
| `eventRegistrations` | OneToMany  | `EventDeckRegistration` | Per-event deck availability/delegation entries |

---

## Entity: `App\Entity\DeckVersion`

A **card list snapshot** — one point-in-time version of a deck. Created when the owner first imports a deck list, and again each time they paste an updated list (F2.8). Previous versions are preserved for history and traceability.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `deck`             | `Deck`             | No       | The deck this version belongs to. |
| `versionNumber`    | `int`              | No       | Sequential version number (1, 2, 3…). Auto-incremented per deck. |
| `estimatedValueAmount`   | `int`        | Yes      | Owner-provided estimated monetary value in cents of the currency. Visible to the owner, organizers, and event staff. |
| `estimatedValueCurrency` | `string(3)`  | Yes      | ISO 4217 currency code (e.g. `"EUR"`, `"USD"`). Required when `estimatedValueAmount` is set. |
| `rawList`          | `text`             | Yes      | The original PTCG text format pasted by the owner. Preserved for reference and re-import. |
| `createdAt`        | `DateTimeImmutable` | No      | When this version was created. |

### Constraints

- Unique constraint on (`deck`, `versionNumber`) — no duplicate version numbers per deck
- `estimatedValueAmount` and `estimatedValueCurrency`: both null or both set. `estimatedValueAmount` >= 0 when provided.

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `deck`             | ManyToOne    | `Deck`         | Parent deck |
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

A managed archetype entry representing a deck strategy (e.g. "Lugia VSTAR", "Iron Thorns ex"). Archetypes are created on-the-fly when owners assign them to decks, then enriched by admins with descriptions, Pokémon sprite slugs, and publication status.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `name`             | `string(100)`      | No       | Display name (e.g. `"Iron Thorns ex"`). Required, unique. |
| `slug`             | `string(100)`      | No       | URL-friendly identifier, auto-generated from name via `AsciiSlugger` (e.g. `"iron-thorns-ex"`). Unique. |
| `pokemonSlugs`     | `json`             | No       | Array of Pokémon slug strings for sprite display (e.g. `["roaring-moon", "flutter-mane"]`). Default: `[]`. Slugs must match filenames in the PokéSprite asset set. See F2.12. |
| `playstyleTags`    | `json`             | No       | Array of `PlaystyleTag` enum values (e.g. `["aggressive", "combo"]`). Default: `[]`. Predefined tags: `aggressive`, `control`, `combo`, `lock`, `spread`, `toolbox`. Each tag has a badge color and translation key. See F2.15. |
| `description`      | `text`             | Yes      | Markdown content for the archetype detail page (F2.10). Rendered via `ArchetypeDescriptionRenderer` which processes Markdown and expands custom tags: `[[archetype:slug]]` (archetype link with sprites), `[[deck:SHORTTAG]]` (deck badge link), `[[card:SET-NUMBER]]` (card name with hover image). |
| `metaDescription`  | `string(255)`      | Yes      | SEO meta description for the archetype detail page. Max 255 characters. |
| `isPublished`      | `bool`             | No       | Controls visibility of the archetype detail page (F2.10). Default: `false`. |
| `createdAt`        | `DateTimeImmutable` | No      | Creation timestamp. |
| `updatedAt`        | `DateTimeImmutable` | Yes     | Last modification timestamp. |

### Constraints

- `name`: required, unique, 2–100 characters
- `slug`: required, unique, auto-generated from name via `AsciiSlugger`
- `metaDescription`: max 255 characters

### Relations

| Relation       | Type      | Target    | Description |
|----------------|-----------|-----------|-------------|
| `decks`        | OneToMany | `Deck`    | Decks using this archetype |

### Sprite Display (F2.12)

Archetype sprites are rendered wherever a deck name appears in the UI (between the short tag badge and the deck name). The `archetype_sprites()` Twig function generates `<img>` tags from `pokemonSlugs`:

- **Source:** [martimlobao/pokesprite](https://github.com/martimlobao/pokesprite) Gen 9 fork — 1478 box sprite PNGs
- **Build pipeline:** `make sprites` downloads the tarball to `assets/vendor/sprites/pokemon/` (gitignored cache), then `copy-webpack-plugin` copies them to `public/build/sprites/pokemon/` during `make assets`
- **Rendering:** Fixed 40px height via CSS, `image-rendering: pixelated` for crisp pixel art. Native dimensions vary (23×22 to 73×62); width scales proportionally
- **Accessibility:** `alt` and `title` attributes use a human-readable name derived from the slug (`iron-thorns` → `Iron Thorns`)
- **Table alignment:** `.archetype-sprites` wrapper uses `min-width: 120px` in table contexts for consistent name alignment across 1–3 sprites

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
