# Localization

> **Audience:** Developer · **Scope:** Technical Reference

<- Back to [Main Documentation](../docs.md) | [Features](../features.md)

---

## Supported Locales

- `en` (English) — default
- `fr` (French)

Enabled in `config/packages/translation.yaml`:

```yaml
framework:
    default_locale: en
    enabled_locales: ['en', 'fr']
```

## Locale Detection

`LocaleListener` (priority 4, after firewall) resolves the locale in this order:

1. **Route-level `_locale`** -> editorial routes use `/{_locale}/` prefix (e.g. `/en/archetypes/...`); when present, the URL dictates the language
2. **Authenticated user** -> `user.preferredLocale`, stored in session
3. **Anonymous with session `_locale`** -> use it
4. **Anonymous without** -> detect from `Accept-Language` header against enabled locales, fallback `en`, store in session

The `<html lang>` attribute reflects the request locale.

## Locale-prefixed URL Routing

Editorial routes (archetypes, CMS pages) include a `/{_locale}/` prefix so each language has a distinct URL, enabling SEO differentiation and hreflang tags.

### Routes with locale prefix

| Route name | Path |
|------------|------|
| `app_archetype_list` | `/{_locale}/archetypes` |
| `app_archetype_show` | `/{_locale}/archetypes/{slug}` |
| `app_archetype_variant_compare` | `/{_locale}/archetypes/{slug}/compare/{shortTagA}/{shortTagB}` |
| `app_page_show` | `/{_locale}/pages/{slug}` |
| `app_page_category` | `/{_locale}/pages/category/{id}` |
| `app_home_localized` | `/{_locale}/` |

### Routes without locale prefix (session-based)

Decks, events, auth, admin, API, homepage (`/`) — all retain session-based locale.

### Homepage special case

`/` remains session-based for UX. Its canonical points to `/en/`. Localized homepage routes (`/en/`, `/fr/`) exist for hreflang purposes.

### Legacy URL redirects

All old unprefixed editorial URLs (e.g. `/archetypes/iron-thorns`) 301-redirect to their `/en/` equivalents. Defined in `config/routes.yaml`.

### Locale switcher

A navbar `EN | FR` toggle is visible when the channel supports multiple locales. On editorial routes, it swaps `_locale` directly in the URL. On session-based routes, it goes through `LocaleSwitchController` (`/locale/{_locale}`) which sets the session locale and redirects back.

### URL generation

Symfony auto-fills `{_locale}` from the current request context when generating URLs. All `path()` calls in Twig and `UrlGeneratorInterface::generate()` calls in PHP automatically include the locale prefix for editorial routes — no explicit `_locale` parameter needed.

## Translation Catalogues

XLIFF format: `translations/messages.en.xlf`, `translations/messages.fr.xlf`.

### Key Naming Convention

Dot-notation, organized by domain:

| Prefix                   | Usage                                    |
|--------------------------|------------------------------------------|
| `app.nav.*`              | Navigation links                         |
| `app.common.*`           | Shared buttons/labels (view, edit, etc.) |
| `app.dashboard.*`        | Dashboard sections                       |
| `app.deck.*`             | Deck catalog, show, new, edit            |
| `app.event.*`            | Event list, show, new, edit              |
| `app.borrow.*`           | Borrow show, list, inbox, badges         |
| `app.auth.*`             | Login, register, password reset          |
| `app.profile.*`          | Profile page                             |
| `app.email.*`            | Email templates and subjects             |
| `app.flash.*`            | Flash messages (controller layer)        |
| `app.form.label.*`       | Form field labels                        |
| `app.form.placeholder.*` | Form field placeholders                  |
| `app.footer.*`           | Footer                                   |

### Templates

Use `{{ 'key'|trans }}` or `{{ 'key'|trans({'%param%': value}) }}`.

### Controllers

All controllers extend `AbstractAppController`, which overrides `addFlash()` to auto-translate messages:

```php
$this->addFlash('success', 'app.flash.borrow.approved');
```

### Emails

Email templates receive a `locale` context variable (the recipient's `preferredLocale`) and use explicit locale on every `|trans` call:

```twig
{{ 'app.email.greeting'|trans({'%name%': recipient.screenName}, 'messages', locale) }}
```

Email subjects are translated server-side via `TranslatorInterface::trans()` with the recipient's locale.

## Timezone Display

### Twig Filters

| Filter           | Description                                          |
|------------------|------------------------------------------------------|
| `user_datetime`  | Full datetime in user's timezone and locale           |
| `user_date`      | Date only in user's timezone and locale               |
| `tz_abbr`        | Short timezone abbreviation (e.g. "CET", "EST")      |

All filters accept an optional `?string $timezone` parameter to override the user's timezone (used for event-local display).

### Event Date Display

The `event/_datetime.html.twig` partial shows event dates in the event's timezone with an abbreviation. When the user's timezone differs, a clock icon tooltip displays the user's local time:

```twig
{% include 'event/_datetime.html.twig' with {dt: event.date, tz: event.timezone} %}
```

### UTC Storage

All database datetimes are stored in UTC. The event form uses `model_timezone: 'UTC'` and `view_timezone: event.timezone` for automatic conversion. PHP timezone is set to UTC via `.symfony.local.yaml`.

## Adding a New Locale

1. Add the locale code to `enabled_locales` in `config/packages/translation.yaml`
2. Create `translations/messages.{locale}.xlf` by copying `messages.en.xlf` and translating all `<target>` values
3. Add the locale as a choice in `ProfileFormType::preferredLocale`
4. Test: log in as a user with the new locale, verify all pages render correctly
