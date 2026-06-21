# Schema Drift (known residual)

> **Audience:** Developer, AI Agent · **Scope:** Technical Deep-Dive

← Back to [Documentation](../docs.md) | [Coding Standards](../standards/coding.md)

---

The local DB is kept in sync with **production** (prod is the source of truth for
schema). `doctrine:schema:validate` reports the **mapping as correct**, but the
DB-sync check still lists a small, **known, accepted residual** of *cosmetic* drift.
None of it is semantic — no data, types, nullability, or defaults are affected.

This page exists so the residual is not mistaken for real changes when generating
migrations.

## What was reconciled

- **Column defaults/types** → fixed in the entity mappings (e.g. `options: ['default' => …]`
  on booleans, `deck.format`, `deck_card.card_locale`, `card_identity.*`, and the
  `homepage_layout_translation.og_description` `TEXT` length). These no longer drift.
- **Framework tables** (`messenger_messages`, `sessions`) → excluded from ORM schema
  management via `dbal.schema_filter` in `config/packages/doctrine.yaml`. They are owned
  by the Messenger transport / PDO session handler, not our mappings.

## Accepted residual (cosmetic only)

These keep appearing in `doctrine:schema:update --dump-sql` and will reappear in any
generated migration. They are intentionally **not** reconciled (it would mean either
pinning legacy identifier names into mappings, or an `ALTER` migration on prod for zero
functional gain):

- **Legacy index / FK / unique-constraint names** — old migrations named these explicitly
  (e.g. `uniq_event_tag_name`, `fk_banned_card_representative_printing`,
  `idx_event_pending_transfer_to`); Doctrine now derives hash names. FK *constraint* names
  cannot be set via attributes anyway. Tables: `event`, `event_event_tag`, `event_tag`,
  `user` (calendar token), `banned_card`, `banned_card_printing`.
- **`(DC2Type:datetime_immutable)` column comments** — DBAL 4 dropped comment-based type
  tracking; the live columns still carry the old comment. There is no mapping switch to
  keep it. Columns: `event.pending_transfer_requested_at`, `banned_card.{effective_date,
  deleted_at,created_at}`, `event_tag.created_at`, `tcgdex_card.tcgdex_updated_at`.
- One extra DB index, `banned_card.IDX_banned_card_deleted_at`, not declared in the mapping.

## How to handle when generating a migration

`doctrine:migrations:diff` will include the residual lines above. **Strip them** from the
generated migration and keep only your intended change (this is what the F19.8 migration
did). Cross-check against this list.

## Eliminating it (deferred)

A one-shot normalization migration could rename the indexes to Doctrine's convention and
drop the stale comments, clearing the residual for good. Deferred by decision (it would
`ALTER` prod for cosmetic-only gain); revisit if the residual becomes noisy.
