# TCGdex Known Data Issues

> **Audience:** Developer, AI Agent · **Scope:** Reference

← Back to [Documentation](../docs.md) | [Enrichment](enrichment.md)

---

Issues discovered while building the Expanded Decks enrichment pipeline. Some are worked around in our code via static overrides; others are documented here for awareness.

## Missing Sets

TCGdex does not index dedicated energy-only sets. These are PTCG Live / physical product identifiers.

| Set Code | Set Name | Status |
|----------|----------|--------|
| SVE | Scarlet & Violet Energies | Source data merged ([PR #1125](https://github.com/tcgdex/cards-database/pull/1125), 2026-03-06), images not deployed to CDN |
| MEE | Mega Evolution Energy | Source data merged (same PR), images not deployed to CDN |
| SME | Sun & Moon Energy | Not in source database |
| XYE | XY Energy | Not in source database |
| BWE | Black & White Energy | Not in source database |

## Colliding Set Codes

A PTCG code does not identify one TCGdex set. Two shapes occur:

**Parent / gallery subset** — TCGdex splits Trainer Gallery, Galarian Gallery, Shiny Vault and Classic Collection cards into their own set. Whether that set reuses the parent's code or carries a suffixed one (`ASR-TG`) changes between upstream snapshots, so prod and local currently disagree.

| Code | Parent | Gallery subset | Overlapping localIds |
|------|--------|----------------|----------------------|
| ASR | `swsh10` (246 cards) | `swsh10.5tg` (30) | 30 — all `TG01`–`TG30` |
| BRS | `swsh9` (216) | `swsh9.5tg` (30) | 30 — all `TG` |
| LOR | `swsh11` (247) | `swsh11.5tg` (30) | 30 — all `TG` |
| CRZ | `swsh12.5` (230) | `swsh12.5gg` (70) | 70 — all `GG` |
| SHF | `swsh4.5` (195) | `swsh4.5sv` (122) | 122 — all `SV` |
| SIT | `swsh12` (245) | `swsh12.5tg` (30) | 30 — all `TG` |
| CEL | `cel25` (50) | `cel25cc` (25) | **0** — `cel25cc` is `CC001`–`CC025` only |

**Unrelated sets** — `RR` denotes both `ex7` Team Rocket Returns (EX, 2004) and `pl2` Rising Rivals (Platinum, 2009). Numbers 1–111 exist in both and name *different* cards; only 112–114 and `RT1`–`RT6` are unambiguous. Nothing but the card name can separate these.

Resolved at read time — see [Candidate Sets & Ambiguity Resolution](enrichment.md#candidate-sets--ambiguity-resolution) (F6.16). Note that `officialCardCount` is not an upper bound on card numbers: secret rares push `swsh10` to 216 against an official count of 189.

Thirteen sets carry no PTCG code at all in either table and are therefore unreachable by code, including `sma` (Hidden Fates Shiny Vault) — the same gallery shape as `swsh4.5sv`, but with no code to resolve from.

## Incorrect Images

| Card | TCGdex ID | Issue | Workaround |
|------|-----------|-------|------------|
| Team Flare Grunt GEN 73 | `g1-73` | Asset `https://assets.tcgdex.net/en/xy/g1/73/high.webp` shows the full-art version (which should be at `g1/73a/high.webp`) instead of the regular Uncommon | `IMAGE_OVERRIDES` in `CardEnricher` redirects to XY 129 image |

## Inaccurate Rarity

TCGdex uses coarser rarity labels than the physical cards, causing different card variants to appear at the same rarity tier.

| Card(s) | TCGdex rarity | Actual rarity | Impact | Workaround |
|---------|---------------|---------------|--------|------------|
| SM-era GX cards (e.g. Dialga GX UPR 100 vs FLI 125) | Both "Ultra Rare" | UPR 100 = Rare Holo GX (one ★), FLI 125 = Rare Ultra / Full Art (two ★★) | Minified export could pick the more expensive Full Art | Price-based sorting (tier 4+: price ASC) distinguishes them |
| Trainer Gallery cards (e.g. Flareon BRS TG01 vs VIV 26) | Both "Rare" | BRS TG01 = premium full-art holo-only variant (€11), VIV 26 = regular Rare (€0.50) | Minified export picked the expensive TG variant | `PREMIUM_CARD_NUMBER_PREFIXES` (TG, GG) excluded from minified passes 1–2; `RarityTierMapper` bumps TG/GG cards to tier 5 |
| Cards beyond official set count | Same as regular cards | Secret Rare / Full Art | Minified export could pick expensive variants | `RarityTierMapper` bumps cards with numeric number > `set.cardCount.official` to tier 5 |

## Missing Card Variants

In some sets, TCGdex stores the regular and alternate-art versions with swapped or confusing IDs:

| Set | Issue |
|-----|-------|
| Generations (g1) | Regular cards use letter-suffixed IDs (e.g. `g1-28a` = regular Jolteon-EX, `g1-28` = full art). Our API client now tries the exact card number before stripping letter suffixes. |

## Missing Card Images

Some cards exist in TCGdex's database but have no `image` field and no image on the CDN (404). When a deck list references one of these cards, the enricher falls back to `findImageByName()` which finds an image from another printing of the same card. The `CardPrinting` records for these cards are stored with `imageUrl = null` (excluded from minified selection by the `imageUrl IS NOT NULL` filter).

| tcgdex_id | Card | Set | PokemonTCG.io |
|-----------|------|-----|---------------|
| `sm3.5-68` | Ultra Ball | Shining Legends (SLG) | `sm35/68` exists |
| `sm3.5-69` | Double Colorless Energy | Shining Legends (SLG) | `sm35/69` exists |
| `sm6-82` | Dialga GX | Forbidden Light (FLI) | `sm6/82` exists |
| `smp-SM193` | Garchomp & Giratina GX | SM Promos (PR-SM) | `smp/SM193` exists |
| `tk-dp-l-10` | Quick Ball | Trainer Kit (TK3L) | Missing everywhere |

**Impact on original card display:** When a user imports e.g. `SLG 68` (Ultra Ball), the enricher finds the card in TCGdex (correct metadata, no image), then tries PokemonTCG.io CDN, then `findImageByName("Ultra Ball")` which returns an image from another printing. During enrichment, each fallback URL is verified with a HEAD request before being persisted. At mosaic generation time, `CardImageResolver` provides an additional fallback chain including sibling printings of the same card.

**Impact on minified export:** None — `CardPrinting` records with null images are excluded by the query filter.

## Other Limitations

- **Energy cards from energy sets** (SVE, SME, etc.) cannot be looked up via the API — `findCard()` returns null. Worked around with `ENERGY_SET_IMAGES` static map and `findSimplestBasicEnergyByName()` fallback.
- **Name search is contains-match** — searching for "Pikachu" returns "Pikachu V", "Flying Pikachu", etc. Code filters to exact name equality.
- **Pricing data gaps** — not all cards have Cardmarket or TCGPlayer pricing. Cards without pricing sort after priced cards.
- **Japanese set codes** (S6K, SM8) have no card data — fall back to name-based matching.
