# Schema Sync

> **Audience:** Developer, AI Agent · **Scope:** Technical Deep-Dive

← Back to [Documentation](../docs.md) | [Coding Standards](../standards/coding.md)

---

The local DB is kept in sync with **production** (prod is the source of truth for schema),
and `doctrine:schema:validate` is **fully in sync** — both the mapping and the DB-sync
checks report OK. This page records how a batch of long-standing drift was cleared so it
doesn't creep back in.

## How the drift was reconciled

Drift had accumulated from old hand-written migrations and dependency upgrades. It was
cleared in three ways, by category:

1. **Column defaults/types** → declared on the entity mappings (e.g. `options: ['default' => …]`
   on booleans, `deck.format`, `deck_card.card_locale`, `card_identity.*`, and the
   `homepage_layout_translation.og_description` `TEXT` length).
2. **Framework-managed tables** (`messenger_messages`, `sessions`) → excluded from ORM
   schema management via `dbal.schema_filter` in `config/packages/doctrine.yaml`. They are
   owned by the Messenger transport / PDO session handler and managed by those bundles, not
   by our mappings — so we never diff or migrate them here.
3. **Legacy index/FK names + obsolete `(DC2Type:datetime_immutable)` comments** → normalized
   to Doctrine's current conventions by a one-time, metadata-only migration
   (`Version20260621081335`). RENAME INDEX / comment changes are instant in MySQL 8.

## Keeping it clean

- After changing an entity, generate the migration with `doctrine:migrations:diff` and the
  diff should contain **only your intended change**. If it shows index renames, comment
  removals, or `messenger_messages`/`sessions`, something regressed — investigate rather
  than committing the noise.
- New framework tables (future bundles) may need to be added to the `schema_filter` regex.
