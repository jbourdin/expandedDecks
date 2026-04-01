# Homepage Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md#f10--global-ux-concerns)

The homepage layout is stored as a JSON-based block system, allowing admins to compose the page from reorderable blocks without code changes. Translation follows the same Knp Translatable-inspired pattern as CMS pages.

---

## Entity: `App\Entity\HomepageLayout`

Stores the ordered list of homepage blocks and their non-translatable settings. Only one layout should be published at a time (singleton pattern).

### Fields

| Field         | Type                | Nullable | Description |
|---------------|---------------------|----------|-------------|
| `id`          | `int` (auto)        | No       | Primary key |
| `blocks`      | `json`              | No       | Ordered list of block definitions (see Block Structure below). |
| `isPublished` | `bool`              | No       | Whether this layout is the active homepage. Default: `false`. |
| `createdAt`   | `DateTimeImmutable`  | No      | Creation timestamp. |
| `updatedAt`   | `DateTimeImmutable`  | Yes     | Last modification timestamp. |

### Constraints

- Only one layout should have `isPublished = true` at a time. The application enforces this at the service level.

### Relations

| Relation       | Type      | Target                         | Description |
|----------------|-----------|--------------------------------|-------------|
| `translations` | OneToMany | `HomepageLayoutTranslation`    | Per-locale translatable block content |

---

## Entity: `App\Entity\HomepageLayoutTranslation`

Per-locale translatable content for homepage blocks. The `blockTranslations` JSON is keyed by block index (matching the position in `HomepageLayout.blocks`).

### Fields

| Field               | Type          | Nullable | Description |
|---------------------|---------------|----------|-------------|
| `id`                | `int` (auto)  | No       | Primary key |
| `homepageLayout`    | `HomepageLayout` | No    | Parent layout (FK). |
| `locale`            | `string(5)`   | No       | ISO 639-1 locale code (e.g. `"en"`, `"fr"`). |
| `blockTranslations` | `json`        | No       | Per-block translatable content, keyed by block index. |

### Constraints

- Unique constraint on (`homepage_layout_id`, `locale`) — one translation per locale per layout.

---

## Enum: `App\Enum\HomepageBlockType`

String-backed PHP enum defining the available block types.

| Case            | Value            | Description |
|-----------------|------------------|-------------|
| `Hero`          | `hero`           | Full-width hero banner with title, subtitle, and CTA buttons. |
| `RichText`      | `richText`       | Markdown content block (optionally sourced from a CMS page). |
| `Carousel`      | `carousel`       | Swipeable image slideshow with per-item links and scheduling. |
| `LatestPages`   | `latestPages`    | Auto-populated list of recent published pages from a category. |
| `FeaturedDeck`  | `featuredDeck`   | Highlighted deck card with description and image. |
| `FeaturedEvent` | `featuredEvent`  | Highlighted event card with description and image. |

### Metadata methods

- `label()` — Translation key for human-readable name.
- `icon()` — Bootstrap icon class.
- `hasTranslatableContent()` — Whether the block stores content in `HomepageLayoutTranslation`.

---

## Block Structure (JSON)

Each entry in `HomepageLayout.blocks` is an object with common and type-specific properties.

### Common properties

| Property      | Type           | Description |
|---------------|----------------|-------------|
| `type`        | `string`       | One of the `HomepageBlockType` values. |
| `columnWidth` | `int\|null`    | Bootstrap grid column width (1–12). `null` = full width. |
| `cssClasses`  | `string\|null` | Optional extra CSS classes. |
| `startAt`     | `string\|null` | ISO 8601 datetime — block visible after this time. |
| `endAt`       | `string\|null` | ISO 8601 datetime — block hidden after this time. |

### Type-specific properties

**hero** — Translatable: `title`, `subtitle`, `ctaButtons[]` (each with `label`, `route`, `style`).

**richText** — `pageSlug` (optional, source content from a CMS page). Translatable: `content` (Markdown, used when no `pageSlug`).

**carousel** — `items[]` (each with `image`, `alt`, `link`, `startAt`, `endAt`). Translatable: per-item `alt` text.

**latestPages** — `categorySlug` (menu category English name), `limit` (max pages to show).

**featuredDeck** — `shortTag`, `image`. Translatable: `title`, `description`.

**featuredEvent** — `eventId`, `image`. Translatable: `title`, `description`.

---

## Translatable Content (JSON)

`HomepageLayoutTranslation.blockTranslations` is a JSON object keyed by block index (as string):

```json
{
  "0": {
    "title": "Share the Expanded Experience",
    "subtitle": "Borrow real decks, play at events...",
    "ctaButtons": [
      {"label": "Register", "route": "app_register", "style": "primary"}
    ]
  },
  "2": {
    "content": "## Welcome\n\nMarkdown content here..."
  }
}
```

Only blocks whose type has `hasTranslatableContent() === true` need entries.
