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
| `isFooter`   | `bool`             | No       | Whether to display this category in the site footer instead of the navigation bar. Default: `false`. |
| `createdAt`  | `DateTimeImmutable` | No      | Creation timestamp. |
| `updatedAt`  | `DateTimeImmutable` | No      | Last modification timestamp. |

### Constraints

- `position`: required, >= 0. Unique values recommended but not enforced (ties sorted by `id`).
- `isFooter`: when `true`, the category appears in the site footer instead of the top navigation bar.
- Categories without any published page are hidden from both the navigation and the footer.

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

A CMS content page. Non-translatable fields live here; translatable fields (title, content) live in `PageTranslation`.

### Fields

| Field          | Type               | Nullable | Description |
|----------------|--------------------|----------|-------------|
| `id`           | `int` (auto)       | No       | Primary key |
| `slug`         | `string(150)`      | No       | URL slug (e.g. `"how-to-borrow"`). |
| `menuCategory` | `MenuCategory`     | Yes      | Optional grouping for navigation (F11.2). Null = page not in any menu. |
| `isPublished`  | `bool`             | No       | Whether the page is publicly visible. Default: `false`. |
| `ogImage`      | `string(255)`      | Yes      | Open Graph image URL for social sharing. Accepts relative (`/api/editor/image/...`) or absolute URLs. |
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
| `translations` | OneToMany | `PageTranslation` | Localized content |

---

## Entity: `App\Entity\PageTranslation`

Localized fields for `Page`. One row per locale per page. Contains the page's title and content (Markdown).

### Fields

| Field             | Type          | Nullable | Description |
|-------------------|---------------|----------|-------------|
| `id`              | `int` (auto)  | No       | Primary key |
| `locale`          | `string(5)`   | No       | ISO 639-1 locale code (e.g. `"en"`, `"fr"`). |
| `title`           | `string(200)` | No       | Translated page title. |
| `content`         | `text`        | No       | Page body in **Markdown** format. Rendered to HTML via `league/commonmark` on display. |

### Constraints

- Unique constraint on (`translatable_id`, `locale`) — one translation per locale per page
- `title`: required, 1–200 characters
- `content`: required, non-empty

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
/{locale}/pages/{slug}
```

Where `{slug}` is the `Page.slug` field. Example: `/en/pages/how-to-borrow`, `/fr/pages/how-to-borrow`.

### Reserved listing-intro slugs

Two slugs are reserved (centralised in `App\Constants\ListingIntroPage`) and back the editable intro block on the banned-cards / staple-cards listing pages:

| Slug                  | Canonical route          |
|-----------------------|--------------------------|
| `banned-cards-intro`  | `app_banned_card_list`   |
| `staple-cards-intro`  | `app_staple_card_list`   |

Direct hits to `/{locale}/pages/{reserved-slug}` redirect (HTTP 301) to the canonical listing route, and the navigation Twig helper `cms_page_url(page)` links straight to the listing route to skip the redirect.

**Menu placement.** A reserved-slug page with `menuCategory = null` keeps the listing link in its hardcoded position in `base.html.twig` (between Archetypes and Decks). Assigning a `MenuCategory` makes the link appear inside that category's dropdown — sorted by `position` like any sibling page — and suppresses the hardcoded fallback (controlled by the `listing_intro_in_menu(slug)` helper). The dropdown label is the page's translated title.
