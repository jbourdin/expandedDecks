# Translation Workflow

> **Audience:** Developer, AI Agent · **Scope:** Technical deep-dive · **Parent:** [Documentation index](../docs.md)

Data model and mechanics of the content-translation workflow ([epic #612](https://github.com/jbourdin/expandedDecks/issues/612)). It covers the foundation layer (the `#[Translatable]` attribute, the revision tables, the snapshot listener), the roles, the review workflow, the translator UI, and the content types plugged into it — including banned & staple cards (F9.17).

## Design overview

The live `*Translation` tables stay authoritative for all rendering — the locale fallback chain, hreflang, and feeds are untouched. Each translated content type gains a companion **revision table** holding the full per-locale history **including the English source**. Revisions are purely additive: nothing on a public page ever reads them (staleness is denormalized, see below).

| Content type | Live table | Revision entity | Source of the `en` snapshot |
|---|---|---|---|
| CMS page | `page_translation` | `PageTranslationRevision` | `PageTranslation` (`en` row) |
| Archetype | `archetype_translation` | `ArchetypeTranslationRevision` | `ArchetypeTranslation` (`en` row) |
| Menu category | `menu_category_translation` | `MenuCategoryTranslationRevision` | `MenuCategoryTranslation` (`en` row) |
| Archetype variant notes | `deck_translation` | `DeckTranslationRevision` | **`Deck.notes` directly** (pattern bend) |
| Banned-card explanation | `banned_card_translation` | `BannedCardTranslationRevision` | **`BannedCard.explanation` directly** (pattern bend) |
| Staple-card note | `staple_card_translation` | `StapleCardTranslationRevision` | **`StapleCard.note` directly** (pattern bend) |

**The variant pattern bend:** `Deck.notes` stays the canonical source-locale content for every deck — user decks never grow translation rows, and no data migration touched the existing read paths. `DeckTranslation` holds only non-source locales, and only archetype variants (`Deck::isArchetypeVariant()`) enter the workflow. Banned and staple cards (F9.17) follow the same bend: the canonical English copy stays on the entity, the live translation tables hold non-source locales only, and only cards with non-empty source copy enter the workflow. Card **names are never translated** — they are proper nouns.

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

## Review workflow (F9.9)

The `translation_review` state machine (Symfony Workflow, `config/packages/workflow.yaml`, same pattern as `borrow`) runs on the four revision entities: `draft` → `pending_review` (**submit**) → `validated` (**approve**) / `rejected` (**reject**) → `draft` (**rework**). Auto-snapshots from editor saves are born `validated` and never traverse the machine.

`TranslationReviewService` owns the transitions:

- **submit** re-checks `TranslationVoter` and refuses stale work: if a newer source revision exists than the one the draft is pinned to, a `StaleTranslationSourceException` (translatable message key) is thrown — the translator updates against the current source first (US-T5).
- **approve** (moderator-only) is the **single write path to live translation rows**: it copies the `#[Translatable]` fields via the per-entity `applyTo()` (mirror of `fromTranslation()`, guarded by the consistency test), creates the live row on first approval of a locale (e.g. the first FR `DeckTranslation`), credits the contributor as the live row's `translator` (F19.8 byline), and **recomputes** `sourceOutdated` — the source may have moved again between submission and approval, in which case the flag stays raised. During this write the revision listener is suppressed (`TranslationRevisionSuppression`): the approved revision *is* the history entry, so the every-live-write-leaves-a-revision invariant holds without duplication.
- **reject** stamps `reviewedBy`/`reviewedAt`/`reviewComment`; **rework** returns the revision to `draft` for the contributor.

**No shortcut, structurally:** `approve` only exists from `pending_review`. A moderator's own translation follows the same explicit submit-then-approve path — self-approval is permitted but is a second, deliberate action.

`TranslationDraftProvider` opens a translation session (US-T3/US-T4): it returns the existing not-yet-validated revision for a (content, locale) pair — pending, rejected, or draft — or creates a new draft prefilled from the live row (the latest validated content) and pinned to the latest source revision via `LatestSourceRevisionProvider` (shared with the snapshot listener).

## Translator UI (F9.10–F9.13)

The workspace lives at `/admin/translations` (access: `ROLE_TRANSLATION_EDITOR` or `ROLE_TRANSLATION_MODERATOR`, wired in `security.yaml` before the `^/admin` catch-all).

- **Entry points (US-T1/US-T2):** public pages and archetype pages render Translate items in their existing action dropdown — one per locale granted by `translatable_locales()` (Twig function over `TranslationVoter`).
- **Queue (F9.12):** one React component (`TranslationQueue`), tabs per content type, contributor/reviewer flavors from `TranslationQueueProvider` (`GET /admin/translations/queue-data`). Variant work aggregates on the archetype row as counters — one row per context.
- **Contextual view (F9.10):** `TranslationEditor` renders `TranslationViewDataBuilder` sections — field-aligned split form, markdown source pre-rendered server-side with a raw toggle, TipTap (`MarkdownEditor`) targets, SEO-budget counters, copy-source, guidelines panel. Drafts autosave (`POST …/draft`, fields filtered by `TranslationDraftFieldUpdater`); submit returns 409 with the translated stale error when the source moved (US-T5). The staleness diff is computed client-side by `assets/utils/wordDiff.ts` (dependency-free word-level LCS) over the pinned-vs-latest source values.
- **Review mode (F9.11):** same component; pending sections lock inputs and expose Approve / Reject-with-comment; rejected sections show the comment and a Rework action.
- **Archetype context (F9.13):** `buildArchetypeContext()` = archetype section + one accordion section per variant (auto-expanded when stale or in workflow), each with independent revisions and actions. `Deck::localizedNotes()` / `translationFor()` feed the public variant selector and the localized RSS feed.

## Reader-facing notice (F9.14, US-T6)

Translated content whose live row is `sourceOutdated` shows a discreet notice linking to the source-locale URL: `_partials/translation_outdated_notice.html.twig` on pages and archetype descriptions, and the equivalent block inside `ArchetypeVariantSelector` for variant notes (`notesOutdated` in the payload). The check reads the denormalized flag only — public pages never touch the revision tables.

## Notifications (F9.15)

`TranslationNotificationService` (in-app `Notification` + email, per-type preferences, recipient `preferredLocale`):

- **submitted** → all moderators (`UserRepository::findTranslationModerators()`) except the submitting contributor;
- **approved / rejected** → the contributor, with the reviewer comment; skipped on self-review;
- **source outdated** → the live row's credited translator, emitted by the snapshot listener only for rows flipping fresh→outdated (an already-outdated translation is never re-notified).

Deck notifications link to the archetype context view. Emails live under `templates/email/translation/`.

## Draft channel locales (F9.16)

A channel locale is **published** (`Channel.locales`) or **draft** (`Channel.draftLocales`). Draft locales are the staging ground for a new language: translators assigned the locale, moderators, and admins browse the site in it (marked entry in the locale switcher, prefixed URLs render, session/preferred locale allowed); everyone else is 302-redirected to the published equivalent and never sees the locale in the switcher, hreflang, sitemap, or robots (all of which derive from the published list only). `ChannelLocaleVisibility` centralizes the who-may-see decision; `LocaleListener` enforces it on the request path behind the session-cookie gate, keeping anonymous pages CDN-cacheable. Admin content forms use `Channel::getAllLocales()` so draft-locale content is editable before publication; publishing is an explicit admin action on the channel form.

## Admin-managed locales (F9.18)

The locale universe itself is admin data, not code. The channel form offers an "Add a language" input (any ISO 639-1 code, datalist-assisted, validated with `Symfony\Intl\Languages`); a new language **always starts as a draft locale** — it has no content and no UI chrome yet, so publishing is a later, deliberate tick. Consequences:

- **Routing:** `_locale` route requirements use the permissive shared `LocaleRequirement::PATTERN` (`[a-z]{2}`); whether a locale exists on the channel — and who may browse it as a draft — is decided by `LocaleListener`, which 302-redirects unknown and unauthorized locales to the published equivalent.
- **Chrome fallback:** UI chrome renders through the XLIFF catalogues; a locale without `translations/messages.<locale>.xlf` falls back to English. The channel form shows a non-blocking warning listing such locales — adding the XLIFF stays a developer task per language.
- **Derived surfaces:** assignable translator locales (F9.8) are the union of every channel's published + draft locales minus the source; the profile's `preferredLocale` choices are the current channel's published locales plus the user's visible drafts.
- **Card names** and other TCG vernacular stay in English whatever the locale.

## Card translations (F9.17)

Banned and staple cards ride the whole pipeline above unchanged — voter, review workflow, snapshot listener, staleness, notifications — with a few type-specific touches:

- **Factories:** `fromBannedCardExplanation()` / `fromStapleCardNote()` snapshot the entity's canonical copy as the source revision (mirror of `fromDeckNotes()`); the consistency test covers both pairs.
- **Read path:** `BannedCard::localizedExplanation()` and `StapleCard::localizedNote()` (translation row with fallback to the canonical copy) feed the public listings. The detail modal shows the reader-facing outdated notice (F9.14) from the denormalized `sourceOutdated` flag carried as a data attribute, and `?translationPreview=1` renders a pending revision in place of the live copy for users passing `TranslationPreviewResolver`.
- **Queue:** the Cards tab merges both types. The **contributor** flavor additionally lists untranslated cards that have source copy as a worklist ("To translate" badge) — this is the epic's untranslated-content filter, scoped to cards where the catalogue is finite. The reviewer flavor only ever shows in-workflow rows.
- **Backfill:** `Version20260818181233` seeds one source revision per card with non-empty copy (`NOT EXISTS`-guarded, soft-deleted cards skipped), mirroring the F9.7 backfill.

## Deliberately not signals

- **Deck-list changes** never flag variant-notes translations: a list change that matters to readers warrants an English notes update, and that update triggers the flag through the normal path (editorial practice, decided in #612).
- **Variant names** are not translatable — TCG vernacular stays in English per [French translation standards](../standards/french_translation.md).

## Roadmap

All layers of [#612](https://github.com/jbourdin/expandedDecks/issues/612) are implemented: foundation (F9.7), roles & voter (F9.8), review workflow (F9.9), translator UI (F9.10–F9.12), archetype+variant combined view (F9.13), reader notice (F9.14), notifications (F9.15), plus the follow-ups: channel draft locales (F9.16, [#775](https://github.com/jbourdin/expandedDecks/issues/775)) and card translations (F9.17, [#776](https://github.com/jbourdin/expandedDecks/issues/776)).
