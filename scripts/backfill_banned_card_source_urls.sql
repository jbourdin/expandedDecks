-- Converge banned_card.source_url on the correct announcement link per ban wave.
--
-- Two separate problems are fixed here.
--
-- 1. Production still links 17 bans to the generic pokemon.com banned-card
--    list page instead of the announcement that introduced them. An older
--    BannedCardsSyncService stamped every ban it provisioned with that generic
--    URL. Commit 01e7677 replaced the stamping with BannedCardSeedData, which
--    carries the specific per-expansion announcement URL, but
--    BannedCardSeedData::fillIfNull() only writes source_url when it is NULL --
--    by design, so that admin edits survive re-running app:banned-cards:seed.
--    Rows created before 01e7677 were never NULL, so the seed has skipped them
--    ever since.
--
-- 2. pokemon.com has retired every banned-list announcement older than the Mega
--    Evolution era. Seven of the ten waves are affected and now point at the
--    Internet Archive, matching the constants in
--    src/Service/BannedCardSeedData.php.
--
--    Each timestamp is pinned to the newest capture verified to contain the
--    announcement text. This matters: for several of these the most recent
--    captures archived pokemon.com's Imperva bot-challenge page rather than the
--    article, so neither the newest snapshot nor a timestamp-less /web/<url>
--    redirect reliably lands on readable content.
--
-- Three waves keep their live links and get no statement below:
--   2015-06-15  Lysandre's Trump Card, Bulbanews (predates pokemon.com's own
--               ban coverage; different domain, still live)
--   2025-10-10  Mega Evolution
--   2026-04-10  Mega Evolution: Perfect Order
--
-- Liveness was established by opening each URL in a browser, not from a script.
-- pokemon.com sits behind Imperva, which answers 200 with a challenge page even
-- for paths that do not exist, so HTTP status from a client proves nothing in
-- either direction.
--
-- Keyed on effective_date rather than card_name: one announcement covers all
-- the cards banned on its date, whereas card_name is ambiguous -- Unown is
-- banned on two separate dates under two different announcements, and several
-- names exist in both straight- and curly-apostrophe spellings
-- (Lt. Surge's / Lt. Surge’s, Maxie's / Maxie’s).
--
-- Every statement guards on the specific stale values it expects to replace --
-- the generic list URL and the retired live URL -- so the script is idempotent,
-- safe to run against either database or twice, and unable to overwrite an
-- admin edit made after it was written.
--
-- Deliberately NOT filtered on deleted_at IS NULL. No ban is soft-deleted
-- today, and correcting one that is means it comes back with a working link
-- rather than a stale one if it is ever restored.
--
-- DATE(effective_date) is safe: every row stores exact midnight and the column
-- has no NULLs. The function call defeats the index, which is irrelevant
-- against 27 rows.
--
-- Rows affected: 24 of the 27 bans, on production and on any database seeded
-- before these URL changes. Both converge on the same final state.
--
-- @see docs/features.md F6.14 -- Banned cards public page

-- Sun & Moon—Burning Shadows, 2 cards: Archeops, Forest of Giant Plants.
UPDATE banned_card
SET source_url = 'https://web.archive.org/web/20251009150613/https://www.pokemon.com/us/sun-moon-burning-shadows-banned-list-and-rule-changes-quarterly-announcement/'
WHERE DATE(effective_date) = '2017-08-18'
  AND source_url IN (
    'https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list',
    'https://www.pokemon.com/us/sun-moon-burning-shadows-banned-list-and-rule-changes-quarterly-announcement/'
  );

-- Sun & Moon—Celestial Storm, 3 cards: Ghetsis, Hex Maniac, Puzzle of Time.
UPDATE banned_card
SET source_url = 'https://web.archive.org/web/20260107021812/https://www.pokemon.com/us/sun-moon-celestial-storm-banned-list-and-rule-changes-quarterly-announcement/'
WHERE DATE(effective_date) = '2018-08-17'
  AND source_url IN (
    'https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list',
    'https://www.pokemon.com/us/sun-moon-celestial-storm-banned-list-and-rule-changes-quarterly-announcement/'
  );

-- Sun & Moon—Team Up, 3 cards: Delinquent, Maxie's Hidden Ball Trick, Unown DAMAGE.
UPDATE banned_card
SET source_url = 'https://web.archive.org/web/20251127005634/https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/'
WHERE DATE(effective_date) = '2019-02-15'
  AND source_url IN (
    'https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list',
    'https://www.pokemon.com/us/sun-moon-team-up-banned-list-and-rule-changes-quarterly-announcement/'
  );

-- Sun & Moon—Cosmic Eclipse, 10 cards, the largest single ban wave.
UPDATE banned_card
SET source_url = 'https://web.archive.org/web/20260510162917/https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/'
WHERE DATE(effective_date) = '2019-11-15'
  AND source_url IN (
    'https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list',
    'https://www.pokemon.com/us/sun-moon-cosmic-eclipse-banned-list-and-rule-changes-announcement/'
  );

-- Sword & Shield—Vivid Voltage, 4 cards: Milotic, Oranguru, Sableye, Shaymin-EX.
UPDATE banned_card
SET source_url = 'https://web.archive.org/web/20260207042335/https://www.pokemon.com/us/sword-shield-vivid-voltage-banned-list-and-rule-changes-announcement/'
WHERE DATE(effective_date) = '2020-11-27'
  AND source_url IN (
    'https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list',
    'https://www.pokemon.com/us/sword-shield-vivid-voltage-banned-list-and-rule-changes-announcement/'
  );

-- Scarlet & Violet—Paldean Fates, 1 card: Scoop Up Net.
UPDATE banned_card
SET source_url = 'https://web.archive.org/web/20251204063553/https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-paldean-fates-banned-list-and-rule-changes-announcement'
WHERE DATE(effective_date) = '2024-02-09'
  AND source_url IN (
    'https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list',
    'https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-paldean-fates-banned-list-and-rule-changes-announcement'
  );

-- Scarlet & Violet—Stellar Crown, 1 card: Duskull.
UPDATE banned_card
SET source_url = 'https://web.archive.org/web/20260117133609/https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-stellar-crown-banned-list-and-rule-changes-announcement'
WHERE DATE(effective_date) = '2024-09-27'
  AND source_url IN (
    'https://www.pokemon.com/us/play-pokemon/about/pokemon-tcg-banned-card-list',
    'https://www.pokemon.com/us/play-pokemon/about/scarlet-violet-stellar-crown-banned-list-and-rule-changes-announcement'
  );
