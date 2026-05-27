-- Backfill Archetype.last_published_at from variant decks only.
--
-- Mirrors the runtime rule in ArchetypeFreshnessListener: only decks with
-- owner_id IS NULL (archetype variants / editorial decklists) contribute to
-- archetype freshness. Player-owned decks are excluded.
--
-- This script intentionally overwrites the existing last_published_at value
-- (it does NOT take GREATEST with the current row): the whole point of the
-- fix is to correct rows that were wrongly bumped by player-owned decks, so
-- the existing value cannot be trusted as a floor.
--
-- Fallback: when an archetype has no variant decks, reset last_published_at
-- to first_published_at — that preserves the trait invariant
-- (last_published_at >= first_published_at) without inventing a fake update.
--
-- Only published archetypes are touched; drafts stay NULL until first publish.

UPDATE archetype a
SET last_published_at = COALESCE(
    (SELECT MAX(d.updated_at)
     FROM deck d
     WHERE d.archetype_id = a.id
       AND d.owner_id IS NULL),
    a.first_published_at
)
WHERE a.is_published = 1;
