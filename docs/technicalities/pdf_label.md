# PDF Label Card (Home Printing)

> **Audience:** Developer, AI Agent · **Scope:** Technical Reference

← Back to [Main Documentation](../docs.md) | [Feature F5.7](../features.md) | [USB HID Scanner](scanner.md) | [Camera QR Scanner](camera_scanner.md)

---

## Problem

The existing label printing system ([F5.1](../features.md) / [F5.2](../features.md)) relies on a **Zebra thermal printer** available at event venues via PrintNode. Users preparing decks **at home** before an event have no way to generate a physical label for their deck box.

Without a label, the deck cannot be scanned at the event for hand-off (F4.3) or return (F4.4). The user must either wait until they arrive at a venue with a Zebra printer, or have someone else print the label remotely — neither option is practical for pre-event preparation.

## Strategy

Generate **downloadable PDFs** containing TCG card-sized labels that the user can print on any home printer, cut out, and slip into a card sleeve at the front of the deck box. Two variants are available:

1. **Simple label** — single card with deck identity and QR code
2. **Foldable label** — two cards side by side (book layout) with a deck list on the back

Key choices:

- **Dompdf** — HTML/CSS to PDF conversion via Twig templates, well integrated with Symfony
- **`endroid/qr-code` v6** — QR code generation as base64 PNG, embedded directly in the HTML template
- **TCG card dimensions** — Labels are sized to fit a standard card sleeve (63.5 × 88.9 mm)
- **Public URL encoding** — QR code links to the deck page, allowing anyone finding the deck to identify the owner

## TCG Card Dimensions

Each label panel is rendered at **standard poker-size TCG card dimensions**:

| Dimension | Value     | Notes                                          |
|-----------|-----------|-------------------------------------------------|
| Width     | 63.5 mm   | Standard poker card width                       |
| Height    | 88.9 mm   | Standard poker card height                      |
| Fit       | Any standard card sleeve (e.g. Ultra PRO, Dragon Shield) | Same dimensions as a Pokemon card |

## Variant 1: Simple Label

### Layout

The card-sized label contains: deck name, archetype sprites, QR code (linking to the deck page), short tag, owner identity, and the application base URL.

```
+-------------------------+
|                         |
|       Deck Name         |
|    (e.g. Ancient Box)   |
|      🔥  🐉  (sprites) |
|                         |
|  ———————————————————————|
|                         |
|     +-------------+     |
|     |   QR CODE   |     |
|     | (deck URL)  |     |
|     +-------------+     |
|       JXFBT7            |
|                         |
|  ———————————————————————|
|                         |
|     Screen Name         |
|   First Last (smaller)  |
|                         |
|  ———————————————————————|
|  expandeddecks.wip     |
|                         |
+-------------------------+
   63.5 mm x 88.9 mm
```

### Page Layout

Single label **centered on an A4 portrait page** with crop marks at the four corners. Horizontal crop marks extend from the page edge to the label edge for easy cutting alignment.

### Route

```
GET /deck/{short_tag}/label.pdf
```

- Content-Type: `application/pdf`
- Content-Disposition: `inline` (opens in browser's PDF viewer)
- Filename: `deck-{shortTag}-label.pdf`
- Access: restricted to the deck owner
- Template: `label/pdf_label.html.twig`

## Variant 2: Foldable Label (Label + Deck List)

### Layout

Two card-sized panels placed **side by side on landscape A4** (book layout):

```
+-------------------------+---+-------------------------+
|                         |   |                         |
|  4 Flutter Mane TEF 78  | F |       Deck Name         |
|  4 Roaring Moon TEF 109 | O |    (e.g. Ancient Box)   |
|  1 Great Tusk TEF 97    | L |      🔥  🐉  (sprites) |
|  ...                    | D |                         |
|                         |   |  ———————————————————————|
|  4 Explorer's Guid...   | L |     +-------------+     |
|  4 Prof. Sada's... TEF  | I |     |   QR CODE   |     |
|  2 Boss's Orders PAL    | N |     +-------------+     |
|  ...                    | E |       JXFBT7            |
|                         |   |  ———————————————————————|
|  4 Ancient Booster...   |   |     Screen Name         |
|  1 Exp. Share SVI 174   |   |   First Last (smaller)  |
|                         |   |  ———————————————————————|
|  7 Darkness Energy SVE  |   |  expandeddecks.wip     |
|                         |   |                         |
+-------------------------+---+-------------------------+
       BACK (deck list)    ↕         FRONT (label)
                         fold
```

**Fold like a book:** fold along the center vertical line (right panel behind left). Both sides read correctly — no upside-down issue.

### Deck List Panel

- Cards are grouped by detailed type: **pokemon → supporter → item → tool → stadium → energy**
- Trainers are split by subtype (values from `DeckCard.trainerSubtype`, lowercased); trainers with null subtype fall back to a `trainer` catch-all group
- **No section titles** — groups are visually separated by **alternating background shades** (white / light gray `#f4f4f4`)
- **Dynamic font size** — computed from the total card line count to fit the 88.9mm card height:
  - Available height ≈ 80mm minus section padding
  - Formula: `min(7, max(4, available_height / (lines × 1.4 × 0.353)))` (pt→mm conversion)
  - Typical result: ~5.5–6pt for a standard 60-card deck
- Each line shows: `{quantity} {cardName} {setCode} {cardNumber}`
- Set code and card number are rendered in a lighter, slightly smaller font

### Route

```
GET /deck/{short_tag}/label-foldable.pdf
```

- Content-Type: `application/pdf`
- Content-Disposition: `inline`
- Filename: `deck-{shortTag}-label-foldable.pdf`
- Access: restricted to the deck owner
- Template: `label/pdf_label_foldable.html.twig`
- Only available when the deck has a current version (deck list exists)

## QR Code Generation

Uses **`endroid/qr-code` v6** PHP library to generate QR codes server-side.

| Parameter          | Value      | Notes                                              |
|--------------------|------------|----------------------------------------------------|
| Content            | Public deck URL (e.g. `https://expandeddecks.wip/deck/JXFBT7`) | Anyone can scan to find the owner |
| Error correction   | M (15%)    | Balances data density with damage tolerance         |
| Rendered size      | 18 mm (CSS), 300 px (generated) | Scaled down via CSS for sharpness |
| Output format      | PNG (base64-encoded) | Embedded directly in the HTML template    |

The QR code encodes the **public deck URL** (not a raw identifier). This means:

- Any smartphone camera app can scan it and open the deck page directly
- USB HID scanners (F5.3) and camera QR scanners (F5.6) can also read it
- The URL is generated via `UrlGeneratorInterface::ABSOLUTE_URL`, using the `DEFAULT_URI` env var as base

## Dompdf Integration

### Service: `App\Service\Label\PdfLabelGenerator`

Two public methods:

| Method | Description | Paper |
|--------|-------------|-------|
| `generate(Deck)` | Simple label (single card) | A4 portrait |
| `generateFoldable(Deck)` | Foldable label + deck list (two cards) | A4 landscape |

Internal helpers:
- `generateQrCode(string $url)` — returns base64 data URI via endroid/qr-code v6 Builder API
- `buildSpriteDataUris(Deck)` — reads archetype sprite PNGs from `public/build/sprites/pokemon/` and converts to base64 data URIs (Dompdf cannot resolve web-relative paths)
- `groupCards(DeckVersion)` — groups and sorts cards by detailed type (pokemon/supporter/item/tool/stadium/trainer/energy), sorted by quantity desc then name asc
- `renderPdf(string $html, string $orientation)` — Dompdf render with configurable orientation

### Dompdf Quirks

- **No `box-sizing: border-box`** — label dimensions are manually computed: content width/height = outer size − padding − border
- **No CSS transforms** — the foldable layout uses side-by-side panels (book fold) instead of rotating one panel 180°
- **No relative image paths** — all images (QR code, sprites) must be embedded as base64 data URIs
- **`overflow: hidden`** — used on label panels to clip content at the border

### Templates

| Template | Variant | Paper |
|----------|---------|-------|
| `label/pdf_label.html.twig` | Simple label | A4 portrait |
| `label/pdf_label_foldable.html.twig` | Foldable label + list | A4 landscape |

### Controller

Both routes are on `DeckShowController` using `{short_tag}` with `#[MapEntity]` for deck resolution:

```php
#[Route('/deck/{short_tag}/label.pdf', ...)]
#[Route('/deck/{short_tag}/label-foldable.pdf', ...)]
```

## Configuration

| Setting                          | Value   | Location                                |
|----------------------------------|---------|-----------------------------------------|
| QR code generation size (px)     | `300`   | `PdfLabelGenerator::QR_CODE_SIZE_PX`   |
| QR rendered size (CSS)           | `18mm`  | `pdf_label.html.twig`                   |
| Sprite size (CSS)                | `12mm`  | `pdf_label.html.twig`                   |
| Page format (simple)             | A4 portrait | `PdfLabelGenerator::generate()`     |
| Page format (foldable)           | A4 landscape | `PdfLabelGenerator::generateFoldable()` |
| Base URL for QR content          | env     | `DEFAULT_URI` in `.env`                 |
| Decklist font size range         | 4–7 pt  | `PdfLabelGenerator::generateFoldable()` |

## ZPL Label vs PDF Label Card

| Aspect              | ZPL Label (F5.1)                 | PDF Label Card (F5.7)               |
|---------------------|----------------------------------|--------------------------------------|
| Output format       | ZPL code → thermal print         | HTML → PDF → inkjet/laser print      |
| Printer             | Zebra thermal (via PrintNode)    | Any home printer                     |
| Label size          | Zebra label stock (varies)       | 63.5 x 88.9 mm (TCG card size)      |
| Use case            | At venue — instant printing      | At home — pre-event preparation      |
| QR encoding         | Deck identifier                  | Public deck URL                      |
| Scanner compatible  | F5.3 (HID) + F5.6 (camera)      | F5.3 (HID) + F5.6 (camera)          |
| Physical form       | Adhesive label on deck box       | Card in sleeve at front of deck box  |
| Variants            | Single label                     | Simple label + foldable with deck list |

Both label types produce scannable QR codes, so the scanning infrastructure is fully shared.

## References

- [Dompdf documentation](https://github.com/dompdf/dompdf)
- [`endroid/qr-code` documentation](https://github.com/endroid/qr-code)
- [USB HID Scanner Detection](scanner.md) — Barcode scanner integration
- [Camera QR Scanner](camera_scanner.md) — Mobile camera fallback
