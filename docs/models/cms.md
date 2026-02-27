# CMS Model

> **Audience:** Developer, AI Agent · **Scope:** Architecture, Reference

← Back to [Main Documentation](../docs.md) | [Features](../features.md#f11--cms-content-pages)

Translation is handled via the **Knp Translatable** pattern (`knplabs/doctrine-behaviors`). Each translatable entity has a companion `*Translation` entity keyed by locale. The **fallback locale** is `en` — if no translation exists for the requested locale, the English version is returned.

---

## Entity: `App\Entity\MenuCategory`

Admin-managed grouping for content pages. Displayed in site navigation or footer, ordered by position.

### Fields

| Field        | Type               | Nullable | Description |
|--------------|--------------------|----------|-------------|
| `id`         | `int` (auto)       | No       | Primary key |
| `position`   | `int`              | No       | Display order (ascending). Default: `0`. |
| `createdAt`  | `DateTimeImmutable` | No      | Creation timestamp. |
| `updatedAt`  | `DateTimeImmutable` | No      | Last modification timestamp. |

### Constraints

- `position`: required, >= 0. Unique values recommended but not enforced (ties sorted by `id`).
- Categories without any published page are hidden from the navigation.

### Relations

| Relation       | Type      | Target                    | Description |
|----------------|-----------|---------------------------|-------------|
| `pages`        | OneToMany | `Page`                    | Pages in this category |
| `translations` | OneToMany | `MenuCategoryTranslation` | Localized names |

---

## Entity: `App\Entity\MenuCategoryTranslation`

Localized fields for `MenuCategory`. One row per locale per category.

### Fields

| Field    | Type          | Nullable | Description |
|----------|---------------|----------|-------------|
| `id`     | `int` (auto)  | No       | Primary key |
| `locale` | `string(5)`   | No       | ISO 639-1 locale code (e.g. `"en"`, `"fr"`). |
| `name`   | `string(100)` | No       | Translated category name (e.g. "Getting Started", "Premiers pas"). |

### Constraints

- Unique constraint on (`translatable_id`, `locale`) — one translation per locale per category
- `name`: required, 1–100 characters

---

## Entity: `App\Entity\Page`

A CMS content page. Non-translatable fields live here; translatable fields (title, slug, content, SEO) live in `PageTranslation`.

### Fields

| Field          | Type               | Nullable | Description |
|----------------|--------------------|----------|-------------|
| `id`           | `int` (auto)       | No       | Primary key |
| `slug`         | `string(150)`      | No       | Canonical URL slug (e.g. `"how-to-borrow"`). Used as fallback when no localized slug exists. |
| `menuCategory` | `MenuCategory`     | Yes      | Optional grouping for navigation (F11.2). Null = page not in any menu. |
| `isPublished`  | `bool`             | No       | Whether the page is publicly visible. Default: `false`. |
| `canonicalUrl` | `string(255)`      | Yes      | Optional canonical URL override for SEO. When set, a `<link rel="canonical">` tag is rendered. |
| `noIndex`      | `bool`             | No       | Whether to add `<meta name="robots" content="noindex">`. Default: `false`. |
| `createdAt`    | `DateTimeImmutable` | No      | Creation timestamp. |
| `updatedAt`    | `DateTimeImmutable` | No      | Last modification timestamp. |

### Constraints

- `slug`: required, unique, 1–150 characters, URL-friendly (`[a-z0-9-]+`)
- `isPublished`: unpublished pages are only visible to admins (preview mode)

### Relations

| Relation       | Type      | Target            | Description |
|----------------|-----------|-------------------|-------------|
| `menuCategory` | ManyToOne | `MenuCategory`    | Optional menu grouping |
| `translations` | OneToMany | `PageTranslation` | Localized content and SEO |

---

## Entity: `App\Entity\PageTranslation`

Localized fields for `Page`. One row per locale per page. Contains the page's title, content (Markdown), localized slug, and SEO metadata.

### Fields

| Field             | Type          | Nullable | Description |
|-------------------|---------------|----------|-------------|
| `id`              | `int` (auto)  | No       | Primary key |
| `locale`          | `string(5)`   | No       | ISO 639-1 locale code (e.g. `"en"`, `"fr"`). |
| `title`           | `string(200)` | No       | Translated page title. |
| `slug`            | `string(150)` | Yes      | Localized URL slug (e.g. `"comment-emprunter"`). Null = use the canonical `Page.slug`. |
| `content`         | `text`        | No       | Page body in **Markdown** format. Rendered to HTML via `league/commonmark` on display. |
| `metaTitle`       | `string(70)`  | Yes      | SEO `<title>` tag. Falls back to `title` when empty. Max 70 chars (SEO best practice). |
| `metaDescription` | `string(160)` | Yes      | SEO `<meta name="description">`. Max 160 chars (SEO best practice). |
| `ogImage`         | `string(255)` | Yes      | Open Graph image URL for social sharing (`<meta property="og:image">`). |

### Constraints

- Unique constraint on (`translatable_id`, `locale`) — one translation per locale per page
- `title`: required, 1–200 characters
- `slug`: optional, unique across all `PageTranslation` rows when provided, URL-friendly (`[a-z0-9-]+`)
- `content`: required, non-empty
- `metaTitle`: max 70 characters when provided
- `metaDescription`: max 160 characters when provided

### Locale Fallback

> **@see** docs/features.md F11.3 — Page rendering & locale fallback

Resolution order when a page is requested:

1. Look for a `PageTranslation` matching the user's preferred locale (F9.1)
2. If not found, fall back to `en` (English)
3. If no `en` translation exists, return 404

The same fallback chain applies to `MenuCategoryTranslation` for navigation rendering.

---

## URL Routing

Pages are served at:

```
/{locale}/pages/{localizedSlug}
```

Where `{localizedSlug}` is resolved from `PageTranslation.slug` for the current locale, falling back to `Page.slug` (the canonical slug). Example:

- English: `/en/pages/how-to-borrow`
- French: `/fr/pages/comment-emprunter` (localized slug exists)
- German: `/de/pages/how-to-borrow` (no German slug → canonical fallback)
