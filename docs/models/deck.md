# Deck Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md)

## Entity: `App\Entity\Deck`

Represents a physical Pokemon TCG deck owned by a user.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `name`             | `string(100)`      | No       | Owner-given name for this deck (e.g. "My Lugia VSTAR"). |
| `owner`            | `User`             | No       | The user who owns this physical deck. |
| `archetype`        | `string(80)`       | Yes      | Archetype identifier (e.g. `"lugia-vstar"`). Manually set by the owner. |
| `archetypeName`    | `string(100)`      | Yes      | Human-readable archetype name (e.g. `"Lugia VSTAR"`). |
| `format`           | `string(30)`       | No       | Play format. Default: `"Expanded"`. |
| `languages`        | `json`             | No       | Array of ISO 639-1 language codes present in the deck (e.g. `["en", "ja"]`). A deck can contain cards in mixed languages. |
| `estimatedValue`   | `decimal(8,2)`     | Yes      | Owner-provided estimated monetary value of the deck (in EUR). Visible to the owner, organizers, and event staff. Helps inform delegation decisions for costly decks. |
| `status`           | `string(20)`       | No       | Current availability status. See Status enum below. Default: `"available"`. |
| `notes`            | `text`             | Yes      | Owner's private notes about the deck (e.g. sleeve color, missing cards, condition). |
| `rawList`          | `text`             | Yes      | The original PTCG text format pasted by the owner. Preserved for reference and re-import. |
| `createdAt`        | `DateTimeImmutable` | No      | Deck registration timestamp. |
| `updatedAt`        | `DateTimeImmutable` | Yes     | Last modification timestamp. |

### Status Enum: `App\Enum\DeckStatus`

| Value       | Description |
|-------------|-------------|
| `available` | Deck is ready to be borrowed. Default state. |
| `reserved`  | A borrow request has been approved but the deck hasn't been handed off yet. |
| `lent`      | Deck is currently lent to a borrower (or held by event staff). |
| `retired`   | Owner has retired this deck. Not available for borrowing. |

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

A deck can contain cards in multiple languages (e.g. `["en", "ja"]` for a mixed English/Japanese deck). This helps borrowers know if they'll be able to read the cards.

### Constraints

- `name`: required, 2–100 characters
- `owner`: required, must be a verified user
- `languages`: required, at least one language code
- `estimatedValue`: optional, >= 0 when provided
- `status`: required, must be a valid `DeckStatus` value

### Relations

| Relation           | Type         | Target entity  | Description |
|--------------------|--------------|----------------|-------------|
| `owner`            | ManyToOne    | `User`         | User who owns this deck |
| `cards`            | OneToMany    | `DeckCard`     | Cards in this deck (the list) |
| `borrows`          | OneToMany    | `Borrow`       | Borrow history for this deck |

---

## Entity: `App\Entity\DeckCard`

A single card entry in a deck list. Parsed from PTCG text format via `ptcgo-parser`, validated against TCGdex.

### Fields

| Field              | Type               | Nullable | Description |
|--------------------|--------------------|----------|-------------|
| `id`               | `int` (auto)       | No       | Primary key |
| `deck`             | `Deck`             | No       | The deck this card belongs to. |
| `cardName`         | `string(100)`      | No       | Card name (e.g. `"Lugia VSTAR"`). |
| `setCode`          | `string(20)`       | No       | Set abbreviation (e.g. `"SIT"`, `"BRS"`). |
| `cardNumber`       | `string(20)`       | No       | Card number within the set (e.g. `"186"`, `"TG1"`). |
| `quantity`         | `int`              | No       | Number of copies in the deck (1–4 for most cards, unlimited for basic energy). |
| `cardType`         | `string(20)`       | No       | Card category: `"pokemon"`, `"trainer"`, or `"energy"`. |
| `trainerSubtype`   | `string(20)`       | Yes      | Trainer subcategory: `"supporter"`, `"item"`, `"tool"`, or `"stadium"`. Null for non-trainer cards. |
| `tcgdexId`         | `string(30)`       | Yes      | TCGdex card identifier. Used for image retrieval and validation. |

### Constraints

- Unique constraint on (`deck`, `setCode`, `cardNumber`) — no duplicate card entries per deck
- `quantity`: required, >= 1
- `cardType`: required, one of `"pokemon"`, `"trainer"`, `"energy"`
- `trainerSubtype`: required when `cardType` is `"trainer"`, null otherwise

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
4. **Stores** the parsed cards as `DeckCard` entities + preserves the raw text in `Deck.rawList`

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
