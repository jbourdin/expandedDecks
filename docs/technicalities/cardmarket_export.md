# Cardmarket Wishlist Export

> **Audience:** Developer, AI Agent · **Scope:** Technical Deep-Dive

← Back to [Documentation](../docs.md)

---

## Overview

The Cardmarket export generates a text list compatible with Cardmarket's **"Add Decklist to Wants"** import textarea. This feature (F6.11) allows users to copy a formatted deck list and paste it directly into Cardmarket to populate a wants list for purchasing cards.

**Service:** `App\Service\DeckList\CardmarketWishlistFormatter`

## Format

Cardmarket identifies cards by **name + abilities + attacks** (for Pokemon) or by **name only** (for Trainers and Energy). It does **not** use set codes, expansion names, or collector numbers.

| Card type | Format | Example |
|-----------|--------|---------|
| Pokemon (with ability) | `{qty}x {name} {ability} {attack}` | `4x Genesect V Fusion Strike System Techno Blast` |
| Pokemon (attacks only) | `{qty}x {name} {attack1} {attack2}` | `3x Shadow Rider Calyrex V Shadow Mist Astral Barrage` |
| Trainer | `{qty}x {name}` | `4x Battle VIP Pass` |
| Special Energy | `{qty}x {name}` | `4x Double Turbo Energy` |
| Basic Energy | *(excluded)* | — |

### Key rules

1. **Abilities come before attacks**, both in their original card order (not alphabetically sorted).
2. **Basic energies are excluded** (Grass, Fire, Water, Lightning, Psychic, Fighting, Darkness, Metal, Fairy).
3. **Special energies are included** (Double Turbo Energy, Double Colorless Energy, etc.).
4. The export uses the **pre-computed minified card views** (`DeckVersion.minifiedCardViews` JSON column), same base as F6.8.

## Data flow

```
DeckVersion.minifiedCardViews (pre-computed JSON)
  → MinifiedCardView::deserializeGrouped()
    → reconstructs MinifiedCardView DTOs with abilityNames + attackNames
  → CardmarketWishlistFormatter.format()
    → iterates MinifiedCardView entries
    → applies name overrides (CARDMARKET_NAME_OVERRIDES)
    → appends abilities + attacks for Pokemon cards
    → excludes basic energies
    → returns formatted text
```

For deck versions not yet re-enriched with the `minifiedCardViews` column, the formatter falls back to `MinifiedCardViewBuilder.buildGrouped()` (runtime computation).

## Card identity fields

`CardIdentity` stores both sorted signatures (for deduplication) and original-order names (for export):

| Field | Purpose | Example |
|-------|---------|---------|
| `abilitySignature` | Sorted, comma-joined — used in unique constraint | `Fusion Strike System` |
| `abilityNames` | Original card order — used in Cardmarket export | `Fusion Strike System` |
| `attackSignature` | Sorted, comma-joined — used in unique constraint | `Astral Barrage,Shadow Mist` |
| `attackNames` | Original card order — used in Cardmarket export | `Shadow Mist,Astral Barrage` |

The sorted signatures ensure correct deduplication (same card identity regardless of attack order in TCGdex data). The unsorted names preserve the order Cardmarket expects.

## Name overrides

Some card names are ambiguous on Cardmarket. The `CARDMARKET_NAME_OVERRIDES` constant in `CardmarketWishlistFormatter` maps PTCG names to Cardmarket-compatible names:

```php
private const array CARDMARKET_NAME_OVERRIDES = [
    "Professor's Research" => "Professor's Research - Professor Sada",
];
```

This is necessary because Cardmarket lists Professor's Research as separate products per professor (Magnolia, Oak, Sada, Turo, etc.), while PTCG treats them as the same card.

To add new overrides, append entries to this constant. Use the exact card name as it appears in the deck list as the key, and the Cardmarket product name as the value.

## Known limitations

- **Pokemon without enrichment data**: If `CardPrinting`/`CardIdentity` is not yet populated (enrichment pending), Pokemon cards fall back to name-only format, which may not match on Cardmarket.
- **Cards not on Cardmarket**: Some cards (promos, special printings) may not exist in Cardmarket's database. These will show as "not added" after import.
- **Ambiguous card names**: Cards that exist under multiple Cardmarket product names (like Professor's Research) need explicit name overrides. Without an override, Cardmarket may not find the card.
- **TCGdex ability gaps**: TCGdex may not return abilities for all cards. If a Pokemon's identity has no ability data, the export omits them (attacks only).

## Related features

- **F6.10** — Card identity and printing model (data source for abilities/attacks)
- **F6.8** — Minified deck list export (same card selection logic)
- **F6.12** — Future: Cardmarket API integration using `cardmarketProductId`

## Files

| File | Role |
|------|------|
| `src/Service/DeckList/CardmarketWishlistFormatter.php` | Formats the export text |
| `src/Service/DeckList/MinifiedCardViewBuilder.php` | Resolves cheapest printings (fallback for pre-existing versions) |
| `src/Service/DeckList/MinifiedCardView.php` | DTO carrying card data including ability/attack names |
| `src/Entity/CardIdentity.php` | Stores ability/attack signatures and names |
| `src/Service/CardIdentity/CardIdentityResolver.php` | Populates CardIdentity from TCGdex data |
| `src/Service/Tcgdex/TcgdexApiClient.php` | Fetches abilities and attacks from TCGdex API |
