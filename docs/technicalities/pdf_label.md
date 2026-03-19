# PDF Label Card (Home Printing)

> **Audience:** Developer, AI Agent · **Scope:** Technical Reference

← Back to [Main Documentation](../docs.md) | [Feature F5.7](../features.md) | [USB HID Scanner](scanner.md) | [Camera QR Scanner](camera_scanner.md)

---

## Problem

The existing label printing system ([F5.1](../features.md) / [F5.2](../features.md)) relies on a **Zebra thermal printer** available at event venues via PrintNode. Users preparing decks **at home** before an event have no way to generate a physical label for their deck box.

Without a label, the deck cannot be scanned at the event for hand-off (F4.3) or return (F4.4). The user must either wait until they arrive at a venue with a Zebra printer, or have someone else print the label remotely — neither option is practical for pre-event preparation.

## Strategy

Generate a **downloadable PDF** containing a single TCG card-sized label that the user can print on any home printer, cut out, and slip into a card sleeve at the front of the deck box.

Key choices:

- **Dompdf** — HTML/CSS to PDF conversion via a Twig template, well integrated with Symfony
- **`endroid/qr-code`** — QR code generation as base64 PNG, embedded directly in the HTML template
- **TCG card dimensions** — The label is sized to fit a standard card sleeve, so it sits naturally in the deck box
- **Same QR encoding** — Identical deck identifier format as the ZPL label (F5.1), so existing scanners (F5.3, F5.6) work unchanged

## TCG Card Dimensions

The label is rendered at **standard poker-size TCG card dimensions**:

| Dimension | Value     | Notes                                          |
|-----------|-----------|-------------------------------------------------|
| Width     | 63.5 mm   | Standard poker card width                       |
| Height    | 88.9 mm   | Standard poker card height                      |
| Fit       | Any standard card sleeve (e.g. Ultra PRO, Dragon Shield) | Same dimensions as a Pokemon card |

## Label Layout

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
|  expanded-decks.wip     |
|                         |
+-------------------------+
   63.5 mm x 88.9 mm
```

The QR code encodes the **public deck URL** (e.g. `https://expanded-decks.wip/deck/JXFBT7`), generated via Symfony's `UrlGeneratorInterface` using the `DEFAULT_URI` env var. This allows anyone finding the deck to scan the code and reach the deck page to identify the owner.

## Printed Page Layout

The label is **centered on an A4 (or Letter) page** with crop marks at the four corners to guide cutting:

```
+--------------------------------------------------+
|                                                  |
|                                                  |
|                  +    +                          |
|                  |    |                          |
|              ----+----+----                      |
|              |            |                      |
|              |   LABEL    |                      |
|              |  63.5 mm   |                      |
|              |  x 88.9mm  |                      |
|              |            |                      |
|              ----+----+----                      |
|                  |    |                          |
|                  +    +                          |
|                                                  |
|                                                  |
|                  A4 / Letter                     |
+--------------------------------------------------+
```

One label per page. Crop marks are thin lines extending ~5 mm outward from each corner of the label rectangle.

## Dompdf + Symfony Integration

### Package

**`dompdf/dompdf`** — a PHP library that renders HTML + CSS into PDF documents. It supports `@page` CSS rules, mm-unit positioning, and embedded base64 images (for the QR code).

### Service: `App\Service\Label\PdfLabelGenerator`

Responsibilities:

1. Accept a `Deck` entity
2. Generate a QR code (base64 PNG) encoding the deck identifier — same format as F5.1
3. Render the Twig template `label/pdf_label.html.twig` with deck data and QR image
4. Pass the rendered HTML to Dompdf and return the PDF binary string

```php
class PdfLabelGenerator
{
    public function generate(Deck $deck): string
    {
        // 1. Generate QR code as base64 PNG
        // 2. Render Twig template with deck data + QR
        // 3. Dompdf render → return PDF binary
    }
}
```

### Twig Template: `label/pdf_label.html.twig`

The template uses CSS `@page` rules and mm-unit positioning to produce the exact card dimensions:

- `@page { size: A4; margin: 0; }` — full-bleed A4 page
- The label is a `div` with content-box dimensions computed to produce a 63.5 × 88.9mm outer size (Dompdf does not support `border-box`), centered on the page with absolute positioning
- Crop marks are rendered as absolutely positioned `div` elements at each corner — horizontal marks span from page edge to label edge
- Separator lines span the full label width (edge-to-edge) using `margin: 0`
- The QR code and archetype sprites are embedded as `<img src="data:image/png;base64,...">` tags (Dompdf cannot resolve relative paths)
- Text is sized for legibility at print resolution (deck name ~12pt, screen name ~10pt, full name ~7.5pt, short tag ~9pt mono, base URL ~6.5pt)

### Route

```
GET /deck/{id}/label.pdf
```

- Controller action returns a `Response` with `Content-Type: application/pdf`
- Content-Disposition: `inline` (opens in browser's PDF viewer for direct printing)
- Filename: `deck-{shortTag}-label.pdf`
- Access: restricted to the deck owner

## QR Code Generation

Uses the **`endroid/qr-code`** PHP library to generate QR codes server-side.

| Parameter          | Value      | Notes                                              |
|--------------------|------------|----------------------------------------------------|
| Content            | Public deck URL (e.g. `https://expanded-decks.wip/deck/JXFBT7`) | Anyone can scan to find the owner |
| Error correction   | M (15%)    | Balances data density with damage tolerance         |
| Rendered size      | 18 mm (CSS), 300 px (generated) | Scaled down via CSS for sharpness |
| Output format      | PNG (base64-encoded) | Embedded directly in the HTML template    |

The QR code encodes the **public deck URL** (not a raw identifier). This means:

- Any smartphone camera app can scan it and open the deck page directly
- USB HID scanners (F5.3) and camera QR scanners (F5.6) can also read it
- The URL is generated via `UrlGeneratorInterface::ABSOLUTE_URL`, using the `DEFAULT_URI` env var as base

## Configuration

| Setting                          | Value   | Location                                |
|----------------------------------|---------|-----------------------------------------|
| QR code generation size (px)     | `300`   | `PdfLabelGenerator::QR_CODE_SIZE_PX`   |
| QR rendered size (CSS)           | `18mm`  | `pdf_label.html.twig`                   |
| Sprite size (CSS)                | `12mm`  | `pdf_label.html.twig`                   |
| Page format                      | `A4`    | `PdfLabelGenerator::renderPdf()`        |
| Base URL for QR content          | env     | `DEFAULT_URI` in `.env`                 |

## ZPL Label vs PDF Label Card

| Aspect              | ZPL Label (F5.1)                 | PDF Label Card (F5.7)               |
|---------------------|----------------------------------|--------------------------------------|
| Output format       | ZPL code → thermal print         | HTML → PDF → inkjet/laser print      |
| Printer             | Zebra thermal (via PrintNode)    | Any home printer                     |
| Label size          | Zebra label stock (varies)       | 63.5 x 88.9 mm (TCG card size)      |
| Use case            | At venue — instant printing      | At home — pre-event preparation      |
| QR encoding         | Deck identifier                  | Same deck identifier                 |
| Scanner compatible  | F5.3 (HID) + F5.6 (camera)      | F5.3 (HID) + F5.6 (camera)          |
| Physical form       | Adhesive label on deck box       | Card in sleeve at front of deck box  |

Both label types produce scannable QR codes with identical encoding, so the scanning infrastructure is fully shared.

## References

- [Dompdf documentation](https://github.com/dompdf/dompdf)
- [`endroid/qr-code` documentation](https://github.com/endroid/qr-code)
- [USB HID Scanner Detection](scanner.md) — Barcode scanner integration
- [Camera QR Scanner](camera_scanner.md) — Mobile camera fallback
