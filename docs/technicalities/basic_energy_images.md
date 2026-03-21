# Basic Energy Image Reference

> **Audience:** Developer, AI Agent · **Scope:** Reference Data

← Back to [Documentation](../docs.md) | [Enrichment](enrichment.md)

---

## Structured Data

All known basic energy printings are catalogued in [`data/basic_energies.json`](/data/basic_energies.json). Each entry includes set identification, PTCGL code (when relevant), rarity, release date, an array of image URLs from multiple sources, and a `defaultForMinified` flag indicating which printing to use in the minified deck list export.

**Defaults for minified export:**
- 8 standard types (Grass, Fire, Water, Lightning, Psychic, Fighting, Darkness, Metal) → **MEE** (Mega Evolution Energy, 2025-09-25) — the latest plain Common basic energy
- Fairy Energy → **sm1** (Sun & Moon, 2017-02-03) — the latest dated Common Fairy Energy (Fairy type was removed in SWSH era)

## Context

TCGdex does not index dedicated energy sets (SVE, SME, XYE, BWE, MEE). These sets are PTCG Live / physical product identifiers, not traditional card sets tracked by TCGdex. The plain basic energy images must be sourced elsewhere.

### Image CDNs

- **pokemon.com** — Official Pokemon CDN. Has MEE, SVE, and NRG sets. No API key required. URL pattern: `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/{SET}/{SET}_EN_{NUMBER}.png`
- **PokemonTCG.io** — Former API (now Scrydex, paid). Has SVE set (16 cards: 2 artwork variants per type). Image CDN still accessible. URL pattern: `https://images.pokemontcg.io/{set_id}/{NUMBER}_hires.png` — covers **every basic energy ever printed**, from Base Set (1999) to SV era

### PokemonTCG.io Data (GitHub)

The [PokemonTCG/pokemon-tcg-data](https://github.com/PokemonTCG/pokemon-tcg-data) repository contains structured JSON card data. Only the **SVE** set exists as a dedicated energy set (16 cards). Basic energies also appear in regular expansion sets (bw1, sm1, xy1, swsh12pt5, sv1, etc.) under the name "{Type} Energy" (older sets) or "Basic {Type} Energy" (SV era).

Key observations:
- **Fairy Energy** exists only in pre-SWSH sets: g1, xy1, xy12, sm1, sm3 (5 printings total)
- **MEE, SME, XYE, BWE, NRG** do not exist in the PokemonTCG.io dataset
- Cards named "Basic {Type} Energy" only appear in SV-era sets; older sets use "{Type} Energy"

### TCGdex Source Database (GitHub)

The [tcgdex/cards-database](https://github.com/tcgdex/cards-database) repository contains the source TypeScript files compiled into the TCGdex API. Both **SVE** and **MEE** sets were added via [PR #1125](https://github.com/tcgdex/cards-database/pull/1125) (merged 2026-03-06):

- **SVE** (`data/Scarlet & Violet/Scarlet & Violet Energy/`) — 24 cards (3 artwork variants per type, #001–024), all Common
- **MEE** (`data/Mega Evolution/Mega Evolution Energy/`) — 8 cards (#001–008), all Common

However, **images are not yet published** on the TCGdex CDN — all `https://assets.tcgdex.net/en/sv/sve/*/high.webp` and `https://assets.tcgdex.net/en/me/mee/*/high.webp` URLs return 404 as of 2026-03-21. The contributor provided card images in a [Google Drive folder](https://drive.google.com/drive/folders/1QWiOalSdmGDTwZ0LIxBv4BhTrkfqYy1Q) (6 languages) but they haven't been deployed yet.

No other dedicated energy sets (SME, XYE, BWE, NRG) exist in the TCGdex source database.

---

## MEE — Mega Energy Era (SV-style artwork)

Modern SV-era artwork with the "Basic" banner. 8 cards, no Fairy. Available on pokemon.com CDN only.

| # | Energy Name            | pokemon.com URL                                                                                         |
|---|------------------------|---------------------------------------------------------------------------------------------------------|
| 1 | Basic Grass Energy     | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_1.png`           |
| 2 | Basic Fire Energy      | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_2.png`           |
| 3 | Basic Water Energy     | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_3.png`           |
| 4 | Basic Lightning Energy | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_4.png`           |
| 5 | Basic Psychic Energy   | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_5.png`           |
| 6 | Basic Fighting Energy  | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_6.png`           |
| 7 | Basic Darkness Energy  | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_7.png`           |
| 8 | Basic Metal Energy     | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/MEE/MEE_EN_8.png`           |

## SVE — Scarlet & Violet Energies (2023-03-31)

SV-era artwork with "Basic" banner. No Fairy. 24 cards in TCGdex source (3 artwork variants per type), 16 in PokemonTCG.io (2 variants).

| #  | Energy Name            | pokemon.com URL                                                                                         | PokemonTCG.io URL                                    |
|----|------------------------|---------------------------------------------------------------------------------------------------------|------------------------------------------------------|
| 1  | Basic Grass Energy     | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_1.png`           | `https://images.pokemontcg.io/sve/1_hires.png`       |
| 2  | Basic Fire Energy      | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_2.png`           | `https://images.pokemontcg.io/sve/2_hires.png`       |
| 3  | Basic Water Energy     | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_3.png`           | `https://images.pokemontcg.io/sve/3_hires.png`       |
| 4  | Basic Lightning Energy | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_4.png`           | `https://images.pokemontcg.io/sve/4_hires.png`       |
| 5  | Basic Psychic Energy   | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_5.png`           | `https://images.pokemontcg.io/sve/5_hires.png`       |
| 6  | Basic Fighting Energy  | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_6.png`           | `https://images.pokemontcg.io/sve/6_hires.png`       |
| 7  | Basic Darkness Energy  | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_7.png`           | `https://images.pokemontcg.io/sve/7_hires.png`       |
| 8  | Basic Metal Energy     | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SVE/SVE_EN_8.png`           | `https://images.pokemontcg.io/sve/8_hires.png`       |
| 9  | Basic Grass Energy     |                                                                                                         | `https://images.pokemontcg.io/sve/9_hires.png`       |
| 10 | Basic Fire Energy      |                                                                                                         | `https://images.pokemontcg.io/sve/10_hires.png`      |
| 11 | Basic Water Energy     |                                                                                                         | `https://images.pokemontcg.io/sve/11_hires.png`      |
| 12 | Basic Lightning Energy |                                                                                                         | `https://images.pokemontcg.io/sve/12_hires.png`      |
| 13 | Basic Psychic Energy   |                                                                                                         | `https://images.pokemontcg.io/sve/13_hires.png`      |
| 14 | Basic Fighting Energy  |                                                                                                         | `https://images.pokemontcg.io/sve/14_hires.png`      |
| 15 | Basic Darkness Energy  |                                                                                                         | `https://images.pokemontcg.io/sve/15_hires.png`      |
| 16 | Basic Metal Energy     |                                                                                                         | `https://images.pokemontcg.io/sve/16_hires.png`      |

## NRG — Legacy Energy Set (SM-style artwork, includes Fairy)

SM-era artwork without "Basic" banner. 9 cards including Fairy. Cards numbered 26–34 within the NRG set. Only on pokemon.com CDN (not in PokemonTCG.io dataset).

| #  | Energy Name       | pokemon.com URL                                                                                         |
|----|-------------------|---------------------------------------------------------------------------------------------------------|
| 26 | Grass Energy      | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/NRG/NRG_EN_26.png`          |
| 27 | Fire Energy       | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/NRG/NRG_EN_27.png`          |
| 28 | Water Energy      | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/NRG/NRG_EN_28.png`          |
| 29 | Lightning Energy  | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/NRG/NRG_EN_29.png`          |
| 30 | Psychic Energy    | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/NRG/NRG_EN_30.png`          |
| 31 | Fighting Energy   | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/NRG/NRG_EN_31.png`          |
| 32 | Darkness Energy   | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/NRG/NRG_EN_32.png`          |
| 33 | Metal Energy      | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/NRG/NRG_EN_33.png`          |
| 34 | Fairy Energy      | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/NRG/NRG_EN_34.png`          |

## Fairy Energy — All Known Sources

Fairy type was removed starting with Sword & Shield. No dedicated energy set (SVE/MEE) includes it.

| Source          | Set   | URL                                                                                                     |
|-----------------|-------|---------------------------------------------------------------------------------------------------------|
| pokemon.com     | NRG   | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/NRG/NRG_EN_34.png`          |
| pokemon.com     | SM3   | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/SM3/SM3_EN_169.png`         |
| pokemon.com     | XY1   | `https://assets.pokemon.com/static-assets/content-assets/cms2/img/cards/web/XY1/XY1_EN_140.png`         |
| PokemonTCG.io   | sm1   | `https://images.pokemontcg.io/sm1/172_hires.png`                                                       |
| PokemonTCG.io   | sm3   | `https://images.pokemontcg.io/sm3/169_hires.png`                                                       |
| PokemonTCG.io   | xy1   | `https://images.pokemontcg.io/xy1/140_hires.png`                                                       |
| PokemonTCG.io   | g1    | `https://images.pokemontcg.io/g1/83_hires.png`                                                         |
| PokemonTCG.io   | xy12  | `https://images.pokemontcg.io/xy12/99_hires.png`                                                       |
| TCGdex          | sm1   | `https://assets.tcgdex.net/en/sm/sm1/172/high.webp`                                                    |

---

## Sets Not Found on CDNs

The following energy set codes returned 404 on pokemon.com's CDN and do not exist in the PokemonTCG.io dataset:
- **SME** (Sun & Moon Energy)
- **XYE** (XY Energy)
- **BWE** (Black & White Energy)
- **SWE** (Sword & Shield Energy)

These are PTCG Live product codes without corresponding CDN assets.

## All Basic Energy Printings — CDN Availability

The PokemonTCG.io CDN (`images.pokemontcg.io`) has images for **every basic energy printing** across all eras, from Base Set (1999) to Scarlet & Violet. The pokemon.com CDN has a subset (modern sets only). TCGdex CDN has none of the dedicated energy sets and none of the basic energy cards from regular sets.

### pokemon.com CDN — available sets

Energy-only sets: **MEE**, **SVE**, **SVE2** (variant 2), **SVE3** (variant 3), **NRG** (includes Fairy)

Regular sets with energies: BW1, COL1, DP1, EX1, EX9, EX13, EX16, G1, HGSS1, SM2, SV02, SWSH6, SWSH8, XY1, XY12

Not available: Base Set (BS), Base Set 2, Gym Heroes/Challenge, Neo Genesis, E-Card, SM1, SM3, SWSH base sets

### PokemonTCG.io CDN — complete coverage

Every set that contains basic energies has working image URLs: base1, base4, gym1, gym2, neo1, ecard1, ex1, ex9, ex13, ex16, col1, dp1, hgss1, bw1, xy1, xy12, g1, sm1, sm2, sm3, sm4, sv1, sv2, sv3, sv3pt5, sv6pt5, sve, swsh6, swsh7, swsh8, swsh12pt5, tk1a, tk1b, tk2a, tk2b

URL pattern: `https://images.pokemontcg.io/{set_id}/{card_number}.png` (small) or `{card_number}_hires.png` (large)

## Data Sources Summary

| Source                       | Energy sets | Regular set energies | Free       | Notes                                                    |
|------------------------------|-------------|----------------------|------------|----------------------------------------------------------|
| pokemon.com CDN              | MEE, SVE, SVE2, SVE3, NRG | Partial (modern only) | Yes | Official CDN, no API key. Predictable URL pattern.       |
| PokemonTCG.io CDN            | SVE (16 cards) | All (base1 to sv6pt5) | Deprecated | Migrated to Scrydex. Image CDN still up. Complete coverage. |
| PokemonTCG.io data (GitHub)  | SVE         | All core sets        | Yes        | Static JSON, all basic energy printings across all sets.  |
| TCGdex source (GitHub)       | SVE, MEE    | All core sets        | Yes        | Source data exists but images not deployed to CDN yet.    |
| TCGdex API/CDN               | None        | None (for energies)  | Yes        | Free API, but no energy set data served.                 |
| Scrydex API                  | Likely all  | Likely all           | No         | $29/month minimum. Successor to PokemonTCG.io.          |
