# Translation Workflow

> **Audience:** Developer, AI Agent · **Scope:** Technical deep-dive · **Parent:** [Documentation index](../docs.md)

Data model and mechanics of the content-translation workflow ([epic #612](https://github.com/jbourdin/expandedDecks/issues/612)). This document grows with the epic; as of F9.7 it covers the foundation layer: the `#[Translatable]` attribute, the revision tables, and the snapshot listener.

## Design overview

The live `*Translation` tables stay authoritative for all rendering — the locale fallback chain, hreflang, and feeds are untouched. Each translated content type gains a companion **revision table** holding the full per-locale history **including the English source**. Revisions are purely additive: nothing on a public page ever reads them (staleness is denormalized, see below).

| Content type | Live table | Revision entity | Source of the `en` snapshot |
|---|---|---|---|
| CMS page | `page_translation` | `PageTranslationRevision` | `PageTranslation` (`en` row) |
| Archetype | `archetype_translation` | `ArchetypeTranslationRevision` | `ArchetypeTranslation` (`en` row) |
| Menu category | `menu_category_translation` | `MenuCategoryTranslationRevision` | `MenuCategoryTranslation` (`en` row) |
| Archetype variant notes | `deck_translation` | `DeckTranslationRevision` | **`Deck.notes` directly** (pattern bend) |

**The variant pattern bend:** `Deck.notes` stays the canonical source-locale content for every deck — user decks never grow translation rows, and no data migration touched the existing read paths. `DeckTranslation` holds only non-source locales, and only archetype variants (`Deck::isArchetypeVariant()`) enter the workflow.

## The `#[Translatable]` attribute

`App\Attribute\Translatable` is a parameterless property attribute on translation-entity properties. It is the **single registry** deciding, at once:

1. **Snapshot scope** — the revision listener snapshots exactly these fields;
2. **Staleness trigger** — a source-locale save creates a revision (and flags siblings) only when one of these fields actually changed;
3. **Translator form** (F9.10) — the editing UI is built from the same list.

A per-locale property *without* the attribute stays editor-managed and invisible to the workflow — deliberately so for `ogImage` (per-locale config, not linguistic content) and `translator` (workflow metadata). This is the same philosophy as `TimestampExemptChangeTrait`: an `ogImage`-only save is not content activity.

## Revision rows

Every revision entity implements `TranslationRevisionInterface` and uses `TranslationRevisionTrait`:

- `locale` — all locales appear in the same table, source included;
- `state` — `TranslationRevisionState` enum (`draft` / `pending_review` / `validated` / `rejected`). Auto-snapshots from direct editor saves are born `Validated`; the review states are only traversed by translator submissions (F9.9);
- `author` — the contributor, `SET NULL` on user deletion (GDPR, F19.8 pattern);
- `sourceRevision` — self-referencing FK to the source-locale revision this translation was based on, `NULL` on source rows. Staleness is the integer comparison "a newer source revision exists"; the FK also enables the exact old-vs-new source diff in the translator view (F9.10);
- the `#[Translatable]` fields, mirrored as typed columns.

The `sourceRevision` association is declared per-entity (not in the trait) because a self-reference needs the concrete type. Static factories (`fromTranslation()`, plus `fromDeckNotes()` for the variant source) copy the fields; `tests/Entity/TranslationRevisionConsistencyTest.php` fails whenever a `#[Translatable]` field lacks its mirror column or its factory copy line.

## The snapshot listener

`TranslationRevisionListener` (Doctrine `onFlush` + `postFlush`) enforces the epic's invariant: **every live write leaves a validated revision behind**, whichever door it came through.

- Revisions are created *inside the same flush* (`persist` + `computeChangeSet` in `onFlush`), so history is transactionally consistent with the live write.
- Source-locale revisions are created first, so a translation changed in the same flush links its in-flush source revision; otherwise the latest source revision is looked up from the database.
- Updates only count when the changeset intersects the `#[Translatable]` fields — `ogImage`-only and no-op saves produce nothing.
- When a source revision is created, sibling non-source live rows get `sourceOutdated = 1` via a bulk SQL `UPDATE` in `postFlush` (same re-entrancy-avoidance pattern as `ArchetypeFreshnessListener`).

`sourceOutdated` is denormalized on the live `*Translation` rows (and `DeckTranslation`) precisely so that public pages never query the revision tables — the reader-facing outdated notice (US-T6, F9.14) reads a boolean already loaded with the row.

**Backfill:** a data migration (`Version20260816102343`) seeds one `validated` revision per existing live translation row at deploy time — source locales first, then non-source rows linked to their subject's source revision, mirroring the listener's runtime behavior. Non-source backfilled revisions credit the live row's `translator` (F19.8) as author; source revisions keep a `NULL` author. `NOT EXISTS` guards make the backfill skip rows that already have revisions. History is therefore complete from day one: every live row has a revision, and every translation has a `sourceRevision` (except translations whose subject has no source-locale row at all, which downstream features treat as "staleness unknown").

## Roles & access (F9.8)

Two roles, one voter:

- **`ROLE_TRANSLATION_EDITOR`** — a single role paired with `User.translationLocales` (json list of ISO 639-1 target locales). Locales are data, not capabilities: adding a language to a translator is a data change, never a `security.yaml` change (same lesson as F19.4).
- **`ROLE_TRANSLATION_MODERATOR`** — reviews submissions (F9.9). Granted to CMS editors through the role hierarchy (`ROLE_CMS_EDITOR` inherits it, and `ROLE_ADMIN` inherits `ROLE_CMS_EDITOR`), per the #612 rollout decision; a standalone grant stays possible for future pure moderators. Moderating never implies authoring, and never shortcuts the review workflow.

`TranslationVoter` votes on the `TRANSLATE` attribute with a `TranslationTarget` (content + target locale) subject. Access requires **all** of: reachable `ROLE_TRANSLATION_EDITOR`, target locale in `translationLocales`, target locale ≠ source locale (source content is editor-managed — translators read it, never write it), and for decks `isArchetypeVariant()`. Admins manage the roles and the locale list on the admin user page; the source locale is never offered as a target and submitted values are filtered against `kernel.enabled_locales`.

## Deliberately not signals

- **Deck-list changes** never flag variant-notes translations: a list change that matters to readers warrants an English notes update, and that update triggers the flag through the normal path (editorial practice, decided in #612).
- **Variant names** are not translatable — TCG vernacular stays in English per [French translation standards](../standards/french_translation.md).

## Roadmap

The remaining layers are tracked as sub-issues of [#612](https://github.com/jbourdin/expandedDecks/issues/612): roles & voter (F9.8), review workflow (F9.9), translator UI (F9.10–F9.12), archetype+variant combined view (F9.13), reader notice (F9.14), notifications (F9.15).
