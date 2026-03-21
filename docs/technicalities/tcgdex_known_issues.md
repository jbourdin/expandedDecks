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

## Other Limitations

- **Energy cards from energy sets** (SVE, SME, etc.) cannot be looked up via the API — `findCard()` returns null. Worked around with `ENERGY_SET_IMAGES` static map and `findSimplestBasicEnergyByName()` fallback.
- **Name search is contains-match** — searching for "Pikachu" returns "Pikachu V", "Flying Pikachu", etc. Code filters to exact name equality.
- **Pricing data gaps** — not all cards have Cardmarket or TCGPlayer pricing. Cards without pricing sort after priced cards.
- **Japanese set codes** (S6K, SM8) have no card data — fall back to name-based matching.
